<?php
/**
 * Point of Sale (POS) & Bill Creation
 * MOMAI PLYWOOD - Core PHP
 */
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth_guard.php';

$pdo = get_db();

// Handle Bill Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $customerName = trim($_POST['customer_name'] ?? '');
    $customerPhone = trim($_POST['customer_phone'] ?? '');
    $customerAddress = trim($_POST['customer_address'] ?? '');
    $discount = max(0, (float) ($_POST['discount'] ?? 0));
    $paidAmountInput = max(0, (float) ($_POST['paid_amount'] ?? 0));
    $paymentMethod = trim($_POST['payment_method'] ?? 'cash') ?: 'cash';
    $notes = trim($_POST['notes'] ?? '');

    $itemsRaw = $_POST['items'] ?? [];

    if (empty($customerName) || empty($itemsRaw) || !is_array($itemsRaw)) {
        flash_set('error', 'Please provide a customer name and at least one item.');
        redirect('bill-create.php');
    }

    try {
        $pdo->beginTransaction();

        // 1. Resolve Customer
        $customerId = null;
        if (!empty($customerPhone)) {
            $stmt = $pdo->prepare("SELECT id FROM customers WHERE phone = ? LIMIT 1");
            $stmt->execute([$customerPhone]);
            $existing = $stmt->fetch();
            if ($existing) {
                $customerId = $existing['id'];
                if (!empty($customerAddress)) {
                    $pdo->prepare("UPDATE customers SET address = ? WHERE id = ?")->execute([$customerAddress, $customerId]);
                }
            } else {
                $ins = $pdo->prepare("INSERT INTO customers (name, phone, address, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
                $ins->execute([$customerName, $customerPhone, $customerAddress ?: null]);
                $customerId = $pdo->lastInsertId();
            }
        }

        // 2. Compute Lines & Subtotal
        $subtotal = 0;
        $processedItems = [];

        foreach ($itemsRaw as $item) {
            $qty = max(1, (int) ($item['quantity'] ?? 1));
            $unitPrice = max(0, (float) ($item['unit_price'] ?? 0));
            $productId = !empty($item['product_id']) ? (int) $item['product_id'] : null;
            $productName = trim($item['product_name'] ?? '');

            if ($productId) {
                $pStmt = $pdo->prepare("SELECT id, name, stock_quantity FROM products WHERE id = ? FOR UPDATE");
                $pStmt->execute([$productId]);
                $product = $pStmt->fetch();
                if ($product) {
                    $productName = $product['name'];
                    // Decrement inventory
                    $newQty = max(0, (int)$product['stock_quantity'] - $qty);
                    $pdo->prepare("UPDATE products SET stock_quantity = ?, updated_at = NOW() WHERE id = ?")
                        ->execute([$newQty, $productId]);
                }
            }

            if (empty($productName)) {
                $productName = 'Item / General Product';
            }

            $lineTotal = $qty * $unitPrice;
            $subtotal += $lineTotal;

            $processedItems[] = [
                'product_id' => $productId,
                'product_name' => $productName,
                'unit_price' => $unitPrice,
                'quantity' => $qty,
                'total_price' => $lineTotal
            ];
        }

        $grandTotal = max(0, $subtotal - $discount);
        $paidAmount = min($grandTotal, $paidAmountInput);
        $dueAmount = max(0, $grandTotal - $paidAmount);

        if ($dueAmount <= 0) {
            $paymentStatus = 'paid';
        } elseif ($paidAmount > 0) {
            $paymentStatus = 'partial';
        } else {
            $paymentStatus = 'unpaid';
        }

        $billNumber = generate_bill_number($pdo);

        // 3. Insert Bill
        $bStmt = $pdo->prepare("
            INSERT INTO bills (bill_number, customer_id, customer_name, customer_phone, customer_address, subtotal, discount, grand_total, paid_amount, due_amount, payment_status, payment_method, notes, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $bStmt->execute([
            $billNumber,
            $customerId,
            $customerName,
            $customerPhone ?: null,
            $customerAddress ?: null,
            $subtotal,
            $discount,
            $grandTotal,
            $paidAmount,
            $dueAmount,
            $paymentStatus,
            $paymentMethod,
            $notes ?: null
        ]);
        $billId = $pdo->lastInsertId();

        // 4. Insert Bill Items
        $biStmt = $pdo->prepare("
            INSERT INTO bill_items (bill_id, product_id, product_name, unit_price, quantity, total_price, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        foreach ($processedItems as $pi) {
            $biStmt->execute([
                $billId,
                $pi['product_id'],
                $pi['product_name'],
                $pi['unit_price'],
                $pi['quantity'],
                $pi['total_price']
            ]);
        }

        // 5. Insert Initial Payment if collected
        if ($paidAmount > 0) {
            $bpStmt = $pdo->prepare("
                INSERT INTO bill_payments (bill_id, amount_paid, payment_method, note, created_at, updated_at)
                VALUES (?, ?, ?, 'Initial payment at bill generation', NOW(), NOW())
            ");
            $bpStmt->execute([$billId, $paidAmount, $paymentMethod]);
        }

        // 6. Update Customer Total Due
        if ($customerId && $dueAmount > 0) {
            $pdo->prepare("UPDATE customers SET total_due = total_due + ? WHERE id = ?")
                ->execute([$dueAmount, $customerId]);
        }

        $pdo->commit();

        flash_set('success', "Bill {$billNumber} generated successfully!");

        // Open small due slip if partial due, or official full bill if fully paid
        if ($paymentStatus !== 'paid' && $dueAmount > 0) {
            redirect("bill-due-slip.php?id={$billId}");
        } else {
            redirect("bill-slip.php?id={$billId}");
        }

    } catch (Exception $e) {
        $pdo->rollBack();
        flash_set('error', 'Failed to generate bill: ' . $e->getMessage());
        redirect('bill-create.php');
    }
}

// Generate Next Bill Number for preview
$nextBillNumber = generate_bill_number($pdo);

$pageTitle = 'Make Bill (POS) - MOMAI PLYWOOD';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/topbar.php';
?>

<div x-data="billingSystem()" class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-4 sm:p-5 rounded-2xl border border-gray-200/80 shadow-2xs">
        <div>
            <h1 class="text-xl font-bold text-gray-800 tracking-tight">
                Make Bill / Point of Sale (POS)
            </h1>
            <p class="text-xs text-gray-500">
                Issue invoices, record partial payments, and generate printable transaction slips.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="bills.php"
                class="px-3.5 py-1.5 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold transition">
                View Bills History
            </a>
        </div>
    </div>

    <form method="POST" action="bill-create.php" @submit="return validateForm()">
        <?= csrf_field() ?>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

            <!-- Left Side (8 Cols): Customer Info & Product Selection Table -->
            <div class="lg:col-span-8 space-y-6">

                <!-- Customer Details Card -->
                <div class="bg-white rounded-2xl p-5 border border-gray-200/80 shadow-2xs space-y-4">
                    <div class="text-xs font-bold text-gray-700 uppercase tracking-wider flex items-center justify-between">
                        <span>1. Customer Details</span>
                        <span class="text-[11px] font-normal text-gray-400">Bill No: <span class="font-mono text-gray-700 font-bold"><?= e($nextBillNumber) ?></span></span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">
                                Customer Name <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" name="customer_name" x-model="customer.name" required
                                placeholder="e.g. Ramesh Patel"
                                class="w-full px-3.5 py-2 rounded-xl text-xs bg-gray-50 border border-gray-300 focus:bg-white focus:border-[#324b3e] focus:ring-2 focus:ring-[#324b3e]/20 outline-none transition" />
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">
                                Phone / MO.
                            </label>
                            <input type="text" name="customer_phone" x-model="customer.phone"
                                placeholder="e.g. 9876543210"
                                class="w-full px-3.5 py-2 rounded-xl text-xs bg-gray-50 border border-gray-300 focus:bg-white focus:border-[#324b3e] focus:ring-2 focus:ring-[#324b3e]/20 outline-none transition" />
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">
                                Address (City / Area)
                            </label>
                            <input type="text" name="customer_address" x-model="customer.address"
                                placeholder="e.g. Porbandar"
                                class="w-full px-3.5 py-2 rounded-xl text-xs bg-gray-50 border border-gray-300 focus:bg-white focus:border-[#324b3e] focus:ring-2 focus:ring-[#324b3e]/20 outline-none transition" />
                        </div>
                    </div>
                </div>

                <!-- Items & Cart Card -->
                <div class="bg-white rounded-2xl p-5 border border-gray-200/80 shadow-2xs space-y-4">
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-gray-700 uppercase tracking-wider block">
                                2. Search & Add Products
                            </span>
                            <button type="button"
                                @click="showCustomItemModal = true; $nextTick(() => $refs.customNameInput.focus())"
                                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-[#324b3e]/10 hover:bg-[#324b3e] text-[#324b3e] hover:text-white text-xs font-semibold transition">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                <span>Direct Add</span>
                            </button>
                        </div>

                        <!-- Big Prominent Autosearch Bar -->
                        <div class="relative w-full" @click.away="searchOpen = false">
                            <div class="relative">
                                <input type="text" x-model="searchQuery" @input="onSearchInput()"
                                    @keydown.enter.prevent="onSearchEnter()"
                                    @focus="if (searchResults.length > 0) searchOpen = true"
                                    placeholder="Type product name to search or press Enter to add directly..."
                                    autocomplete="off"
                                    class="w-full pl-11 pr-10 py-3.5 rounded-2xl text-sm font-medium bg-gray-50/80 border-2 border-gray-200 focus:bg-white focus:border-[#324b3e] focus:ring-4 focus:ring-[#324b3e]/10 outline-none transition shadow-2xs" />
                                <svg class="w-5 h-5 text-gray-400 absolute left-3.5 top-1/2 -translate-y-1/2"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>

                            <!-- Live Results Dropdown -->
                            <div x-show="searchOpen && searchResults.length > 0" x-cloak
                                class="absolute left-0 right-0 top-full mt-2 bg-white rounded-2xl shadow-xl border border-gray-200 py-2 z-50 max-h-80 overflow-y-auto">
                                <template x-for="p in searchResults" :key="p.id">
                                    <button type="button" @click="addProductToCart(p)"
                                        class="w-full text-left px-4 py-2.5 hover:bg-gray-50 border-b border-gray-100 last:border-0 transition flex items-center justify-between">
                                        <div>
                                            <div class="font-bold text-xs text-gray-900" x-text="p.name"></div>
                                            <div class="text-[10px] text-gray-400 font-mono mt-0.5">
                                                <span x-text="p.sku"></span> • <span x-text="p.category"></span> • Stock: <span class="font-bold text-emerald-700" x-text="p.stock_quantity + ' ' + p.unit"></span>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="font-mono font-bold text-sm text-[#324b3e]" x-text="'₹' + Number(p.selling_price).toLocaleString('en-IN')"></div>
                                            <span class="text-[10px] text-emerald-600 font-semibold">+ Add Item</span>
                                        </div>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Items Cart Table -->
                    <div class="overflow-x-auto pt-2">
                        <table class="w-full text-xs text-left">
                            <thead>
                                <tr class="text-gray-400 border-b border-gray-100">
                                    <th class="pb-2 w-8">#</th>
                                    <th class="pb-2">Particulars / Description</th>
                                    <th class="pb-2 text-center w-24">Qty</th>
                                    <th class="pb-2 text-right w-28">Rate (₹)</th>
                                    <th class="pb-2 text-right w-28">Total (₹)</th>
                                    <th class="pb-2 text-center w-12">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="(item, index) in items" :key="index">
                                    <tr class="hover:bg-gray-50/60">
                                        <td class="py-2.5 text-gray-400 font-mono" x-text="index + 1"></td>
                                        <td class="py-2.5">
                                            <input type="hidden" :name="'items[' + index + '][product_id]'" :value="item.product_id">
                                            <input type="text" :name="'items[' + index + '][product_name]'" x-model="item.product_name" required
                                                class="w-full px-2 py-1 rounded-lg text-xs border border-gray-200 font-semibold text-gray-900 focus:border-[#324b3e] outline-none">
                                        </td>
                                        <td class="py-2.5 text-center">
                                            <input type="number" :name="'items[' + index + '][quantity]'" x-model.number="item.quantity" @input="calculateTotals()" required min="1"
                                                class="w-16 px-2 py-1 rounded-lg text-xs text-center border border-gray-200 font-mono font-bold text-gray-900 focus:border-[#324b3e] outline-none">
                                        </td>
                                        <td class="py-2.5 text-right">
                                            <input type="number" step="0.01" :name="'items[' + index + '][unit_price]'" x-model.number="item.unit_price" @input="calculateTotals()" required min="0"
                                                class="w-24 px-2 py-1 rounded-lg text-xs text-right border border-gray-200 font-mono font-bold text-gray-900 focus:border-[#324b3e] outline-none">
                                        </td>
                                        <td class="py-2.5 text-right font-mono font-bold text-gray-900" x-text="'₹' + (item.quantity * item.unit_price).toFixed(2)"></td>
                                        <td class="py-2.5 text-center">
                                            <button type="button" @click="removeItem(index)" class="p-1 text-gray-400 hover:text-rose-600 transition">
                                                ✕
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="items.length === 0">
                                    <td colspan="6" class="py-8 text-center text-gray-400 text-xs">
                                        No items in bill yet. Search products above or click "Direct Add".
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Notes Card -->
                <div class="bg-white rounded-2xl p-5 border border-gray-200/80 shadow-2xs">
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Invoice Notes / Remarks</label>
                    <textarea name="notes" rows="2" placeholder="e.g. Delivered to factory site..." class="w-full px-3.5 py-2 rounded-xl text-xs bg-gray-50 border border-gray-300 outline-none"></textarea>
                </div>
            </div>

            <!-- Right Side (4 Cols): Summary, Payment Calculation & Generate Action -->
            <div class="lg:col-span-4 space-y-6">
                <div class="bg-white rounded-2xl p-5 border border-gray-200/80 shadow-2xs space-y-4 sticky top-6">
                    <div class="text-xs font-bold text-gray-700 uppercase tracking-wider pb-2 border-b border-gray-100">
                        3. Billing Summary
                    </div>

                    <div class="space-y-3 text-xs">
                        <div class="flex justify-between text-gray-600">
                            <span>Subtotal</span>
                            <span class="font-mono font-bold text-gray-900" x-text="'₹' + subtotal.toFixed(2)">₹0.00</span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-gray-600">Discount (₹)</span>
                            <input type="number" step="0.01" name="discount" x-model.number="discount" @input="calculateTotals()" min="0" placeholder="0"
                                class="w-28 px-2.5 py-1 text-right rounded-lg border border-gray-300 font-mono font-bold outline-none text-rose-600">
                        </div>

                        <div class="pt-2 border-t border-gray-100 flex justify-between items-baseline">
                            <span class="font-bold text-sm text-gray-800">Grand Total</span>
                            <span class="font-mono font-black text-xl text-[#324b3e]" x-text="'₹' + grandTotal.toFixed(2)">₹0.00</span>
                        </div>

                        <!-- Payment Collection Input -->
                        <div class="pt-3 border-t border-gray-100 space-y-3">
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="block font-semibold text-gray-700">Amount Paid (₹) *</label>
                                    <div class="flex gap-1.5">
                                        <button type="button" @click="paidAmount = grandTotal; calculateTotals(false)" 
                                            class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 hover:bg-emerald-200 transition">
                                            Full Paid
                                        </button>
                                        <button type="button" @click="paidAmount = 0; calculateTotals(false)" 
                                            class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800 hover:bg-rose-200 transition">
                                            Unpaid (₹0)
                                        </button>
                                    </div>
                                </div>
                                <input type="number" step="0.01" name="paid_amount" x-model.number="paidAmount" @input="calculateTotals(false)" required min="0"
                                    class="w-full px-3.5 py-2.5 rounded-xl border-2 border-emerald-500 font-mono font-black text-emerald-800 text-base outline-none bg-emerald-50/20">
                            </div>

                            <div class="flex justify-between items-center py-2 px-3 rounded-xl bg-gray-50 border border-gray-200">
                                <span class="font-semibold text-gray-600">Remaining Due:</span>
                                <span class="font-mono font-black text-sm text-rose-600" x-text="'₹' + dueAmount.toFixed(2)">₹0.00</span>
                            </div>

                            <div>
                                <label class="block font-semibold text-gray-700 mb-1">Payment Method</label>
                                <select name="payment_method" class="w-full px-3.5 py-2 rounded-xl text-xs bg-gray-50 border border-gray-300 outline-none">
                                    <option value="cash">Cash</option>
                                    <option value="upi">UPI / Online / GPay</option>
                                    <option value="bank_transfer">Bank Transfer / NEFT</option>
                                    <option value="cheque">Cheque</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-gray-100">
                        <button type="submit"
                            class="w-full py-3 px-4 rounded-xl bg-[#324b3e] hover:bg-[#23382f] text-white text-xs font-bold shadow-md transition flex items-center justify-center gap-2 active:scale-[0.99]">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Save & Print Invoice Slip</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <!-- Direct Add Custom Item Modal -->
    <div x-show="showCustomItemModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
        <div @click.away="showCustomItemModal = false" class="bg-white rounded-3xl p-6 max-w-sm w-full shadow-2xl border border-gray-100 space-y-4">
            <div class="flex items-center justify-between pb-2 border-b border-gray-100">
                <h3 class="font-bold text-sm text-gray-800">Add Item Directly</h3>
                <button type="button" @click="showCustomItemModal = false" class="text-gray-400 hover:text-gray-600">✕</button>
            </div>

            <div class="space-y-3 text-xs">
                <div>
                    <label class="block font-semibold text-gray-700 mb-1">Item / Particulars *</label>
                    <input type="text" x-ref="customNameInput" x-model="customItem.name" placeholder="e.g. Cut pieces, Labour, Transport"
                        class="w-full px-3.5 py-2 rounded-xl bg-gray-50 border border-gray-300 outline-none">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold text-gray-700 mb-1">Quantity *</label>
                        <input type="number" x-model.number="customItem.quantity" min="1"
                            class="w-full px-3.5 py-2 rounded-xl bg-gray-50 border border-gray-300 outline-none font-mono">
                    </div>
                    <div>
                        <label class="block font-semibold text-gray-700 mb-1">Rate (₹) *</label>
                        <input type="number" step="0.01" x-model.number="customItem.unit_price" min="0" placeholder="0.00"
                            class="w-full px-3.5 py-2 rounded-xl bg-gray-50 border border-gray-300 outline-none font-mono">
                    </div>
                </div>
            </div>

            <div class="pt-2 flex justify-end gap-2">
                <button type="button" @click="showCustomItemModal = false" class="px-4 py-2 rounded-xl bg-gray-100 text-gray-600 text-xs font-semibold">Cancel</button>
                <button type="button" @click="addCustomItemToCart()" class="px-4 py-2 rounded-xl bg-[#324b3e] text-white text-xs font-bold shadow-md">Add to Cart</button>
            </div>
        </div>
    </div>
</div>

<script>
    function billingSystem() {
        return {
            customer: { name: '', phone: '', address: '' },
            items: [],
            searchQuery: '',
            searchResults: [],
            searchOpen: false,
            debounceTimer: null,
            subtotal: 0,
            discount: 0,
            grandTotal: 0,
            paidAmount: 0,
            dueAmount: 0,
            showCustomItemModal: false,
            customItem: { name: '', quantity: 1, unit_price: 0 },

            onSearchInput() {
                clearTimeout(this.debounceTimer);
                const q = this.searchQuery.trim();
                if (q.length < 2) {
                    this.searchResults = [];
                    this.searchOpen = false;
                    return;
                }

                this.debounceTimer = setTimeout(() => {
                    fetch('product-search.php?q=' + encodeURIComponent(q))
                        .then(res => res.json())
                        .then(data => {
                            this.searchResults = data;
                            this.searchOpen = true;
                        });
                }, 150);
            },

            onSearchEnter() {
                if (this.searchResults.length > 0) {
                    this.addProductToCart(this.searchResults[0]);
                } else if (this.searchQuery.trim().length > 0) {
                    this.customItem.name = this.searchQuery.trim();
                    this.showCustomItemModal = true;
                }
            },

            addProductToCart(prod) {
                this.items.push({
                    product_id: prod.id,
                    product_name: prod.name,
                    quantity: 1,
                    unit_price: parseFloat(prod.selling_price) || 0
                });
                this.searchQuery = '';
                this.searchResults = [];
                this.searchOpen = false;
                this.calculateTotals();
            },

            addCustomItemToCart() {
                if (!this.customItem.name.trim()) return;
                this.items.push({
                    product_id: null,
                    product_name: this.customItem.name.trim(),
                    quantity: Math.max(1, this.customItem.quantity),
                    unit_price: Math.max(0, this.customItem.unit_price)
                });
                this.customItem = { name: '', quantity: 1, unit_price: 0 };
                this.showCustomItemModal = false;
                this.calculateTotals();
            },

            removeItem(index) {
                this.items.splice(index, 1);
                this.calculateTotals();
            },

            calculateTotals(autoFill = true) {
                let sub = 0;
                this.items.forEach(i => {
                    sub += (i.quantity * i.unit_price);
                });
                this.subtotal = sub;
                this.grandTotal = Math.max(0, sub - (this.discount || 0));
                
                // Only auto-fill paid amount when adding new items if user hasn't explicitly set payment
                if (autoFill && (this.paidAmount === undefined || this.paidAmount === null || this.paidAmount > this.grandTotal)) {
                    this.paidAmount = this.grandTotal;
                }
                this.dueAmount = Math.max(0, this.grandTotal - (this.paidAmount || 0));
            },

            validateForm() {
                if (this.items.length === 0) {
                    alert('Please add at least one product to the invoice.');
                    return false;
                }
                return true;
            }
        }
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

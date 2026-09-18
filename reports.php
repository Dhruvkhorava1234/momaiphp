<?php
/**
 * Business Intelligence & Reports
 * MOMAI PLYWOOD - Core PHP
 */
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth_guard.php';

$pdo = get_db();

// 1. Pending Customer Bills
$stmt = $pdo->query("
    SELECT * FROM bills 
    WHERE deleted_at IS NULL AND (due_amount > 0 OR payment_status IN ('partial', 'unpaid')) 
    ORDER BY id DESC
");
$pendingBills = $stmt->fetchAll();
$totalPendingAmount = array_sum(array_column($pendingBills, 'due_amount'));
$totalPendingCount = count($pendingBills);

// 2. Out of Stock Products
$stmt = $pdo->query("SELECT * FROM products WHERE deleted_at IS NULL AND stock_quantity <= 0 ORDER BY id DESC");
$outOfStockProducts = $stmt->fetchAll();
$outOfStockCount = count($outOfStockProducts);

// 3. Low Stock Products
$stmt = $pdo->query("SELECT * FROM products WHERE deleted_at IS NULL AND stock_quantity > 0 AND stock_quantity <= min_alert_stock ORDER BY id DESC");
$lowStockProducts = $stmt->fetchAll();
$lowStockCount = count($lowStockProducts);

$pageTitle = 'Reports & Intelligence - MOMAI PLYWOOD';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/topbar.php';
?>

<div x-data="{ activeTab: 'pending' }" class="space-y-6">
    
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-gray-200/80 shadow-2xs">
        <div>
            <h1 class="text-xl font-bold text-gray-800 tracking-tight">
                Business Intelligence & Reports
            </h1>
            <p class="text-xs text-gray-500">
                Real-time tracking of pending customer bills, out of stock products, and low stock inventory alerts.
            </p>
        </div>
        
        <div class="flex items-center gap-2">
            <!-- Export to Excel Dropdown -->
            <div class="relative" x-data="{ exportOpen: false }">
                <button type="button"
                        @click="exportOpen = !exportOpen"
                        @click.outside="exportOpen = false"
                        style="background-color: #324b3e;"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-white text-xs font-bold shadow-md transition active:scale-[0.98] hover:opacity-90">
                    <!-- Excel icon -->
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h4a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                    </svg>
                    <span>Export to Excel</span>
                    <svg class="w-3 h-3 transition-transform duration-200" :class="exportOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                <!-- Dropdown menu -->
                <div x-show="exportOpen"
                     x-cloak
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 scale-95 translate-y-1"
                     x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                     x-transition:leave-end="opacity-0 scale-95 translate-y-1"
                     class="absolute right-0 mt-2 w-56 bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden z-30 py-1.5">

                    <div class="px-3 pt-2 pb-1.5 text-[10px] font-bold text-gray-400 uppercase tracking-wider">Choose Export</div>

                    <a href="report-export.php?type=all"
                       class="flex items-center gap-2.5 px-3.5 py-2.5 hover:bg-emerald-50 text-xs font-bold text-emerald-800 transition">
                        <svg class="w-4 h-4 text-emerald-600 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Full Report (All Sheets)
                    </a>

                    <div class="border-t border-gray-100 mx-3 my-1"></div>

                    <a href="report-export.php?type=pending"
                       class="flex items-center gap-2.5 px-3.5 py-2.5 hover:bg-rose-50 text-xs text-gray-700 font-semibold transition">
                        <span class="w-2 h-2 rounded-full bg-rose-500 shrink-0"></span>
                        Pending Customer Bills
                    </a>

                    <a href="report-export.php?type=out_of_stock"
                       class="flex items-center gap-2.5 px-3.5 py-2.5 hover:bg-rose-50 text-xs text-gray-700 font-semibold transition">
                        <span class="w-2 h-2 rounded-full bg-rose-800 shrink-0"></span>
                        Out of Stock Products
                    </a>

                    <a href="report-export.php?type=low_stock"
                       class="flex items-center gap-2.5 px-3.5 py-2.5 hover:bg-amber-50 text-xs text-gray-700 font-semibold transition">
                        <span class="w-2 h-2 rounded-full bg-amber-500 shrink-0"></span>
                        Low Stock Alert Items
                    </a>
                </div>
            </div>

        </div>
    </div>

    <!-- 3 Summary Alert Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <!-- 1. Pending Bills Card -->
        <div @click="activeTab = 'pending'"
             :class="activeTab === 'pending' ? 'ring-2 ring-rose-500 border-rose-300 shadow-md' : 'hover:border-gray-300'"
             class="bg-white p-5 rounded-2xl border border-gray-200/80 shadow-2xs transition cursor-pointer flex flex-col justify-between group">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-rose-700">Pending Customer Bills</span>
                <div class="w-9 h-9 rounded-xl bg-rose-50 border border-rose-200 text-rose-600 flex items-center justify-center font-bold text-xs">
                    <?= $totalPendingCount ?>
                </div>
            </div>
            <div class="mt-4">
                <div class="text-2xl font-black font-mono text-gray-900">
                    <?= format_inr($totalPendingAmount) ?>
                </div>
                <div class="flex items-center justify-between text-xs mt-1 text-gray-500">
                    <span><?= $totalPendingCount ?> bills with unpaid dues</span>
                    <span class="text-rose-600 font-semibold group-hover:underline">View List →</span>
                </div>
            </div>
        </div>

        <!-- 2. Out of Stock Card -->
        <div @click="activeTab = 'out'"
             :class="activeTab === 'out' ? 'ring-2 ring-rose-600 border-rose-300 shadow-md' : 'hover:border-gray-300'"
             class="bg-white p-5 rounded-2xl border border-gray-200/80 shadow-2xs transition cursor-pointer flex flex-col justify-between group">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-rose-800">Out of Stock Items</span>
                <div class="w-9 h-9 rounded-xl bg-rose-100 border border-rose-300 text-rose-700 flex items-center justify-center font-bold text-xs">
                    <?= $outOfStockCount ?>
                </div>
            </div>
            <div class="mt-4">
                <div class="text-2xl font-black font-mono text-rose-600">
                    <?= $outOfStockCount ?> <span class="text-sm font-sans font-medium text-gray-500">Products</span>
                </div>
                <div class="flex items-center justify-between text-xs mt-1 text-gray-500">
                    <span>Stock quantity reached 0</span>
                    <span class="text-rose-600 font-semibold group-hover:underline">View Items →</span>
                </div>
            </div>
        </div>

        <!-- 3. Low Stock Alert Card -->
        <div @click="activeTab = 'low'"
             :class="activeTab === 'low' ? 'ring-2 ring-amber-500 border-amber-300 shadow-md' : 'hover:border-gray-300'"
             class="bg-white p-5 rounded-2xl border border-gray-200/80 shadow-2xs transition cursor-pointer flex flex-col justify-between group">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-amber-700">Low Stock Alert</span>
                <div class="w-9 h-9 rounded-xl bg-amber-50 border border-amber-200 text-amber-600 flex items-center justify-center font-bold text-xs">
                    <?= $lowStockCount ?>
                </div>
            </div>
            <div class="mt-4">
                <div class="text-2xl font-black font-mono text-amber-600">
                    <?= $lowStockCount ?> <span class="text-sm font-sans font-medium text-gray-500">Products</span>
                </div>
                <div class="flex items-center justify-between text-xs mt-1 text-gray-500">
                    <span>Below replenishment alert</span>
                    <span class="text-amber-600 font-semibold group-hover:underline">View Items →</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 pb-3">
        <button type="button"
                @click="activeTab = 'pending'"
                :class="activeTab === 'pending' ? 'bg-[#324b3e] text-white shadow-xs' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200'"
                class="px-4 py-2 rounded-full text-xs font-bold transition flex items-center gap-2">
            <span>Pending Bills</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] bg-rose-500 text-white font-mono"><?= $totalPendingCount ?></span>
        </button>

        <button type="button"
                @click="activeTab = 'out'"
                :class="activeTab === 'out' ? 'bg-rose-700 text-white shadow-xs' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200'"
                class="px-4 py-2 rounded-full text-xs font-bold transition flex items-center gap-2">
            <span>Out of Stock</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] bg-rose-200 text-rose-800 font-mono"><?= $outOfStockCount ?></span>
        </button>

        <button type="button"
                @click="activeTab = 'low'"
                :class="activeTab === 'low' ? 'bg-amber-600 text-white shadow-xs' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200'"
                class="px-4 py-2 rounded-full text-xs font-bold transition flex items-center gap-2">
            <span>Low Stock Items</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] bg-amber-200 text-amber-900 font-mono"><?= $lowStockCount ?></span>
        </button>
    </div>

    <!-- Tab 1: Pending Bills -->
    <div x-show="activeTab === 'pending'" x-cloak class="bg-white rounded-2xl border border-gray-200/80 shadow-2xs overflow-hidden">
        <div class="p-4 sm:p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-sm text-gray-800">Pending Customer Invoices</h3>
                <span class="text-xs text-gray-400 font-mono">Total Due: <?= format_inr($totalPendingAmount) ?></span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead>
                        <tr class="text-gray-400 border-b border-gray-100">
                            <th class="pb-3">Bill No</th>
                            <th class="pb-3">Customer</th>
                            <th class="pb-3">Date</th>
                            <th class="pb-3 text-right">Grand Total</th>
                            <th class="pb-3 text-right">Paid</th>
                            <th class="pb-3 text-right">Balance Due</th>
                            <th class="pb-3 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php if (!empty($pendingBills)): ?>
                            <?php foreach ($pendingBills as $bill): ?>
                                <tr class="hover:bg-gray-50/60 transition">
                                    <td class="py-3 font-mono font-bold text-gray-800">
                                        <a href="bill-slip.php?id=<?= $bill['id'] ?>" class="hover:text-[#324b3e] underline">
                                            <?= e($bill['bill_number']) ?>
                                        </a>
                                    </td>
                                    <td class="py-3">
                                        <div class="font-semibold text-gray-900"><?= e($bill['customer_name']) ?></div>
                                        <?php if (!empty($bill['customer_phone'])): ?>
                                            <div class="text-[10px] text-gray-400 font-mono"><?= e($bill['customer_phone']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 text-gray-500 font-mono">
                                        <?= date('d M Y', strtotime($bill['created_at'])) ?>
                                    </td>
                                    <td class="py-3 text-right font-mono font-bold text-gray-900">
                                        <?= format_inr((float)$bill['grand_total']) ?>
                                    </td>
                                    <td class="py-3 text-right font-mono text-emerald-700 font-bold">
                                        <?= format_inr((float)$bill['paid_amount']) ?>
                                    </td>
                                    <td class="py-3 text-right font-mono font-black text-rose-600">
                                        <?= format_inr((float)$bill['due_amount']) ?>
                                    </td>
                                    <td class="py-3 text-center">
                                        <a href="bill-due-slip.php?id=<?= $bill['id'] ?>" class="px-3 py-1 rounded-lg bg-gray-100 hover:bg-[#324b3e] hover:text-white text-gray-700 text-[11px] font-bold transition">
                                            Slip
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="py-8 text-center text-gray-400">
                                    Great news! All customer bills are fully settled.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Tab 2: Out of Stock -->
    <div x-show="activeTab === 'out'" x-cloak class="bg-white rounded-2xl border border-gray-200/80 shadow-2xs overflow-hidden">
        <div class="p-4 sm:p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-sm text-gray-800">Critical: Out of Stock Products</h3>
                <span class="text-xs text-rose-600 font-bold"><?= $outOfStockCount ?> items require reorder</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead>
                        <tr class="text-gray-400 border-b border-gray-100">
                            <th class="pb-3">SKU</th>
                            <th class="pb-3">Product Name</th>
                            <th class="pb-3">Company</th>
                            <th class="pb-3">Category</th>
                            <th class="pb-3 text-right">Selling Price</th>
                            <th class="pb-3 text-center">Current Stock</th>
                            <th class="pb-3 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php if (!empty($outOfStockProducts)): ?>
                            <?php foreach ($outOfStockProducts as $p): ?>
                                <tr class="hover:bg-gray-50/60 transition">
                                    <td class="py-3 font-mono font-bold text-gray-700"><?= e($p['sku']) ?></td>
                                    <td class="py-3 font-bold text-gray-900"><?= e($p['name']) ?></td>
                                    <td class="py-3 text-gray-600"><?= e($p['company_name'] ?: '—') ?></td>
                                    <td class="py-3">
                                        <span class="px-2 py-0.5 rounded-lg bg-gray-100 text-gray-600 text-[10px] font-medium">
                                            <?= e($p['category']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3 text-right font-mono font-bold text-gray-900"><?= format_inr((float)$p['selling_price']) ?></td>
                                    <td class="py-3 text-center">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800">
                                            0 <?= e($p['unit']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3 text-center">
                                        <a href="products.php" class="px-3 py-1 rounded-lg bg-emerald-50 text-emerald-800 hover:bg-emerald-600 hover:text-white text-[11px] font-bold transition">
                                            Replenish Stock
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="py-8 text-center text-gray-400">
                                    No products are currently out of stock.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Tab 3: Low Stock -->
    <div x-show="activeTab === 'low'" x-cloak class="bg-white rounded-2xl border border-gray-200/80 shadow-2xs overflow-hidden">
        <div class="p-4 sm:p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-sm text-gray-800">Low Stock Early Warning List</h3>
                <span class="text-xs text-amber-600 font-bold"><?= $lowStockCount ?> items nearing empty</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead>
                        <tr class="text-gray-400 border-b border-gray-100">
                            <th class="pb-3">SKU</th>
                            <th class="pb-3">Product Name</th>
                            <th class="pb-3">Company</th>
                            <th class="pb-3 text-right">Selling Price</th>
                            <th class="pb-3 text-center">Remaining</th>
                            <th class="pb-3 text-center">Min Alert Level</th>
                            <th class="pb-3 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php if (!empty($lowStockProducts)): ?>
                            <?php foreach ($lowStockProducts as $p): ?>
                                <tr class="hover:bg-gray-50/60 transition">
                                    <td class="py-3 font-mono font-bold text-gray-700"><?= e($p['sku']) ?></td>
                                    <td class="py-3 font-bold text-gray-900"><?= e($p['name']) ?></td>
                                    <td class="py-3 text-gray-600"><?= e($p['company_name'] ?: '—') ?></td>
                                    <td class="py-3 text-right font-mono font-bold text-gray-900"><?= format_inr((float)$p['selling_price']) ?></td>
                                    <td class="py-3 text-center font-mono font-bold text-amber-700">
                                        <?= $p['stock_quantity'] ?> <?= e($p['unit']) ?>
                                    </td>
                                    <td class="py-3 text-center font-mono text-gray-500">
                                        <?= $p['min_alert_stock'] ?> <?= e($p['unit']) ?>
                                    </td>
                                    <td class="py-3 text-center">
                                        <a href="products.php" class="px-3 py-1 rounded-lg bg-amber-50 text-amber-800 hover:bg-amber-600 hover:text-white text-[11px] font-bold transition">
                                            + Add Units
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="py-8 text-center text-gray-400">
                                    All inventory levels are healthy.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

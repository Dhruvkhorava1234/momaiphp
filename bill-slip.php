<?php
/**
 * Official Full MOMAI PLYWOOD Invoice Slip
 * Exact Red Border & Typography Replication
 * MOMAI PLYWOOD - Core PHP
 */
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth_guard.php';

$billId = (int) ($_GET['id'] ?? 0);

if ($billId <= 0) {
    flash_set('error', 'Invalid Bill ID.');
    redirect('bills.php');
}

$pdo = get_db();

// Load Bill with Customer
$stmt = $pdo->prepare("SELECT b.*, c.phone as c_phone, c.address as c_address FROM bills b LEFT JOIN customers c ON b.customer_id = c.id WHERE b.id = ? AND b.deleted_at IS NULL");
$stmt->execute([$billId]);
$bill = $stmt->fetch();

if (!$bill) {
    flash_set('error', 'Bill not found.');
    redirect('bills.php');
}

// Load Items
$itemStmt = $pdo->prepare("SELECT * FROM bill_items WHERE bill_id = ? AND deleted_at IS NULL ORDER BY id ASC");
$itemStmt->execute([$billId]);
$items = $itemStmt->fetchAll();

// Amount in words
$amountInWords = convert_number_to_words((int) round((float)$bill['grand_total'])) . ' Only';

$pageTitle = "Invoice {$bill['bill_number']} - MOMAI PLYWOOD";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/topbar.php';
?>

<div class="max-w-4xl mx-auto space-y-6">

    <!-- Action Toolbar (Hidden during print) -->
    <div class="flex flex-wrap items-center justify-between gap-3 bg-white p-4 rounded-2xl border border-gray-200/80 shadow-2xs print:hidden">
        <div class="flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
            <span class="text-xs font-bold text-gray-700">Official Bill View</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $bill['payment_status'] === 'paid' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>">
                <?= ucfirst($bill['payment_status']) ?>
            </span>
        </div>

        <div class="flex items-center gap-2.5">
            <button type="button" onclick="window.print()"
                class="px-4 py-2 rounded-full bg-[#324b3e] hover:bg-[#23382f] text-white text-xs font-bold shadow-md transition flex items-center gap-1.5 active:scale-[0.98]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                <span>Print Slip</span>
            </button>

            <a href="bill-create.php"
                class="px-3.5 py-2 rounded-full bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-semibold border border-emerald-200 transition">
                + New Bill
            </a>

            <a href="bills.php"
                class="px-3.5 py-2 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold transition">
                All Bills History
            </a>
        </div>
    </div>

    <!-- Exact Physical MOMAI PLYWOOD Invoice Slip -->
    <div id="printable-slip"
        class="relative mx-auto bg-white border-2 border-[#b53127] rounded-sm p-4 sm:p-7 shadow-lg font-sans text-[#b53127] max-w-[800px] select-none">

        <!-- Top Row: Phone Number -->
        <div class="flex justify-end items-center relative z-10 text-xs font-bold tracking-wider mb-1">
            <span>MO. 91063 40961</span>
        </div>

        <!-- Header: MOMAI PLYWOOD -->
        <div class="relative z-10 pb-2">
            <div class="text-2xl sm:text-3xl font-extrabold tracking-widest uppercase leading-none font-serif text-[#b53127]">
                MOMAI
            </div>
            <div class="text-2xl sm:text-3xl font-extrabold tracking-widest uppercase leading-none font-serif text-[#b53127]">
                PLYWOOD
            </div>
        </div>

        <!-- Customer & Bill Info Grid Box -->
        <div class="border-t-2 border-b-2 border-[#b53127] my-2 text-xs">
            <div class="grid grid-cols-12">

                <!-- Left Section: NAME, ADDRESS, MO (8 cols) -->
                <div class="col-span-8 sm:col-span-9 pr-3 py-2 border-r-2 border-[#b53127] space-y-2.5">
                    <!-- NAME -->
                    <div class="flex items-end">
                        <span class="font-extrabold tracking-wider uppercase text-[11px] shrink-0 w-16">NAME :</span>
                        <div class="flex-1 border-b border-[#b53127] px-2 font-serif text-base font-bold text-gray-900 leading-tight">
                            <?= e($bill['customer_name']) ?>
                        </div>
                    </div>

                    <!-- ADDRESS -->
                    <div class="flex items-end">
                        <span class="font-extrabold tracking-wider uppercase text-[11px] shrink-0 w-16">ADDRESS :</span>
                        <div class="flex-1 border-b border-[#b53127] px-2 font-serif text-sm font-semibold text-gray-800 leading-tight">
                            <?= e($bill['customer_address'] ?: ($bill['c_address'] ?? '')) ?>
                        </div>
                    </div>

                    <!-- MO -->
                    <div class="flex items-end">
                        <span class="font-extrabold tracking-wider uppercase text-[11px] shrink-0 w-16">MO. :</span>
                        <div class="flex-1 border-b border-[#b53127] px-2 font-serif text-sm font-semibold text-gray-800 leading-tight">
                            <?= e($bill['customer_phone'] ?: ($bill['c_phone'] ?? '')) ?>
                        </div>
                    </div>
                </div>

                <!-- Right Section: BILL NO, DATE (4 cols) -->
                <div class="col-span-4 sm:col-span-3 pl-2 sm:pl-3 py-2 flex flex-col justify-between">
                    <!-- BILL NO -->
                    <div class="flex items-baseline gap-2 border-b border-[#b53127] pb-1">
                        <span class="font-extrabold tracking-wider uppercase text-[11px] shrink-0">BILL NO. :</span>
                        <span class="font-serif text-base font-black text-gray-900">
                            <?= preg_replace('/[^0-9]/', '', $bill['bill_number']) ?: $bill['id'] ?>
                        </span>
                    </div>

                    <!-- DATE -->
                    <div class="flex items-baseline gap-2 border-b border-[#b53127] pb-1">
                        <span class="font-extrabold tracking-wider uppercase text-[11px] shrink-0">DATE :</span>
                        <span class="font-serif text-sm font-bold text-gray-900">
                            <?= date('d-m-Y', strtotime($bill['created_at'])) ?>
                        </span>
                    </div>

                    <!-- TIME -->
                    <div class="flex items-baseline gap-2 pt-1 text-[10px] text-[#b53127]">
                        <span class="font-bold uppercase tracking-wider">TIME :</span>
                        <span class="font-mono"><?= date('h:i A', strtotime($bill['created_at'])) ?></span>
                    </div>
                </div>

            </div>
        </div>

        <!-- Table of Items (5 Standard Physical Bill Columns) -->
        <div class="border-b-2 border-[#b53127]">
            <table class="w-full text-xs text-left border-collapse">
                <thead>
                    <tr class="border-b-2 border-[#b53127] font-extrabold text-[11px] uppercase tracking-wider text-center">
                        <th class="py-1.5 px-2 border-r-2 border-[#b53127] w-12 text-center">NO.</th>
                        <th class="py-1.5 px-3 border-r-2 border-[#b53127] text-left">PARTICULARS</th>
                        <th class="py-1.5 px-2 border-r-2 border-[#b53127] w-16 text-center">QTY.</th>
                        <th class="py-1.5 px-2 border-r-2 border-[#b53127] w-24 text-right">RATE</th>
                        <th class="py-1.5 px-3 w-28 text-right">AMOUNT</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#b53127]/30 font-serif text-sm text-gray-900">
                    <?php 
                    $rowCount = max(count($items), 7);
                    for ($i = 0; $i < $rowCount; $i++): 
                        $item = $items[$i] ?? null;
                    ?>
                        <tr class="h-8">
                            <!-- NO -->
                            <td class="px-2 border-r-2 border-[#b53127] text-center font-bold text-xs text-[#b53127]">
                                <?= $item ? ($i + 1) : '' ?>
                            </td>

                            <!-- PARTICULARS -->
                            <td class="px-3 border-r-2 border-[#b53127] font-semibold">
                                <?= $item ? e($item['product_name']) : '' ?>
                            </td>

                            <!-- QTY -->
                            <td class="px-2 border-r-2 border-[#b53127] text-center font-bold">
                                <?= $item ? $item['quantity'] : '' ?>
                            </td>

                            <!-- RATE -->
                            <td class="px-2 border-r-2 border-[#b53127] text-right font-mono font-medium">
                                <?= $item ? number_format((float)$item['unit_price'], 2) : '' ?>
                            </td>

                            <!-- AMOUNT -->
                            <td class="px-3 text-right font-mono font-bold">
                                <?= $item ? number_format((float)$item['total_price'], 2) : '' ?>
                            </td>
                        </tr>
                    <?php endfor; ?>
                </tbody>

                <!-- Totals & In-Words Footer -->
                <tfoot>
                    <tr class="border-t-2 border-[#b53127] text-xs">
                        <td colspan="3" class="px-3 py-2 border-r-2 border-[#b53127] align-top">
                            <div class="text-[10px] font-extrabold uppercase tracking-wider text-[#b53127]">
                                RUPEES IN WORDS:
                            </div>
                            <div class="font-serif italic font-bold text-gray-900 text-xs mt-0.5">
                                <?= e($amountInWords) ?>
                            </div>

                            <?php if ((float)$bill['discount'] > 0): ?>
                                <div class="mt-1 text-[11px] text-[#b53127] font-semibold">
                                    Special Discount Applied: ₹<?= number_format((float)$bill['discount'], 2) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="px-2 py-2 border-r-2 border-[#b53127] text-right font-extrabold text-[11px] uppercase tracking-wider text-[#b53127] align-middle">
                            TOTAL
                        </td>
                        <td class="px-3 py-2 text-right font-mono font-black text-base text-[#b53127] align-middle">
                            ₹<?= number_format((float)$bill['grand_total'], 2) ?>
                        </td>
                    </tr>
                    
                    <?php if ((float)$bill['due_amount'] > 0): ?>
                        <tr class="border-t border-[#b53127]/40 text-xs">
                            <td colspan="3" class="px-3 py-1 border-r-2 border-[#b53127] text-gray-500 italic text-[11px]">
                                Payment Status: Partial Due (Paid: ₹<?= number_format((float)$bill['paid_amount'], 2) ?>)
                            </td>
                            <td class="px-2 py-1 border-r-2 border-[#b53127] text-right font-extrabold text-[10px] text-rose-700 uppercase">
                                BALANCE DUE
                            </td>
                            <td class="px-3 py-1 text-right font-mono font-bold text-sm text-rose-700">
                                ₹<?= number_format((float)$bill['due_amount'], 2) ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tfoot>
            </table>
        </div>

        <!-- Bottom Terms & Signatures -->
        <div class="mt-4 pt-2 flex items-end justify-between text-xs text-[#b53127]">
            <div class="space-y-0.5 text-[10px]">
                <div class="font-bold uppercase tracking-wider">Terms & Conditions:</div>
                <div class="italic text-gray-700">1. Goods once sold will not be taken back or exchanged.</div>
                <div class="italic text-gray-700">2. Subject to Porbandar jurisdiction.</div>
            </div>

            <div class="text-center pr-4">
                <div class="h-10"></div>
                <div class="border-t border-[#b53127] px-6 pt-1 font-bold tracking-wider text-[11px] uppercase">
                    For, MOMAI PLYWOOD
                </div>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

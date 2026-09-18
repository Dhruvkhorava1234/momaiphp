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

// WhatsApp Message Text
$waPhone = preg_replace('/[^0-9]/', '', $bill['customer_phone'] ?? '');
if ($waPhone && strlen($waPhone) === 10) {
    $waPhone = '91' . $waPhone; // India country code
}
$waDueNote = (float)$bill['due_amount'] > 0
    ? "\n⚠️ Outstanding Balance: ₹" . number_format((float)$bill['due_amount'], 2) . " pending."
    : "\n✅ Payment fully cleared. Thank you!"
;
$waMessage  = "🪵 *MOMAI PLYWOOD* - Invoice Receipt\n";
$waMessage .= "━━━━━━━━━━━━━━━━━━━━━\n";
$waMessage .= "📄 Bill No: *{$bill['bill_number']}*\n";
$waMessage .= "👤 Customer: {$bill['customer_name']}\n";
$waMessage .= "📅 Date: " . date('d-m-Y', strtotime($bill['created_at'])) . "\n";
$waMessage .= "━━━━━━━━━━━━━━━━━━━━━\n";
foreach ($items as $item) {
    $waMessage .= "  • {$item['product_name']} x{$item['quantity']} @ ₹{$item['unit_price']} = ₹{$item['total_price']}\n";
}
$waMessage .= "━━━━━━━━━━━━━━━━━━━━━\n";
$waMessage .= "💰 Grand Total: *₹" . number_format((float)$bill['grand_total'], 2) . "*\n";
$waMessage .= "✔️ Amount Paid: ₹" . number_format((float)$bill['paid_amount'], 2) . "\n";
$waMessage .= $waDueNote . "\n";
$waMessage .= "━━━━━━━━━━━━━━━━━━━━━\n";
$waMessage .= "Thank you for choosing MOMAI PLYWOOD! 🙏";
$waUrl = 'https://wa.me/' . ($waPhone ?: '') . '?text=' . rawurlencode($waMessage);

$pageTitle = "Invoice {$bill['bill_number']} - MOMAI PLYWOOD";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/topbar.php';
?>

<div x-data="{ deleteModal: false, isDeleting: false }" class="max-w-4xl mx-auto space-y-6">

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

            <!-- WhatsApp Share as Image Button -->
            <button type="button"
               id="btn-wa-share-slip"
               onclick="shareSlipImage('printable-slip', '<?= e($bill['bill_number']) ?>', '<?= rawurlencode($bill['customer_phone'] ?? '') ?>')"
               class="px-3.5 py-2 rounded-full bg-[#25d366]/10 hover:bg-[#25d366] text-[#128c4c] hover:text-white text-xs font-semibold border border-[#25d366]/40 transition flex items-center gap-1.5 active:scale-[0.98]"
               title="Share invoice image on WhatsApp">
                <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                </svg>
                <span id="btn-wa-share-slip-text">Share on WhatsApp</span>
            </button>

            <a href="bill-create.php"
                class="px-3.5 py-2 rounded-full bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-semibold border border-emerald-200 transition">
                + New Bill
            </a>

            <a href="bills.php"
                class="px-3.5 py-2 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold transition">
                All Bills History
            </a>

            <button type="button"
                @click="deleteModal = true"
                class="btn-delete-animated px-3.5 py-2 rounded-full bg-rose-50 hover:bg-rose-600 hover:text-white text-rose-700 text-xs font-semibold border border-rose-200 transition flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                <span>Delete Bill</span>
            </button>
            <form id="delete-bill-form" method="POST" action="bill-delete.php" class="hidden">
                <?= csrf_field() ?>
                <input type="hidden" name="bill_id" value="<?= $bill['id'] ?>">
            </form>
        </div>
    </div>

    <!-- Exact Physical MOMAI PLYWOOD Invoice Slip -->
    <div id="printable-slip"
        class="relative mx-auto bg-white border-2 border-[#b53127] rounded-sm p-4 sm:p-7 shadow-lg font-sans text-[#b53127] max-w-[800px] select-none">

        <!-- Top Row: Phone Number -->
        <!-- <div class="flex justify-end items-center relative z-10 text-xs font-bold tracking-wider mb-1">
            <span>MO. 91063 40961</span>
        </div> -->

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
                <div class="col-span-8 sm:col-span-9 pr-3 py-2 border-r-2 border-[#b53127] space-y-0">
                    <!-- NAME -->
                    <div class="flex items-center border-b border-[#b53127]/30 py-1.5 min-h-[28px]">
                        <span class="font-extrabold tracking-wider uppercase text-[11px] shrink-0 w-[72px]">NAME :</span>
                        <span class="flex-1 px-2 font-serif text-sm text-gray-900 leading-tight truncate"><?= e($bill['customer_name']) ?></span>
                    </div>

                    <!-- ADDRESS -->
                    <div class="flex items-center border-b border-[#b53127]/30 py-1.5 min-h-[28px]">
                        <span class="font-extrabold tracking-wider uppercase text-[11px] shrink-0 w-[72px]">ADDRESS :</span>
                        <span class="flex-1 px-2 font-serif text-sm text-gray-800 leading-tight"><?= e($bill['customer_address'] ?: ($bill['c_address'] ?? '')) ?></span>
                    </div>

                    <!-- MO -->
                    <div class="flex items-center py-1.5 min-h-[28px]">
                        <span class="font-extrabold tracking-wider uppercase text-[11px] shrink-0 w-[72px]">MO. :</span>
                        <span class="flex-1 px-2 font-serif text-sm text-gray-800 leading-tight"><?= e($bill['customer_phone'] ?: ($bill['c_phone'] ?? '')) ?></span>
                    </div>
                </div>

                <!-- Right Section: BILL NO, DATE, TIME (4 cols) -->
                <div class="col-span-4 sm:col-span-3 pl-2 sm:pl-3 py-2 space-y-0">
                    <!-- BILL NO -->
                    <div class="flex items-center border-b border-[#b53127]/30 py-1.5 min-h-[28px]">
                        <span class="font-extrabold tracking-wider uppercase text-[10px] shrink-0 whitespace-nowrap">BILL NO. :</span>
                        <span class="ml-1.5 font-serif text-sm font-black text-gray-900 leading-tight"><?= preg_replace('/[^0-9]/', '', $bill['bill_number']) ?: $bill['id'] ?></span>
                    </div>

                    <!-- DATE -->
                    <div class="flex items-center border-b border-[#b53127]/30 py-1.5 min-h-[28px]">
                        <span class="font-extrabold tracking-wider uppercase text-[10px] shrink-0 whitespace-nowrap">DATE :</span>
                        <span class="ml-1.5 font-serif text-sm text-gray-900 leading-tight"><?= date('d-m-Y', strtotime($bill['created_at'])) ?></span>
                    </div>

                    <!-- TIME -->
                    <div class="flex items-center py-1.5 min-h-[28px]">
                        <span class="font-extrabold tracking-wider uppercase text-[10px] shrink-0 whitespace-nowrap">TIME :</span>
                        <span class="ml-1.5 font-mono text-[11px] text-gray-900 leading-tight"><?= date('h:i A', strtotime($bill['created_at'])) ?></span>
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
            <!-- <div class="space-y-0.5 text-[10px]">
                <div class="font-bold uppercase tracking-wider">Terms & Conditions:</div>
                <div class="italic text-gray-700">1. Goods once sold will not be taken back or exchanged.</div>
                <div class="italic text-gray-700">2. Subject to Porbandar jurisdiction.</div>
            </div> -->

            <div class="text-center pr-4">
                <!-- Signature space -->
                <div class="h-14"></div>
                <div class="border-t border-[#b53127] px-6 pt-1 font-bold tracking-wider text-[11px] uppercase">
                    For, MOMAI PLYWOOD
                </div>
            </div>
        </div>

    </div>

    <!-- MODAL: Botanical Soft Delete Confirmation -->
    <div x-show="deleteModal" x-cloak 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs transition-opacity duration-300 print:hidden">
        <div @click.away="if (!isDeleting) deleteModal = false"
             x-show="deleteModal"
             x-transition:enter="transition ease-out duration-300 transform"
             x-transition:enter-start="opacity-0 scale-95 translate-y-4"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200 transform"
             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-95 translate-y-4"
             class="bg-white rounded-3xl p-6 sm:p-7 max-w-md w-full shadow-2xl border border-gray-100 space-y-5 text-center relative overflow-hidden">
            
            <div class="mx-auto w-16 h-16 rounded-2xl bg-rose-50 border border-rose-100 flex items-center justify-center text-rose-600 shadow-inner">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
            </div>

            <div>
                <h3 class="text-lg font-bold text-gray-900 tracking-tight">
                    Delete Bill <?= e($bill['bill_number']) ?>?
                </h3>
                <p class="text-xs text-gray-500 mt-1.5 leading-relaxed">
                    Are you sure you want to delete this invoice?
                </p>
            </div>

            <div class="p-3.5 rounded-2xl bg-amber-50/70 border border-amber-200/80 text-left space-y-1.5 text-xs text-amber-900">
                <div class="flex items-center gap-2 font-bold text-amber-800">
                    <svg class="w-4 h-4 shrink-0 text-amber-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    <span>Automatic Adjustments:</span>
                </div>
                <ul class="list-disc list-inside space-y-1 text-[11px] text-amber-800/90 pl-1">
                    <li>Product inventory quantities will be restored.</li>
                    <li>Customer Khata outstanding balance will be reduced.</li>
                    <li>The record is archived via soft-delete.</li>
                </ul>
            </div>

            <div class="grid grid-cols-2 gap-3 pt-2">
                <button type="button" 
                        @click="deleteModal = false" 
                        :disabled="isDeleting"
                        class="w-full py-2.5 px-4 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold transition disabled:opacity-50">
                    Cancel
                </button>
                <button type="button"
                        @click="isDeleting = true; $('#printable-slip').addClass('animating-delete'); setTimeout(() => $('#delete-bill-form').submit(), 550)"
                        :disabled="isDeleting"
                        class="w-full py-2.5 px-4 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold shadow-md shadow-rose-200 transition flex items-center justify-center gap-1.5 active:scale-[0.98] disabled:opacity-50">
                    <template x-if="!isDeleting">
                        <span class="flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            <span>Yes, Delete</span>
                        </span>
                    </template>
                    <template x-if="isDeleting">
                        <span class="flex items-center gap-1.5">
                            <svg class="animate-spin w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>Deleting...</span>
                        </span>
                    </template>
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
/**
 * Captures a slip element as a PNG image and shares it via
 * the Web Share API (mobile) or downloads it (desktop fallback).
 *
 * @param {string} elementId   - ID of the DOM element to capture
 * @param {string} billNumber  - Bill number for the filename
 * @param {string} phone       - URL-encoded phone number (optional)
 */
async function shareSlipImage(elementId, billNumber, phone) {
    const btn = document.getElementById('btn-wa-share-' + elementId.replace('printable-', ''));
    const btnText = document.getElementById('btn-wa-share-' + elementId.replace('printable-', '') + '-text');
    if (!btn) return;

    const originalText = btnText ? btnText.textContent : 'Share on WhatsApp';
    if (btnText) btnText.textContent = 'Capturing...';
    btn.disabled = true;

    try {
        const element = document.getElementById(elementId);
        if (!element) throw new Error('Slip element not found.');

        // Capture the element as a canvas
        const canvas = await html2canvas(element, {
            scale: 2,              // 2× resolution for crisp image
            useCORS: true,
            backgroundColor: '#ffffff',
            logging: false
        });

        // Convert canvas → Blob (PNG)
        const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'));
        const filename = 'MOMAI_PLYWOOD_' + (billNumber || 'Bill') + '.png';
        const file = new File([blob], filename, { type: 'image/png' });

        // Mobile: use Web Share API with files
        if (navigator.canShare && navigator.canShare({ files: [file] })) {
            await navigator.share({
                files: [file],
                title: 'MOMAI PLYWOOD - Bill ' + (billNumber || ''),
                text: 'Please find the attached invoice from MOMAI PLYWOOD.'
            });
        } else {
            // Desktop fallback: download the image, then open WhatsApp
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            a.click();
            URL.revokeObjectURL(url);

            // Open WhatsApp chat after short delay
            setTimeout(() => {
                const decodedPhone = decodeURIComponent(phone || '');
                const cleanPhone = decodedPhone.replace(/[^0-9]/g, '');
                const waPhone = cleanPhone.length === 10 ? '91' + cleanPhone : cleanPhone;
                const waNote = encodeURIComponent('Please find the attached invoice image from MOMAI PLYWOOD.');
                window.open('https://wa.me/' + (waPhone || '') + '?text=' + waNote, '_blank');
            }, 600);
        }
    } catch (err) {
        if (err.name !== 'AbortError') {
            console.error('WhatsApp share error:', err);
            alert('Could not share the slip. Please try printing it instead.');
        }
    } finally {
        if (btnText) btnText.textContent = originalText;
        btn.disabled = false;
    }
}
</script>

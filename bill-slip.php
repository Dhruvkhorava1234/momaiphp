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

// Load Returns History (if any)
$returnStmt = $pdo->prepare("SELECT * FROM bill_returns WHERE bill_id = ? ORDER BY id DESC");
$returnStmt->execute([$billId]);
$returnsList = $returnStmt->fetchAll();

$totalReturnedAmount = 0.0;
$totalReturnedQty = 0;
foreach ($returnsList as $ret) {
    $totalReturnedAmount += (float) $ret['total_refund'];
    $totalReturnedQty += (int) $ret['quantity'];
}
$hasReturns = count($returnsList) > 0;

$returnableItems = [];
foreach ($items as $it) {
    $alreadyRet = (int) ($it['returned_quantity'] ?? 0);
    $avail = (int) $it['quantity'] - $alreadyRet;
    if ($avail > 0) {
        $returnableItems[] = [
            'id' => (int) $it['id'],
            'product_name' => $it['product_name'],
            'unit_price' => (float) $it['unit_price'],
            'quantity' => (int) $it['quantity'],
            'returned_quantity' => $alreadyRet,
            'available_qty' => $avail
        ];
    }
}
$canReturnAny = count($returnableItems) > 0;

// Amount in words (Strictly Original Full Bill Total - Untouched by returns)
$originalSubtotal = 0.0;
foreach ($items as $it) {
    $originalSubtotal += (int) $it['quantity'] * (float) $it['unit_price'];
}
$originalDiscount = (float) ($bill['discount'] ?? 0);
$originalGrandTotal = max(0, $originalSubtotal - $originalDiscount);
$amountInWords = convert_number_to_words((int) round($originalGrandTotal)) . ' Only';

// WhatsApp Message Text
$waPhone = preg_replace('/[^0-9]/', '', $bill['customer_phone'] ?? '');
if ($waPhone && strlen($waPhone) === 10) {
    $waPhone = '91' . $waPhone; // India country code
}
$waDueNote = (float) $bill['due_amount'] > 0
    ? "\n⚠️ Outstanding Balance: ₹" . number_format((float) $bill['due_amount'], 2) . " pending."
    : "\n✅ Payment fully cleared. Thank you!"
;
$waMessage = "🪵 *MOMAI PLYWOOD* - Invoice Receipt\n";
$waMessage .= "━━━━━━━━━━━━━━━━━━━━━\n";
$waMessage .= "📄 Bill No: *{$bill['bill_number']}*\n";
$waMessage .= "👤 Customer: {$bill['customer_name']}\n";
$waMessage .= "📅 Date: " . date('d-m-Y', strtotime($bill['created_at'])) . "\n";
$waMessage .= "━━━━━━━━━━━━━━━━━━━━━\n";
foreach ($items as $item) {
    $waMessage .= "  • {$item['product_name']} x{$item['quantity']} @ ₹" . number_format((float) $item['unit_price'], 2) . " = ₹" . number_format((int) $item['quantity'] * (float) $item['unit_price'], 2) . "\n";
}
if ($originalDiscount > 0) {
    $waMessage .= "🏷️ Discount: -₹" . number_format($originalDiscount, 2) . "\n";
}
$waMessage .= "━━━━━━━━━━━━━━━━━━━━━\n";
$waMessage .= "💰 Total: *₹" . number_format($originalGrandTotal, 2) . "*\n";
$waMessage .= "✔️ Amount Paid: ₹" . number_format((float) $bill['paid_amount'], 2) . "\n";
$waMessage .= $waDueNote . "\n";
$waMessage .= "━━━━━━━━━━━━━━━━━━━━━\n";
$waMessage .= "Thank you for choosing MOMAI PLYWOOD! 🙏";
$waUrl = 'https://wa.me/' . ($waPhone ?: '') . '?text=' . rawurlencode($waMessage);

$pageTitle = "Invoice {$bill['bill_number']} - MOMAI PLYWOOD";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/topbar.php';
?>

<script>
    const billReturnableItems = <?= json_encode($returnableItems, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

    function billSlipManager() {
        return {
            deleteModal: false, 
            isDeleting: false,
            returnModal: <?= (($_GET['action'] ?? '') === 'return' && $canReturnAny) ? 'true' : 'false' ?>,
            isSubmittingReturn: false,
            quantities: {},
            refundType: '<?= ((float) $bill['due_amount'] > 0) ? 'due_deduct' : 'cash_refund' ?>',
            reason: '',
            init() {
                if (Array.isArray(billReturnableItems)) {
                    billReturnableItems.forEach(it => {
                        this.quantities[it.id] = 0;
                    });
                }
            },
            calculateTotal() {
                let tot = 0;
                if (Array.isArray(billReturnableItems)) {
                    billReturnableItems.forEach(it => {
                        let q = parseInt(this.quantities[it.id]) || 0;
                        if (q > 0) {
                            tot += q * parseFloat(it.unit_price);
                        }
                    });
                }
                return tot;
            },
            setQty(id, delta, max) {
                let cur = (parseInt(this.quantities[id]) || 0) + delta;
                if (cur < 0) cur = 0;
                if (cur > max) cur = max;
                this.quantities[id] = cur;
            }
        };
    }
</script>

<div x-data="billSlipManager()" class="max-w-4xl mx-auto space-y-6">

    <!-- Action Toolbar (Hidden during print) -->
    <div class="bg-white p-3 sm:p-4 rounded-2xl border border-gray-200/80 shadow-2xs print:hidden space-y-2.5">
        <!-- Label row -->
        <div class="flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 shrink-0"></span>
            <span class="text-xs font-bold text-gray-700">Official Bill View</span>
            <?php if ($hasReturns): ?>
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-900 border border-amber-300">
                    Return Bill
                </span>
            <?php endif; ?>
            <span
                class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $bill['payment_status'] === 'paid' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>">
                <?= ucfirst($bill['payment_status']) ?>
            </span>
        </div>

        <!-- Buttons row — horizontally scrollable on mobile -->
        <div class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none">
            <button type="button" onclick="window.print()"
                class="shrink-0 px-4 py-2 rounded-full bg-[#324b3e] hover:bg-[#23382f] text-white text-xs font-bold shadow-md transition flex items-center gap-1.5 active:scale-[0.98]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                <span>Print Slip</span>
            </button>

            <!-- WhatsApp Share as Image Button -->
            <button type="button" id="btn-wa-share-slip"
                onclick="shareSlipImage('printable-slip', '<?= e($bill['bill_number']) ?>', '<?= rawurlencode($bill['customer_phone'] ?? '') ?>')"
                class="shrink-0 px-3.5 py-2 rounded-full bg-[#25d366]/10 hover:bg-[#25d366] text-[#128c4c] hover:text-white text-xs font-semibold border border-[#25d366]/40 transition flex items-center gap-1.5 active:scale-[0.98]"
                title="Share invoice image on WhatsApp">
                <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 24 24" fill="currentColor">
                    <path
                        d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z" />
                </svg>
                <span id="btn-wa-share-slip-text">Share on WhatsApp</span>
            </button>

            <!-- Copy Slip Image Button -->
            <button type="button" id="btn-copy-slip-img"
                onclick="copySlipImage('printable-slip', this)"
                class="shrink-0 px-3.5 py-2 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold transition flex items-center gap-1.5 active:scale-[0.98]"
                title="Copy slip image to clipboard to paste in WhatsApp">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                </svg>
                <span id="btn-copy-slip-img-text">Copy Image</span>
            </button>

            <!-- Return Items Button -->
            <?php if ($canReturnAny): ?>
                <button type="button" @click="returnModal = true"
                    class="shrink-0 px-3.5 py-2 rounded-full bg-amber-500/15 hover:bg-amber-600 hover:text-white text-amber-900 text-xs font-bold border border-amber-400/60 transition flex items-center gap-1.5 active:scale-[0.98] shadow-2xs"
                    title="Return items from this bill">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                    </svg>
                    <span>Return Items</span>
                </button>
            <?php else: ?>
                <span class="shrink-0 px-3 py-1.5 rounded-full bg-gray-100 text-gray-500 text-[11px] font-medium border border-gray-200">
                    All Items Returned
                </span>
            <?php endif; ?>

            <!-- View Return Bill Button (If bill has returns) -->
            <?php if ($hasReturns): ?>
                <a href="return-slip.php?bill_id=<?= $bill['id'] ?>"
                    class="shrink-0 px-3.5 py-2 rounded-full bg-rose-50 hover:bg-rose-600 hover:text-white text-rose-800 text-xs font-bold border border-rose-300 transition flex items-center gap-1.5 active:scale-[0.98] shadow-2xs"
                    title="Open dedicated Return Bill Slip for this bill">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                    </svg>
                    <span>View Return Bill</span>
                </a>
            <?php endif; ?>

            <a href="bill-create.php"
                class="shrink-0 px-3.5 py-2 rounded-full bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-semibold border border-emerald-200 transition">
                + New Bill
            </a>

            <a href="bills.php"
                class="shrink-0 px-3.5 py-2 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold transition">
                All Bills History
            </a>

            <a href="bill-returns.php"
                class="shrink-0 px-3.5 py-2 rounded-full bg-amber-50 hover:bg-amber-100 text-amber-900 text-xs font-semibold border border-amber-200 transition">
                Return Bills
            </a>

            <button type="button" @click="deleteModal = true"
                class="shrink-0 btn-delete-animated px-3.5 py-2 rounded-full bg-rose-50 hover:bg-rose-600 hover:text-white text-rose-700 text-xs font-semibold border border-rose-200 transition flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
                <span>Delete Bill</span>
            </button>
            <form id="delete-bill-form" method="POST" action="bill-delete.php" class="hidden">
                <?= csrf_field() ?>
                <input type="hidden" name="bill_id" value="<?= $bill['id'] ?>">
            </form>
        </div>
    </div>

    <!-- Slip Container (Horizontally scrollable on small mobile screens to keep layout intact) -->
    <div class="w-full overflow-x-auto pb-4 -mx-2 px-2 sm:mx-0 sm:px-0">
        <!-- Exact Physical MOMAI PLYWOOD Invoice Slip -->
        <div id="printable-slip"
            class="relative mx-auto bg-white border-2 border-[#b53127] rounded-sm p-4 sm:p-7 shadow-lg font-sans text-[#b53127] select-none"
            style="width: 100%; max-width: 800px; min-width: 720px; box-sizing: border-box;">

            <!-- Header: MOMAI PLYWOOD -->
            <div class="relative z-10 pb-2">
                <div class="text-2xl sm:text-3xl font-extrabold tracking-widest uppercase leading-none font-serif whitespace-nowrap"
                    style="color:#b53127;">
                    MOMAI
                </div>
                <div class="text-2xl sm:text-3xl font-extrabold tracking-widest uppercase leading-none font-serif whitespace-nowrap"
                    style="color:#b53127;">
                    PLYWOOD
                </div>
            </div>

            <!-- Customer & Bill Info Grid Box -->
            <div class="border-t-2 border-b-2 border-[#b53127] my-2 text-xs">
                <div class="flex flex-row">

                    <!-- Left Section: NAME, ADDRESS, MO -->
                    <div class="flex-1 pr-3 py-2 border-r-2 border-[#b53127] space-y-0 min-w-0">
                        <!-- NAME -->
                        <div class="flex items-start py-1 min-h-[26px]">
                            <span class="font-extrabold tracking-wider uppercase text-[11px] shrink-0 w-[75px]">NAME
                                :</span>
                            <span class="flex-1 px-2 font-serif text-sm text-gray-900 leading-tight break-words"
                                style="overflow-wrap: anywhere; word-break: break-word;"><?= e($bill['customer_name']) ?></span>
                        </div>

                        <!-- ADDRESS -->
                        <div class="flex items-start py-1 min-h-[26px]">
                            <span class="font-extrabold tracking-wider uppercase text-[11px] shrink-0 w-[75px]">ADDRESS
                                :</span>
                            <span class="flex-1 px-2 font-serif text-sm text-gray-800 leading-tight break-words"
                                style="overflow-wrap: anywhere; word-break: break-word;"><?= e($bill['customer_address'] ?: ($bill['c_address'] ?? '')) ?></span>
                        </div>

                        <!-- MO -->
                        <div class="flex items-center py-1 min-h-[26px]">
                            <span class="font-extrabold tracking-wider uppercase text-[11px] shrink-0 w-[75px]">MO. :</span>
                            <span
                                class="flex-1 px-2 font-serif text-sm text-gray-800 leading-tight"><?= e($bill['customer_phone'] ?: ($bill['c_phone'] ?? '')) ?></span>
                        </div>
                    </div>

                    <!-- Right Section: BILL NO, DATE, TIME (Matches exact width of QTY+RATE+AMOUNT columns = 292px) -->
                    <div class="pl-3 py-2 space-y-0" style="width: 292px; flex-shrink: 0;">
                        <!-- BILL NO -->
                        <div class="flex items-center py-1 min-h-[26px]">
                            <span
                                class="font-extrabold tracking-wider uppercase text-[10px] shrink-0 w-[75px]">BILL
                                NO. :</span>
                            <span
                                class="ml-1 font-serif text-sm font-black text-gray-900 leading-tight"><?= preg_replace('/[^0-9]/', '', $bill['bill_number']) ?: $bill['id'] ?></span>
                        </div>

                        <!-- DATE -->
                        <div class="flex items-center py-1 min-h-[26px]">
                            <span
                                class="font-extrabold tracking-wider uppercase text-[10px] shrink-0 w-[75px]">DATE
                                :</span>
                            <span
                                class="ml-1 font-serif text-sm text-gray-900 leading-tight"><?= date('d-m-Y', strtotime($bill['created_at'])) ?></span>
                        </div>

                        <!-- TIME -->
                        <div class="flex items-center py-1 min-h-[26px]">
                            <span
                                class="font-extrabold tracking-wider uppercase text-[10px] shrink-0 w-[75px]">TIME
                                :</span>
                            <span
                                class="ml-1 font-mono text-[11px] text-gray-900 leading-tight"><?= date('h:i A', strtotime($bill['created_at'])) ?></span>
                        </div>

                    </div>

                </div>
            </div>

            <!-- Table of Items (5 Standard Physical Bill Columns) -->
            <div class="border-b-2 border-[#b53127]">
                <table class="w-full text-xs text-left border-collapse" style="table-layout: fixed; width: 100%;">
                    <colgroup>
                        <col style="width: 48px;">
                        <col style="width: auto;">
                        <col style="width: 68px;">
                        <col style="width: 100px;">
                        <col style="width: 124px;">
                    </colgroup>
                    <thead>
                        <tr
                            class="border-b-2 border-[#b53127] font-extrabold text-[11px] uppercase tracking-wider text-center">
                            <th style="width: 48px;" class="py-1.5 px-2 border-r-2 border-[#b53127] text-center">NO.</th>
                            <th class="py-1.5 px-3 border-r-2 border-[#b53127] text-left">PARTICULARS</th>
                            <th style="width: 68px;" class="py-1.5 px-2 border-r-2 border-[#b53127] text-center">QTY.</th>
                            <th style="width: 100px;" class="py-1.5 px-2 border-r-2 border-[#b53127] text-right">RATE</th>
                            <th style="width: 124px;" class="py-1.5 px-3 text-right">AMOUNT</th>
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
                                <td style="width: 48px;" class="px-2 border-r-2 border-[#b53127] text-center text-xs text-[#b53127]">
                                    <?= $item ? ($i + 1) : '' ?>
                                </td>

                                <!-- PARTICULARS -->
                                <td class="px-3 border-r-2 border-[#b53127] break-words leading-tight"
                                    style="overflow-wrap: anywhere; word-break: break-word;">
                                    <?= $item ? e($item['product_name']) : '' ?>
                                </td>

                                <!-- QTY -->
                                <td style="width: 68px;" class="px-2 border-r-2 border-[#b53127] text-center">
                                    <?= $item ? (int) $item['quantity'] : '' ?>
                                </td>

                                <!-- RATE -->
                                <td style="width: 100px;" class="px-2 border-r-2 border-[#b53127] text-right font-mono font-medium">
                                    <?= $item ? number_format((float) $item['unit_price'], 2) : '' ?>
                                </td>

                                <!-- AMOUNT -->
                                <td style="width: 124px;" class="px-3 text-right font-mono">
                                    <?= $item ? number_format((float) ((int) $item['quantity'] * (float) $item['unit_price']), 2) : '' ?>
                                </td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>

                    <!-- Totals & In-Words Footer (Original Full Bill Values) -->
                    <tfoot>
                        <tr class="border-t-2 border-[#b53127] text-xs">
                            <td colspan="3" class="px-3 py-2 border-r-2 border-[#b53127] align-top">
                                <div class="text-[10px] font-extrabold uppercase tracking-wider text-[#b53127]">
                                    RUPEES IN WORDS:
                                </div>
                                <div class="font-serif italic font-bold text-gray-900 text-xs mt-0.5 break-words"
                                    style="overflow-wrap: anywhere; word-break: break-word;">
                                    <?= e($amountInWords) ?>
                                </div>

                                <?php if ($originalDiscount > 0): ?>
                                    <div class="mt-1 text-[11px] text-[#b53127] font-semibold">
                                        Special Discount Applied: ₹<?= number_format($originalDiscount, 2) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td colspan="2" class="px-3 py-2 text-right align-middle">
                                <div class="text-[11px] font-extrabold uppercase tracking-wider text-[#b53127]">
                                    TOTAL
                                </div>
                                <div class="font-mono font-black text-base text-[#b53127] leading-tight mt-0.5">
                                    ₹<?= number_format($originalGrandTotal, 2) ?>
                                </div>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Bottom Terms & Signatures -->
            <div class="mt-4 pt-2 flex items-end justify-end text-xs text-[#b53127]">
                <div class="text-center ml-auto pl-4">
                    <!-- Signature space -->
                    <div class="h-14"></div>
                    <div class="border-t border-[#b53127] px-6 pt-1 font-bold tracking-wider text-[11px] uppercase">
                        For, MOMAI PLYWOOD
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Returns History Card (Hidden during print) -->
    <?php if ($hasReturns): ?>
        <div class="bg-white rounded-2xl border border-amber-200/80 shadow-2xs p-4 sm:p-5 print:hidden space-y-3">
            <div class="flex items-center justify-between border-b border-gray-100 pb-2.5">
                <div class="flex items-center gap-2">
                    <span class="p-1.5 rounded-lg bg-amber-100 text-amber-800">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                        </svg>
                    </span>
                    <h4 class="text-sm font-bold text-gray-900">
                        Sales Return History
                    </h4>
                </div>
                <span class="text-xs font-bold text-rose-700 bg-rose-50 px-2.5 py-1 rounded-full border border-rose-200">
                    Total Refund Deducted: ₹<?= number_format($totalReturnedAmount, 2) ?>
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-gray-100 text-[11px] font-bold text-gray-500 uppercase tracking-wider">
                            <th class="py-2 px-2">Date & Time</th>
                            <th class="py-2 px-3">Product Name</th>
                            <th class="py-2 px-2 text-center">Returned Qty</th>
                            <th class="py-2 px-2 text-right">Rate</th>
                            <th class="py-2 px-3 text-right">Refund Amount</th>
                            <th class="py-2 px-3">Method & Reason</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 text-gray-800">
                        <?php foreach ($returnsList as $ret): ?>
                            <tr class="hover:bg-amber-50/40 transition">
                                <td class="py-2.5 px-2 font-mono text-[11px] text-gray-500 whitespace-nowrap">
                                    <?= date('d M Y, h:i A', strtotime($ret['created_at'])) ?>
                                </td>
                                <td class="py-2.5 px-3 font-semibold text-gray-900">
                                    <?= e($ret['product_name']) ?>
                                </td>
                                <td class="py-2.5 px-2 text-center font-bold text-rose-600">
                                    <?= (int) $ret['quantity'] ?>
                                </td>
                                <td class="py-2.5 px-2 text-right font-mono">
                                    ₹<?= number_format((float) $ret['unit_price'], 2) ?>
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono font-bold text-rose-700">
                                    -₹<?= number_format((float) $ret['total_refund'], 2) ?>
                                </td>
                                <td class="py-2.5 px-3">
                                    <div class="flex items-center gap-1.5">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $ret['refund_type'] === 'cash_refund' ? 'bg-blue-100 text-blue-800' : 'bg-amber-100 text-amber-900' ?>">
                                            <?= $ret['refund_type'] === 'cash_refund' ? 'Cash Refund' : 'Deducted from Due' ?>
                                        </span>
                                        <?php if (!empty($ret['reason'])): ?>
                                            <span class="text-gray-500 italic text-[11px] truncate max-w-[180px]" title="<?= e($ret['reason']) ?>">
                                                <?= e($ret['reason']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="py-2.5 px-3 text-right">
                                    <a href="return-slip.php?id=<?= $ret['id'] ?>"
                                       class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-bold bg-rose-50 text-rose-800 border border-rose-200 hover:bg-rose-600 hover:text-white transition"
                                       title="Open dedicated Return Bill Slip">
                                        <span>Return Slip</span>
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- MODAL: Sales Return Item Modal -->
    <div x-show="returnModal" x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/60 backdrop-blur-xs transition-opacity duration-300 print:hidden">
        <div @click.away="if (!isSubmittingReturn) returnModal = false" x-show="returnModal"
            x-transition:enter="transition ease-out duration-300 transform"
            x-transition:enter-start="opacity-0 scale-95 translate-y-4"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200 transform"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 translate-y-4"
            class="bg-white rounded-3xl p-5 sm:p-6 max-w-xl w-full shadow-2xl border border-gray-100 space-y-4 relative overflow-hidden max-h-[90vh] flex flex-col">

            <!-- Modal Header -->
            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 flex items-center justify-center shadow-xs shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-gray-900 tracking-tight">
                            Return Items
                        </h3>
                        <p class="text-xs text-gray-500">
                            Bill Ref: <strong><?= e($bill['bill_number']) ?></strong> (<?= e($bill['customer_name']) ?>)
                        </p>
                    </div>
                </div>
                <button type="button" @click="returnModal = false" class="text-gray-400 hover:text-gray-600 p-1.5 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Form -->
            <form method="POST" action="bill-return.php" class="flex-1 flex flex-col min-h-0 space-y-4" @submit="isSubmittingReturn = true">
                <?= csrf_field() ?>
                <input type="hidden" name="bill_id" value="<?= $bill['id'] ?>">

                <!-- Scrollable Form Body -->
                <div class="flex-1 overflow-y-auto space-y-4 pr-1">
                    <!-- Items to Return Table -->
                    <div class="border border-gray-200 rounded-2xl overflow-hidden">
                        <div class="bg-gray-50 px-3 py-2 border-b border-gray-200 text-[11px] font-bold text-gray-600 uppercase tracking-wider flex justify-between">
                            <span>Select Items & Quantity to Return</span>
                            <span>Avail / Sold</span>
                        </div>

                        <div class="divide-y divide-gray-100 max-h-52 overflow-y-auto">
                            <?php foreach ($returnableItems as $rit): ?>
                                <div class="p-3 hover:bg-amber-50/30 transition flex items-center justify-between gap-3 text-xs">
                                    <div class="flex-1 min-w-0">
                                        <div class="font-bold text-gray-900 truncate">
                                            <?= e($rit['product_name']) ?>
                                        </div>
                                        <div class="text-[11px] text-gray-500 flex items-center gap-2 mt-0.5">
                                            <span>Rate: <strong>₹<?= number_format($rit['unit_price'], 2) ?></strong></span>
                                            <span>•</span>
                                            <span class="text-emerald-700 font-semibold">Avail to return: <?= $rit['available_qty'] ?></span>
                                            <?php if ($rit['returned_quantity'] > 0): ?>
                                                <span class="text-amber-800">(Already ret: <?= $rit['returned_quantity'] ?>)</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Stepper Input -->
                                    <div class="flex items-center gap-1.5 shrink-0">
                                        <button type="button" 
                                            @click="setQty(<?= $rit['id'] ?>, -1, <?= $rit['available_qty'] ?>)"
                                            class="w-8 h-8 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold flex items-center justify-center active:scale-95 transition">
                                            -
                                        </button>
                                        <input type="number" 
                                            name="returns[<?= $rit['id'] ?>]" 
                                            min="0" 
                                            max="<?= $rit['available_qty'] ?>" 
                                            x-model.number="quantities[<?= $rit['id'] ?>]"
                                            class="w-14 text-center py-1.5 border border-gray-300 rounded-lg font-bold text-xs focus:ring-1 focus:ring-amber-500 focus:border-amber-500"
                                            placeholder="0">
                                        <button type="button" 
                                            @click="setQty(<?= $rit['id'] ?>, 1, <?= $rit['available_qty'] ?>)"
                                            class="w-8 h-8 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold flex items-center justify-center active:scale-95 transition">
                                            +
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Live Refund Calculation Summary -->
                    <div class="p-3.5 rounded-2xl bg-amber-50/70 border border-amber-200 text-xs space-y-1">
                        <div class="flex items-center justify-between font-bold text-amber-950">
                            <span>Total Refund Amount:</span>
                            <span class="font-mono text-base text-[#b53127]" x-text="'₹' + calculateTotal().toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })">
                                ₹0.00
                            </span>
                        </div>
                        <p class="text-[11px] text-amber-800 leading-snug">
                            * Selected products will be automatically added back to inventory stock.
                        </p>
                    </div>

                    <!-- Refund Settlement Option -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-gray-700">
                            Refund Settlement Method:
                        </label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                            <?php if ((float) $bill['due_amount'] > 0): ?>
                                <label class="flex items-center gap-2 p-2.5 rounded-xl border border-gray-200 hover:border-amber-400 cursor-pointer bg-white transition"
                                       :class="refundType === 'due_deduct' ? 'border-amber-500 bg-amber-50/40 text-amber-900 font-bold' : 'text-gray-700'">
                                    <input type="radio" name="refund_type" value="due_deduct" x-model="refundType" class="text-amber-600 focus:ring-amber-500">
                                    <div>
                                        <div>Deduct from Due / Khata</div>
                                        <div class="text-[10px] font-normal text-gray-500">Reduce customer's pending balance</div>
                                    </div>
                                </label>
                            <?php endif; ?>

                            <label class="flex items-center gap-2 p-2.5 rounded-xl border border-gray-200 hover:border-amber-400 cursor-pointer bg-white transition"
                                   :class="refundType === 'cash_refund' ? 'border-amber-500 bg-amber-50/40 text-amber-900 font-bold' : 'text-gray-700'">
                                <input type="radio" name="refund_type" value="cash_refund" x-model="refundType" class="text-amber-600 focus:ring-amber-500">
                                <div>
                                    <div>Cash Refund</div>
                                    <div class="text-[10px] font-normal text-gray-500">Cash returned to customer</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Return Reason / Note -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">
                            Return Reason / Note (Optional):
                        </label>
                        <input type="text" name="reason" x-model="reason"
                            placeholder="e.g. Excess goods returned / Dimension change"
                            class="w-full px-3 py-2 text-xs border border-gray-300 rounded-xl focus:ring-1 focus:ring-amber-500 focus:border-amber-500">
                    </div>
                </div>

                <!-- Pinned Action Buttons (Always visible at the bottom) -->
                <div class="pt-3 border-t border-gray-200 grid grid-cols-2 gap-3 shrink-0">
                    <button type="button" @click="returnModal = false" :disabled="isSubmittingReturn"
                        class="w-full py-2.5 px-4 rounded-xl text-xs font-bold transition flex items-center justify-center shadow-xs"
                        style="background-color: #f3f4f6; color: #374151; border: 1px solid #e5e7eb;">
                        Cancel
                    </button>

                    <!-- Disabled state (When no quantity selected, total <= 0) -->
                    <button type="button" 
                        x-show="calculateTotal() <= 0" 
                        disabled
                        class="w-full py-2.5 px-4 rounded-xl text-xs font-bold flex items-center justify-center gap-1.5 shadow-xs"
                        style="background-color: #f1f5f9; color: #94a3b8; cursor: not-allowed; border: 1px solid #cbd5e1;">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: #94a3b8;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span style="color: #94a3b8; font-weight: 700;">Confirm Return</span>
                    </button>

                    <!-- Active state (When quantity selected, total > 0) -->
                    <button type="submit" 
                        x-show="calculateTotal() > 0" 
                        :disabled="isSubmittingReturn"
                        class="w-full py-2.5 px-4 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 shadow-md active:scale-[0.98]"
                        style="background-color: #b53127; color: #ffffff; cursor: pointer; border: 1px solid #991b1b;">
                        <span x-show="!isSubmittingReturn" class="flex items-center gap-1.5" style="color: #ffffff;">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: #ffffff;">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span style="color: #ffffff; font-weight: 700;">Confirm Return</span>
                        </span>
                        <span x-show="isSubmittingReturn" class="flex items-center gap-1.5" style="color: #ffffff; display: none;">
                            <svg class="animate-spin w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" style="color: #ffffff;">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span style="color: #ffffff; font-weight: 700;">Processing...</span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: Botanical Soft Delete Confirmation -->
    <div x-show="deleteModal" x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs transition-opacity duration-300 print:hidden">
        <div @click.away="if (!isDeleting) deleteModal = false" x-show="deleteModal"
            x-transition:enter="transition ease-out duration-300 transform"
            x-transition:enter-start="opacity-0 scale-95 translate-y-4"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200 transform"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 translate-y-4"
            class="bg-white rounded-3xl p-6 sm:p-7 max-w-md w-full shadow-2xl border border-gray-100 space-y-5 text-center relative overflow-hidden">

            <div
                class="mx-auto w-16 h-16 rounded-2xl bg-rose-50 border border-rose-100 flex items-center justify-center text-rose-600 shadow-inner">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
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

            <div
                class="p-3.5 rounded-2xl bg-amber-50/70 border border-amber-200/80 text-left space-y-1.5 text-xs text-amber-900">
                <div class="flex items-center gap-2 font-bold text-amber-800">
                    <svg class="w-4 h-4 shrink-0 text-amber-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                            clip-rule="evenodd" />
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
                <button type="button" @click="deleteModal = false" :disabled="isDeleting"
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
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            <span>Yes, Delete</span>
                        </span>
                    </template>
                    <template x-if="isDeleting">
                        <span class="flex items-center gap-1.5">
                            <svg class="animate-spin w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
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
    function showToastNotice(msg) {
        let toast = document.getElementById('slip-toast-notice');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'slip-toast-notice';
            toast.className = 'fixed bottom-6 left-1/2 -translate-x-1/2 z-50 px-4 py-2.5 rounded-2xl bg-[#23382f] text-white text-xs font-semibold shadow-2xl flex items-center gap-2 transition-all duration-300 pointer-events-none opacity-0 translate-y-4';
            document.body.appendChild(toast);
        }
        toast.innerHTML = `<svg class="w-4 h-4 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg><span>${msg}</span>`;
        toast.style.opacity = '1';
        toast.style.transform = 'translate(-50%, 0)';
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translate(-50%, 16px)';
        }, 5000);
    }

    /**
     * Copy invoice image directly to clipboard for quick paste (Ctrl+V) into WhatsApp
     */
    async function copySlipImage(elementId, btnElement) {
        const btn = btnElement || document.getElementById('btn-copy-slip-img');
        const textSpan = document.getElementById('btn-copy-slip-img-text');
        const originalText = textSpan ? textSpan.textContent : 'Copy Image';
        if (textSpan) textSpan.textContent = 'Copying...';
        if (btn) btn.disabled = true;

        try {
            const element = document.getElementById(elementId);
            if (!element) throw new Error('Slip element not found.');

            const canvas = await html2canvas(element, {
                scale: 2,
                useCORS: true,
                backgroundColor: '#ffffff',
                logging: false,
                scrollX: 0,
                scrollY: 0,
                windowWidth: 850,
                onclone: (clonedDoc) => {
                    const slip = clonedDoc.getElementById(elementId);
                    if (slip) {
                        slip.style.width = '780px';
                        slip.style.minWidth = '780px';
                        slip.style.maxWidth = '780px';
                        slip.style.boxSizing = 'border-box';
                    }
                }
            });

            const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'));
            if (navigator.clipboard && window.ClipboardItem) {
                await navigator.clipboard.write([new ClipboardItem({ 'image/png': blob })]);
                if (textSpan) textSpan.textContent = 'Copied!';
                if (btn) {
                    btn.classList.remove('bg-gray-100', 'text-gray-700');
                    btn.classList.add('bg-emerald-600', 'text-white');
                }
                showToastNotice('📋 Slip image copied to clipboard! Press Ctrl+V in WhatsApp to paste.');
                setTimeout(() => {
                    if (textSpan) textSpan.textContent = originalText;
                    if (btn) {
                        btn.classList.remove('bg-emerald-600', 'text-white');
                        btn.classList.add('bg-gray-100', 'text-gray-700');
                        btn.disabled = false;
                    }
                }, 2500);
            } else {
                throw new Error('Clipboard API not supported in this browser.');
            }
        } catch (err) {
            console.error('Copy image error:', err);
            alert('Could not copy image directly. Please use "Share on WhatsApp" or "Print Slip".');
            if (textSpan) textSpan.textContent = originalText;
            if (btn) btn.disabled = false;
        }
    }

    /**
     * Captures a slip element as a PNG image and shares it via
     * the Web Share API (mobile) or downloads it (desktop fallback).
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

            // Capture the element as high-res canvas at desktop 780px width
            const canvas = await html2canvas(element, {
                scale: 2,              // 2× resolution for crisp image
                useCORS: true,
                backgroundColor: '#ffffff',
                logging: false,
                scrollX: 0,
                scrollY: 0,
                windowWidth: 850,
                onclone: (clonedDoc) => {
                    const slip = clonedDoc.getElementById(elementId);
                    if (slip) {
                        slip.style.width = '780px';
                        slip.style.minWidth = '780px';
                        slip.style.maxWidth = '780px';
                        slip.style.boxSizing = 'border-box';
                    }
                }
            });

            // Convert canvas → Blob (PNG)
            const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'));
            const filename = 'MOMAI_PLYWOOD_' + (billNumber || 'Bill') + '.png';
            const file = new File([blob], filename, { type: 'image/png' });

            // Automatically copy to clipboard (enables instant Ctrl+V paste on WhatsApp Web)
            let copiedToClipboard = false;
            if (navigator.clipboard && window.ClipboardItem) {
                try {
                    await navigator.clipboard.write([new ClipboardItem({ 'image/png': blob })]);
                    copiedToClipboard = true;
                } catch (e) {
                    console.log('Clipboard auto-copy bypassed:', e);
                }
            }

            // Mobile: use Web Share API with files ONLY
            // IMPORTANT: Passing 'text' alongside 'files' causes WhatsApp on Android to DROP the image and send only text!
            if (navigator.canShare && navigator.canShare({ files: [file] })) {
                await navigator.share({
                    files: [file]
                });
            } else {
                // Desktop fallback: download the image
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = filename;
                a.click();
                URL.revokeObjectURL(url);

                showToastNotice(copiedToClipboard
                    ? '📋 Bill image copied to clipboard! Press Ctrl+V in WhatsApp to paste and send.'
                    : '⬇️ Bill image downloaded! Please attach it in WhatsApp.');

                // Open WhatsApp chat after short delay
                setTimeout(() => {
                    const decodedPhone = decodeURIComponent(phone || '');
                    const cleanPhone = decodedPhone.replace(/[^0-9]/g, '');
                    const waPhone = cleanPhone.length === 10 ? '91' + cleanPhone : cleanPhone;
                    window.open('https://wa.me/' + (waPhone || ''), '_blank');
                }, 800);
            }
        } catch (err) {
            if (err.name !== 'AbortError') {
                console.error('WhatsApp share error:', err);
                alert('Could not share the slip. Please try copying the image or printing.');
            }
        } finally {
            if (btnText) btnText.textContent = originalText;
            btn.disabled = false;
        }
    }
</script>
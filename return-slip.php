<?php
/**
 * Dedicated Return Bill / Sales Return Slip
 * MOMAI PLYWOOD - Core PHP
 */
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth_guard.php';

$pdo = get_db();

$returnId = (int) ($_GET['id'] ?? 0);
$billId = (int) ($_GET['bill_id'] ?? 0);

if ($returnId <= 0 && $billId <= 0) {
    flash_set('error', 'Invalid return reference.');
    redirect('bill-returns.php');
}

// 1. Fetch return(s) and parent bill information
if ($returnId > 0) {
    $stmt = $pdo->prepare("
        SELECT br.*, 
               b.bill_number, 
               b.created_at as bill_date,
               b.customer_id,
               b.customer_name, 
               b.customer_phone, 
               b.customer_address,
               b.grand_total as original_grand_total,
               c.phone as c_phone, 
               c.address as c_address
        FROM bill_returns br
        JOIN bills b ON br.bill_id = b.id
        LEFT JOIN customers c ON b.customer_id = c.id
        WHERE br.id = ? AND b.deleted_at IS NULL
    ");
    $stmt->execute([$returnId]);
    $mainReturn = $stmt->fetch();

    if (!$mainReturn) {
        flash_set('error', 'Return record not found.');
        redirect('bill-returns.php');
    }

    $billId = (int) $mainReturn['bill_id'];
    $returnsList = [$mainReturn];
} else {
    // By bill_id: Fetch all returns for this bill
    $stmt = $pdo->prepare("
        SELECT b.*, c.phone as c_phone, c.address as c_address 
        FROM bills b 
        LEFT JOIN customers c ON b.customer_id = c.id 
        WHERE b.id = ? AND b.deleted_at IS NULL
    ");
    $stmt->execute([$billId]);
    $bill = $stmt->fetch();

    if (!$bill) {
        flash_set('error', 'Bill not found.');
        redirect('bill-returns.php');
    }

    $retStmt = $pdo->prepare("SELECT * FROM bill_returns WHERE bill_id = ? ORDER BY id ASC");
    $retStmt->execute([$billId]);
    $returnsList = $retStmt->fetchAll();

    if (empty($returnsList)) {
        flash_set('error', 'No returns recorded for this bill.');
        redirect("bill-slip.php?id={$billId}");
    }

    $mainReturn = $returnsList[0];
    $mainReturn['bill_number'] = $bill['bill_number'];
    $mainReturn['bill_date'] = $bill['created_at'];
    $mainReturn['customer_name'] = $bill['customer_name'];
    $mainReturn['customer_phone'] = $bill['customer_phone'];
    $mainReturn['customer_address'] = $bill['customer_address'];
    $mainReturn['original_grand_total'] = $bill['grand_total'];
    $mainReturn['bill_due_amount'] = $bill['due_amount'];
    $mainReturn['c_phone'] = $bill['c_phone'];
    $mainReturn['c_address'] = $bill['c_address'];
}

// Fetch current parent bill state if not already set
$parentBillStmt = $pdo->prepare("SELECT grand_total, due_amount, paid_amount FROM bills WHERE id = ?");
$parentBillStmt->execute([$billId]);
$parentBillData = $parentBillStmt->fetch();
$parentBillGrandTotal = (float) ($parentBillData['grand_total'] ?? 0);
$parentBillDue = (float) ($parentBillData['due_amount'] ?? 0);

// Calculate totals
$totalRefundAmount = 0.0;
$totalReturnedQty = 0;
$refundTypes = [];
$reasons = [];

foreach ($returnsList as $ret) {
    $totalRefundAmount += (float) $ret['total_refund'];
    $totalReturnedQty += (int) $ret['quantity'];
    $refundTypes[$ret['refund_type']] = true;
    if (!empty($ret['reason'])) {
        $reasons[] = $ret['reason'];
    }
}

$settlementMode = isset($refundTypes['due_deduct']) && isset($refundTypes['cash_refund'])
    ? 'Split: Due Deducted & Cash Refund'
    : (isset($refundTypes['due_deduct']) ? 'Deducted from Due / Customer Khata' : 'Cash Refund to Customer');

$reasonsSummary = !empty($reasons) ? implode('; ', array_unique($reasons)) : 'Customer Sales Return';

$customerName = $mainReturn['customer_name'] ?: 'Cash Customer';
$customerPhone = $mainReturn['customer_phone'] ?: ($mainReturn['c_phone'] ?? '');
$customerAddress = $mainReturn['customer_address'] ?: ($mainReturn['c_address'] ?? '');
$originalBillNo = $mainReturn['bill_number'];
$returnDate = !empty($mainReturn['created_at']) ? $mainReturn['created_at'] : (!empty($mainReturn['bill_date']) ? $mainReturn['bill_date'] : date('Y-m-d H:i:s'));

// Formatted Return Number (e.g., RET-2609160513)
$returnNumber = 'RET-' . (preg_replace('/[^0-9]/', '', $originalBillNo) ?: $mainReturn['id']);
if ($returnId > 0 && count($returnsList) === 1) {
    $returnNumber .= '-' . str_pad($mainReturn['id'], 2, '0', STR_PAD_LEFT);
}

// Amount in words
$amountInWords = convert_number_to_words((int) round($totalRefundAmount)) . ' Only';

// WhatsApp phone
$waPhone = preg_replace('/[^0-9]/', '', $customerPhone);
if ($waPhone && strlen($waPhone) === 10) {
    $waPhone = '91' . $waPhone;
}

$pageTitle = "Return Bill {$returnNumber} - MOMAI PLYWOOD";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/topbar.php';
?>
<style>
    @media print {
        #printable-return-slip {
            visibility: visible !important;
            border: 2px solid #b53127 !important;
            padding: 18px !important;
            box-sizing: border-box !important;
            box-shadow: none !important;
        }
        #printable-return-slip * {
            visibility: visible !important;
        }
    }
</style>

<div class="space-y-4 max-w-5xl mx-auto">

    <!-- Top Action Bar (Buttons) -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-4 rounded-2xl border border-gray-200 shadow-2xs print:hidden">
        <div>
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-rose-100 text-rose-800 border border-rose-200">
                    Return Bill Slip
                </span>
                <span class="font-mono text-sm font-black text-gray-900"><?= e($returnNumber) ?></span>
            </div>
            <p class="text-xs text-gray-500 mt-0.5">
                Ref Original Bill: <a href="bill-slip.php?id=<?= $billId ?>" class="font-bold text-[#b53127] hover:underline">#<?= e($originalBillNo) ?></a> • Date: <?= date('d-m-Y, h:i A', strtotime($returnDate)) ?>
            </p>
        </div>

        <div class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none">
            <!-- Print Button -->
            <button type="button" onclick="window.print()"
                class="shrink-0 px-4 py-2 rounded-full bg-[#324b3e] hover:bg-[#23382f] text-white text-xs font-bold shadow-md transition flex items-center gap-1.5 active:scale-[0.98]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                <span>Print Return Slip</span>
            </button>

            <!-- WhatsApp Share as Image Button -->
            <button type="button" id="btn-wa-share-return"
                onclick="shareSlipImage('printable-return-slip', '<?= e($returnNumber) ?>', '<?= rawurlencode($customerPhone) ?>')"
                class="shrink-0 px-3.5 py-2 rounded-full bg-[#25d366]/10 hover:bg-[#25d366] text-[#128c4c] hover:text-white text-xs font-semibold border border-[#25d366]/40 transition flex items-center gap-1.5 active:scale-[0.98]"
                title="Share Return Bill image on WhatsApp">
                <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z" />
                </svg>
                <span id="btn-wa-share-return-text">Share on WhatsApp</span>
            </button>

            <!-- Copy Slip Image Button -->
            <button type="button" id="btn-copy-return-img"
                onclick="copySlipImage('printable-return-slip', this)"
                class="shrink-0 px-3.5 py-2 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold transition flex items-center gap-1.5 active:scale-[0.98]"
                title="Copy Return Bill image to clipboard">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                </svg>
                <span id="btn-copy-return-img-text">Copy Image</span>
            </button>

            <!-- View Original Sales Bill -->
            <a href="bill-slip.php?id=<?= $billId ?>"
                class="shrink-0 px-3.5 py-2 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold transition flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span>Original Bill</span>
            </a>

            <!-- Return Bills List -->
            <a href="bill-returns.php"
                class="shrink-0 px-3.5 py-2 rounded-full bg-amber-50 hover:bg-amber-100 text-amber-900 text-xs font-semibold border border-amber-200 transition flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                </svg>
                <span>All Return Bills</span>
            </a>
        </div>
    </div>

    <!-- Slip Container (Scrollable on small mobile screens to keep layout intact) -->
    <div class="w-full overflow-x-auto pb-4 -mx-2 px-2 sm:mx-0 sm:px-0">
        <!-- Exact Physical MOMAI PLYWOOD Return Slip -->
        <div id="printable-return-slip"
            class="relative mx-auto bg-white border-2 border-[#b53127] rounded-sm p-4 sm:p-7 shadow-lg font-sans text-[#b53127] select-none"
            style="width: 100%; max-width: 800px; min-width: 720px; box-sizing: border-box;">

            <!-- Header: MOMAI PLYWOOD & Right-aligned RETURN Stamp -->
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 4px; border: none;">
                <tr>
                    <td style="width: 40%; vertical-align: top; text-align: left; padding: 0; border: none;">
                        <div class="text-2xl sm:text-3xl font-extrabold tracking-widest uppercase leading-none font-serif whitespace-nowrap"
                            style="color:#b53127;">
                            MOMAI
                        </div>
                        <div class="text-2xl sm:text-3xl font-extrabold tracking-widest uppercase leading-none font-serif whitespace-nowrap"
                            style="color:#b53127;">
                            PLYWOOD
                        </div>
                    </td>
                    <td style="width: 20%; vertical-align: top; text-align: center; padding: 0; border: none;">
                    </td>
                    <td style="width: 40%; vertical-align: top; text-align: right; padding: 2px 0 0 0; border: none;">
                    </td>
                </tr>
            </table>

            <!-- Customer & Return Info Grid Box -->
            <div class="border-t-2 border-b-2 border-[#b53127] my-2 text-xs">
                <div class="py-2 space-y-0">

                    <!-- NAME -->
                    <div class="flex items-start py-1 min-h-[26px]">
                        <span class="font-extrabold tracking-wider uppercase text-[11px] shrink-0 w-[90px]">NAME :</span>
                        <span class="flex-1 px-2 font-serif text-sm text-gray-900 leading-tight break-words"
                            style="overflow-wrap: anywhere; word-break: break-word;"><?= e($customerName) ?></span>
                    </div>

                    <!-- ADDRESS -->
                    <div class="flex items-start py-1 min-h-[26px]">
                        <span class="font-extrabold tracking-wider uppercase text-[11px] shrink-0 w-[90px]">ADDRESS :</span>
                        <span class="flex-1 px-2 font-serif text-sm text-gray-800 leading-tight break-words"
                            style="overflow-wrap: anywhere; word-break: break-word;"><?= e($customerAddress) ?></span>
                    </div>

                    <!-- MO -->
                    <div class="flex items-center py-1 min-h-[26px]">
                        <span class="font-extrabold tracking-wider uppercase text-[11px] shrink-0 w-[90px]">MO. :</span>
                        <span class="flex-1 px-2 font-serif text-sm text-gray-800 leading-tight"><?= e($customerPhone) ?></span>
                    </div>

                    <!-- RETURN NO -->
                    <div class="flex items-center py-1 min-h-[26px]">
                        <span class="font-extrabold tracking-wider uppercase text-[11px] shrink-0 w-[90px]">RETURN NO. :</span>
                        <span class="ml-2 font-serif text-sm font-black text-gray-900 leading-tight"><?= e($returnNumber) ?></span>
                    </div>

                    <!-- DATE -->
                    <div class="flex items-center py-1 min-h-[26px]">
                        <span class="font-extrabold tracking-wider uppercase text-[11px] shrink-0 w-[90px]">DATE :</span>
                        <span class="ml-2 font-serif text-sm text-gray-900 leading-tight"><?= date('d-m-Y', strtotime($returnDate)) ?></span>
                    </div>

                    <!-- ORIG BILL -->
                    <div class="flex items-center py-1 min-h-[26px]">
                        <span class="font-extrabold tracking-wider uppercase text-[11px] shrink-0 w-[90px]">ORIG. BILL :</span>
                        <span class="ml-2 font-mono text-sm font-bold text-[#b53127] leading-tight">#<?= e($originalBillNo) ?></span>
                    </div>

                </div>
            </div>

            <!-- Table of Returned Items -->
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
                        <tr class="border-b-2 border-[#b53127] font-extrabold text-[11px] uppercase tracking-wider text-center">
                            <th style="width: 48px;" class="py-1.5 px-2 border-r-2 border-[#b53127] text-center">NO.</th>
                            <th class="py-1.5 px-3 border-r-2 border-[#b53127] text-left">PARTICULARS (RETURNED GOODS)</th>
                            <th style="width: 68px;" class="py-1.5 px-2 border-r-2 border-[#b53127] text-center">QTY.</th>
                            <th style="width: 100px;" class="py-1.5 px-2 border-r-2 border-[#b53127] text-right">RATE</th>
                            <th style="width: 124px;" class="py-1.5 px-3 text-right">AMOUNT</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#b53127]/30 font-serif text-sm text-gray-900">
                        <?php
                        $rowCount = max(count($returnsList), 7);
                        for ($i = 0; $i < $rowCount; $i++):
                            $ret = $returnsList[$i] ?? null;
                            ?>
                            <tr class="h-8">
                                <!-- NO -->
                                <td style="width: 48px;" class="px-2 border-r-2 border-[#b53127] text-center text-xs text-[#b53127]">
                                    <?= $ret ? ($i + 1) : '' ?>
                                </td>

                                <!-- PARTICULARS -->
                                <td class="px-3 border-r-2 border-[#b53127] break-words leading-tight"
                                    style="overflow-wrap: anywhere; word-break: break-word;">
                                    <?= $ret ? e($ret['product_name']) : '' ?>
                                </td>

                                <!-- QTY -->
                                <td style="width: 68px;" class="px-2 border-r-2 border-[#b53127] text-center font-bold text-rose-700">
                                    <?= $ret ? (int) $ret['quantity'] : '' ?>
                                </td>

                                <!-- RATE -->
                                <td style="width: 100px;" class="px-2 border-r-2 border-[#b53127] text-right font-mono font-medium">
                                    <?= $ret ? number_format((float) $ret['unit_price'], 2) : '' ?>
                                </td>

                                <!-- AMOUNT -->
                                <td style="width: 124px;" class="px-3 text-right font-mono font-bold text-rose-700">
                                    <?= $ret ? number_format((float) $ret['total_refund'], 2) : '' ?>
                                </td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>

                    <!-- Totals & In-Words Footer -->
                    <tfoot>
                        <tr class="border-t-2 border-[#b53127] text-xs">
                            <td colspan="3" class="px-3 py-2 border-r-2 border-[#b53127] align-top">
                                <div class="text-[13px] font-extrabold uppercase tracking-widest text-[#b53127] mb-1 font-serif">
                                    RETURN
                                </div>
                                <div class="text-[10px] font-extrabold uppercase tracking-wider text-[#b53127]">
                                    RUPEES IN WORDS:
                                </div>
                                <div class="font-serif italic font-bold text-gray-900 text-xs mt-0.5 break-words"
                                    style="overflow-wrap: anywhere; word-break: break-word;">
                                    <?= e($amountInWords) ?>
                                </div>

                                <div class="mt-2 text-[11px] text-[#b53127] font-semibold flex flex-wrap items-center gap-2">
                                    <span class="px-1.5 py-0.5 rounded bg-amber-50 border border-amber-200">
                                        Ref Original Bill: #<?= e($originalBillNo) ?>
                                    </span>
                                </div>
                            </td>
                            <td colspan="2" class="px-3 py-2 text-right align-middle">
                                <div class="text-[11px] font-extrabold uppercase tracking-wider text-[#b53127]">
                                    TOTAL RETURN
                                </div>
                                <div class="font-mono font-black text-lg text-[#b53127] leading-tight mt-0.5">
                                    ₹<?= number_format($totalRefundAmount, 2) ?>
                                </div>
                                <div class="text-[10px] text-gray-500 mt-1">
                                    Total Items Returned: <?= $totalReturnedQty ?>
                                </div>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Signatures Section -->
            <div class="flex justify-between items-end text-center font-serif text-xs text-[#b53127]" style="margin-top: 54px; padding-top: 8px; padding-bottom: 8px;">
                <div>
                    <div style="height: 48px;"></div>
                    <div class="border-t border-[#b53127] px-6 pt-1 font-bold tracking-wider text-[11px] uppercase" style="border-top: 1.5px solid #b53127;">
                        Customer's Signature
                    </div>
                </div>
                <div>
                    <div style="height: 48px;"></div>
                    <div class="border-t border-[#b53127] px-6 pt-1 font-bold tracking-wider text-[11px] uppercase" style="border-top: 1.5px solid #b53127;">
                        For, MOMAI PLYWOOD
                    </div>
                </div>
            </div>

        </div>
    </div>

</div>

<!-- High-Resolution Image Capture & Sharing Script (html2canvas) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<div id="toast-notice"
    class="fixed bottom-6 right-6 z-50 transform transition-all duration-300 translate-y-20 opacity-0 pointer-events-none bg-gray-900 text-white text-xs font-semibold px-4 py-3 rounded-xl shadow-xl flex items-center gap-2">
    <span id="toast-notice-msg">Notice</span>
</div>

<script>
    function showToastNotice(msg) {
        const toast = document.getElementById('toast-notice');
        const msgEl = document.getElementById('toast-notice-msg');
        if (!toast || !msgEl) return;
        msgEl.textContent = msg;
        toast.classList.remove('translate-y-20', 'opacity-0', 'pointer-events-none');
        setTimeout(() => {
            toast.classList.add('translate-y-20', 'opacity-0', 'pointer-events-none');
        }, 3500);
    }

    async function copySlipImage(elementId, btnElement) {
        const btn = btnElement || document.getElementById('btn-copy-return-img');
        const textSpan = document.getElementById('btn-copy-return-img-text');
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
                    const badge = clonedDoc.getElementById('return-badge-box');
                    if (badge) {
                        badge.style.overflow = 'visible';
                        badge.style.lineHeight = '1.4';
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
                showToastNotice('📋 Return bill image copied to clipboard! Press Ctrl+V in WhatsApp to paste.');
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
            alert('Could not copy image directly. Please use "Share on WhatsApp" or "Print Return Slip".');
            if (textSpan) textSpan.textContent = originalText;
            if (btn) btn.disabled = false;
        }
    }

    async function shareSlipImage(elementId, returnNumber, phone) {
        const btn = document.getElementById('btn-wa-share-return');
        const btnText = document.getElementById('btn-wa-share-return-text');
        if (!btn) return;

        const originalText = btnText ? btnText.textContent : 'Share on WhatsApp';
        if (btnText) btnText.textContent = 'Capturing...';
        btn.disabled = true;

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
                    const badge = clonedDoc.getElementById('return-badge-box');
                    if (badge) {
                        badge.style.overflow = 'visible';
                        badge.style.lineHeight = '1.4';
                    }
                }
            });

            const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'));
            const filename = 'MOMAI_PLYWOOD_' + (returnNumber || 'Return') + '.png';
            const file = new File([blob], filename, { type: 'image/png' });

            let copiedToClipboard = false;
            if (navigator.clipboard && window.ClipboardItem) {
                try {
                    await navigator.clipboard.write([new ClipboardItem({ 'image/png': blob })]);
                    copiedToClipboard = true;
                } catch (e) {
                    console.log('Clipboard auto-copy bypassed:', e);
                }
            }

            if (navigator.canShare && navigator.canShare({ files: [file] })) {
                await navigator.share({
                    files: [file]
                });
            } else {
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = filename;
                a.click();
                URL.revokeObjectURL(url);

                showToastNotice(copiedToClipboard
                    ? '📋 Return bill image copied to clipboard! Press Ctrl+V in WhatsApp to paste and send.'
                    : '⬇️ Return bill image downloaded! Please attach it in WhatsApp.');

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

<?php require_once __DIR__ . '/includes/footer.php'; ?>

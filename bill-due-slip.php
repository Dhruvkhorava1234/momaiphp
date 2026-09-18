<?php
/**
 * Small Due Payment / Khata Thermal Receipt Slip
 * MOMAI PLYWOOD - Core PHP
 */
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth_guard.php';

$billId = (int) ($_GET['id'] ?? 0);
$paymentId = (int) ($_GET['payment_id'] ?? 0);

if ($billId <= 0) {
    flash_set('error', 'Invalid Bill ID.');
    redirect('bills.php');
}

$pdo = get_db();

// Load Bill & Customer
$stmt = $pdo->prepare("SELECT b.*, c.phone as c_phone, c.address as c_address FROM bills b LEFT JOIN customers c ON b.customer_id = c.id WHERE b.id = ? AND b.deleted_at IS NULL");
$stmt->execute([$billId]);
$bill = $stmt->fetch();

if (!$bill) {
    flash_set('error', 'Bill not found.');
    redirect('bills.php');
}

// Load Items
$itemStmt = $pdo->prepare("SELECT * FROM bill_items WHERE bill_id = ? AND deleted_at IS NULL");
$itemStmt->execute([$billId]);
$items = $itemStmt->fetchAll();

// Load Payment
if ($paymentId > 0) {
    $pStmt = $pdo->prepare("SELECT * FROM bill_payments WHERE id = ? AND bill_id = ?");
    $pStmt->execute([$paymentId, $billId]);
    $payment = $pStmt->fetch();
} else {
    $pStmt = $pdo->prepare("SELECT * FROM bill_payments WHERE bill_id = ? ORDER BY id DESC LIMIT 1");
    $pStmt->execute([$billId]);
    $payment = $pStmt->fetch();
}

$pageTitle = "Due Payment Slip - {$bill['bill_number']}";

// WhatsApp Message Text (Khata / Due Reminder)
$waPhone = preg_replace('/[^0-9]/', '', $bill['customer_phone'] ?? '');
if ($waPhone && strlen($waPhone) === 10) {
    $waPhone = '91' . $waPhone;
}
$waFullyUnpaid = ((float)$bill['paid_amount'] <= 0);
$waMessage  = "🪵 *MOMAI PLYWOOD* - Khata Slip\n";
$waMessage .= "━━━━━━━━━━━━━━━━━━━━━\n";
$waMessage .= "📄 Bill No: *{$bill['bill_number']}*\n";
$waMessage .= "👤 Customer: {$bill['customer_name']}\n";
$waMessage .= "📅 Date: " . date('d-m-Y', strtotime($bill['created_at'])) . "\n";
$waMessage .= "━━━━━━━━━━━━━━━━━━━━━\n";
$waMessage .= "💰 Bill Total: ₹" . number_format((float)$bill['grand_total'], 2) . "\n";
if ($waFullyUnpaid) {
    $waMessage .= "⚠️ Payment: ₹0.00 (Fully Unpaid)\n";
} else {
    $waMessage .= "✔️ Amount Paid: ₹" . number_format((float)$bill['paid_amount'], 2) . "\n";
}
$waMessage .= "━━━━━━━━━━━━━━━━━━━━━\n";
$waMessage .= "🔴 *REMAINING DUE: ₹" . number_format((float)$bill['due_amount'], 2) . "*\n";
$waMessage .= "━━━━━━━━━━━━━━━━━━━━━\n";
$waMessage .= "Kindly clear the outstanding balance at your earliest convenience. Thank you! 🙏\n";
$waMessage .= "— MOMAI PLYWOOD";
$waUrl = 'https://wa.me/' . ($waPhone ?: '') . '?text=' . rawurlencode($waMessage);

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/topbar.php';
?>

<div x-data="{ deleteModal: false, isDeleting: false }" class="max-w-md mx-auto space-y-6">
    
    <!-- Action Toolbar (Hidden during print) -->
    <div class="flex flex-wrap items-center justify-between gap-3 bg-white p-4 rounded-2xl border border-gray-200/80 shadow-2xs print:hidden">
        <div class="flex items-center gap-2">
            <?php if ($bill['payment_status'] === 'unpaid' || (float)$bill['paid_amount'] <= 0): ?>
                <span class="w-2.5 h-2.5 rounded-full bg-rose-500 animate-pulse"></span>
                <span class="text-xs font-bold text-rose-700">Unpaid Khata Slip</span>
            <?php else: ?>
                <span class="w-2.5 h-2.5 rounded-full bg-amber-500 animate-pulse"></span>
                <span class="text-xs font-bold text-gray-700">Due Payment Slip</span>
            <?php endif; ?>
        </div>
        
        <div class="flex items-center gap-2">
            <button type="button"
                    onclick="window.print()"
                    class="px-3.5 py-2 rounded-full bg-[#324b3e] hover:bg-[#23382f] text-white text-xs font-bold shadow-md transition flex items-center gap-1.5 active:scale-[0.98]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                <span>Print Slip</span>
            </button>

            <!-- WhatsApp Share as Image Button -->
            <button type="button"
               id="btn-wa-share-due-slip"
               onclick="shareSlipImage('printable-due-slip', '<?= e($bill['bill_number']) ?>', '<?= rawurlencode($bill['customer_phone'] ?? '') ?>')"
               class="px-3 py-2 rounded-full bg-[#25d366]/10 hover:bg-[#25d366] text-[#128c4c] hover:text-white text-xs font-semibold border border-[#25d366]/40 transition flex items-center gap-1.5 active:scale-[0.98]"
               title="Send Khata slip image on WhatsApp">
                <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                </svg>
                <span id="btn-wa-share-due-slip-text">Send via WhatsApp</span>
            </button>

            <a href="bills.php"
               class="px-3 py-2 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold transition">
                Bills
            </a>

            <a href="bill-slip.php?id=<?= $bill['id'] ?>"
               class="px-3 py-2 rounded-full bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-semibold border border-emerald-200 transition"
               title="View Full Bill">
                Full Bill
            </a>

            <button type="button"
                @click="deleteModal = true"
                class="btn-delete-animated px-3 py-2 rounded-full bg-rose-50 hover:bg-rose-600 hover:text-white text-rose-700 text-xs font-semibold border border-rose-200 transition flex items-center gap-1"
                title="Delete Bill">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                <span>Delete</span>
            </button>
            <form id="delete-due-slip-form" method="POST" action="bill-delete.php" class="hidden">
                <?= csrf_field() ?>
                <input type="hidden" name="bill_id" value="<?= $bill['id'] ?>">
            </form>
        </div>
    </div>

    <!-- Info Note explaining workflow -->
    <div class="p-3.5 rounded-2xl border text-xs flex items-center justify-between print:hidden <?= ($bill['payment_status'] === 'unpaid' || (float)$bill['paid_amount'] <= 0) ? 'bg-rose-50 border-rose-200 text-rose-900' : 'bg-amber-50 border-amber-200 text-amber-900' ?>">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0 <?= ($bill['payment_status'] === 'unpaid' || (float)$bill['paid_amount'] <= 0) ? 'text-rose-600' : 'text-amber-600' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <?php if ($bill['payment_status'] === 'unpaid' || (float)$bill['paid_amount'] <= 0): ?>
                <span><strong>Fully Unpaid / Udhar Khata:</strong> Slip generated with ₹0 payment. All amount remains outstanding under customer Khata.</span>
            <?php else: ?>
                <span><strong>Partial Due Active:</strong> Small slip issued for payment received. The official Red MOMAI PLYWOOD Full Bill unlocks automatically once fully paid.</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Small Compact Slip Document -->
    <div id="printable-due-slip" class="bg-white rounded-2xl p-6 border-2 border-dashed border-gray-300 shadow-md text-gray-900 font-mono text-xs space-y-4 max-w-[380px] mx-auto">
        
        <!-- Store Header -->
        <div class="text-center pb-3 border-b-2 border-dashed border-gray-300">
            <div class="font-extrabold text-base tracking-widest text-[#23382f]">
                MOMAI PLYWOOD
            </div>
            <div class="text-[10px] text-gray-500 font-sans uppercase">
                <?= ($bill['payment_status'] === 'unpaid' || (float)$bill['paid_amount'] <= 0) ? 'Due Khata Slip (Unpaid)' : 'Payment Receipt & Due Khata Slip' ?>
            </div>
            <div class="text-[11px] font-bold text-gray-600 mt-0.5">
                MO. 91063 40961
            </div>
        </div>

        <!-- Receipt Info -->
        <div class="space-y-1 text-[11px] pb-3 border-b border-dashed border-gray-200">
            <div class="flex justify-between">
                <span class="text-gray-500">Bill Ref:</span>
                <span class="font-bold text-gray-900"><?= e($bill['bill_number']) ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">Date & Time:</span>
                <span class="font-bold text-gray-800">
                    <?= date('d/m/Y, h:i A', strtotime($payment['created_at'] ?? $bill['created_at'])) ?>
                </span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">Customer:</span>
                <span class="font-bold text-gray-900"><?= e($bill['customer_name']) ?></span>
            </div>
            <?php if (!empty($bill['customer_phone'])): ?>
                <div class="flex justify-between">
                    <span class="text-gray-500">Phone:</span>
                    <span class="text-gray-800"><?= e($bill['customer_phone']) ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($bill['customer_address'])): ?>
                <div class="flex justify-between">
                    <span class="text-gray-500">Address:</span>
                    <span class="text-gray-800"><?= e($bill['customer_address']) ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Items Purchased Summary -->
        <div class="space-y-1.5 pb-3 border-b border-dashed border-gray-200 text-[11px]">
            <div class="flex justify-between font-bold text-gray-500 uppercase text-[9px] tracking-wider pb-1">
                <span>Particulars</span>
                <span>Qty x Rate</span>
                <span class="text-right">Amt</span>
            </div>
            <?php foreach ($items as $item): ?>
                <div class="flex justify-between items-baseline gap-1">
                    <span class="truncate flex-1 font-semibold text-gray-800"><?= e($item['product_name']) ?></span>
                    <span class="text-gray-500 text-[10px]"><?= $item['quantity'] ?> x <?= number_format((float)$item['unit_price'], 0) ?></span>
                    <span class="font-bold text-gray-900 text-right font-mono">₹<?= number_format((float)$item['total_price'], 0) ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Financial Balance Breakdown -->
        <div class="space-y-1.5 pt-1 text-xs">
            <div class="flex justify-between text-gray-600">
                <span>Total Bill Amount:</span>
                <span class="font-bold text-gray-900 font-mono">₹<?= number_format((float)$bill['grand_total'], 2) ?></span>
            </div>

            <?php if ($payment && (float)$payment['amount_paid'] > 0): ?>
                <div class="flex justify-between text-emerald-700 font-bold bg-emerald-50 px-2 py-1 rounded">
                    <span>Payment Received Now:</span>
                    <span class="font-mono">₹<?= number_format((float)$payment['amount_paid'], 2) ?></span>
                </div>
            <?php else: ?>
                <div class="flex justify-between text-rose-700 font-bold bg-rose-50 px-2 py-1 rounded border border-rose-100">
                    <span>Payment Received Now:</span>
                    <span class="font-mono">₹0.00 (Fully Unpaid)</span>
                </div>
            <?php endif; ?>

            <div class="flex justify-between text-gray-600">
                <span>Cumulative Paid:</span>
                <span class="font-mono">₹<?= number_format((float)$bill['paid_amount'], 2) ?></span>
            </div>

            <div class="flex justify-between items-baseline pt-2 border-t-2 border-gray-900 text-sm font-black">
                <span class="text-rose-700 uppercase tracking-wider text-xs">REMAINING DUE:</span>
                <span class="text-rose-700 font-mono text-base">₹<?= number_format((float)$bill['due_amount'], 2) ?></span>
            </div>
        </div>

        <!-- Footer Notice -->
        <div class="text-center pt-3 border-t border-dashed border-gray-300 text-[10px] text-gray-400 space-y-0.5">
            <div>Thank you for your business!</div>
            <div class="italic">Please retain this slip for Khata balance verification.</div>
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
                    Are you sure you want to delete this bill and Khata slip?
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
                        @click="isDeleting = true; $('#printable-due-slip').addClass('animating-delete'); setTimeout(() => $('#delete-due-slip-form').submit(), 550)"
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
async function shareSlipImage(elementId, billNumber, phone) {
    const btnId = 'btn-wa-share-' + elementId.replace('printable-', '');
    const btn = document.getElementById(btnId);
    const btnText = document.getElementById(btnId + '-text');
    if (!btn) return;

    const originalText = btnText ? btnText.textContent : 'Send via WhatsApp';
    if (btnText) btnText.textContent = 'Capturing...';
    btn.disabled = true;

    try {
        const element = document.getElementById(elementId);
        if (!element) throw new Error('Slip element not found.');

        const canvas = await html2canvas(element, {
            scale: 2,
            useCORS: true,
            backgroundColor: '#ffffff',
            logging: false
        });

        const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'));
        const filename = 'MOMAI_PLYWOOD_' + (billNumber || 'Khata') + '.png';
        const file = new File([blob], filename, { type: 'image/png' });

        if (navigator.canShare && navigator.canShare({ files: [file] })) {
            await navigator.share({
                files: [file],
                title: 'MOMAI PLYWOOD - Khata Slip ' + (billNumber || ''),
                text: 'Please find your Khata slip from MOMAI PLYWOOD.'
            });
        } else {
            // Desktop: download image then open WhatsApp
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            a.click();
            URL.revokeObjectURL(url);

            setTimeout(() => {
                const decodedPhone = decodeURIComponent(phone || '');
                const cleanPhone = decodedPhone.replace(/[^0-9]/g, '');
                const waPhone = cleanPhone.length === 10 ? '91' + cleanPhone : cleanPhone;
                const waNote = encodeURIComponent('Please find the attached Khata slip from MOMAI PLYWOOD.');
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

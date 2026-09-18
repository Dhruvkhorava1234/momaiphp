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
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/topbar.php';
?>

<div class="max-w-md mx-auto space-y-6">
    
    <!-- Action Toolbar (Hidden during print) -->
    <div class="flex flex-wrap items-center justify-between gap-3 bg-white p-4 rounded-2xl border border-gray-200/80 shadow-2xs print:hidden">
        <div class="flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-amber-500 animate-pulse"></span>
            <span class="text-xs font-bold text-gray-700">Due Payment Slip</span>
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

            <a href="bills.php"
               class="px-3 py-2 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold transition">
                Bills
            </a>

            <a href="bill-slip.php?id=<?= $bill['id'] ?>"
               class="px-3 py-2 rounded-full bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-semibold border border-emerald-200 transition"
               title="View Full Bill">
                Full Bill
            </a>
        </div>
    </div>

    <!-- Info Note explaining workflow -->
    <div class="p-3.5 bg-amber-50 rounded-2xl border border-amber-200 text-amber-900 text-xs flex items-center justify-between print:hidden">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span><strong>Partial Due Active:</strong> Small slip issued for payment received. The official Red MOMAI PLYWOOD Full Bill unlocks automatically once fully paid.</span>
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
                Payment Receipt & Due Khata Slip
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

            <?php if ($payment): ?>
                <div class="flex justify-between text-emerald-700 font-bold bg-emerald-50 px-2 py-1 rounded">
                    <span>Payment Received Now:</span>
                    <span class="font-mono">₹<?= number_format((float)$payment['amount_paid'], 2) ?></span>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>

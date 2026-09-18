<?php
/**
 * Bills & Ledger History
 * MOMAI PLYWOOD - Core PHP
 */
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth_guard.php';

$pdo = get_db();

// Handle Status Filters
$statusFilter = trim($_GET['status'] ?? '');
$searchFilter = trim($_GET['search'] ?? '');

$sql = "SELECT b.*, c.phone as c_phone, c.address as c_address 
        FROM bills b 
        LEFT JOIN customers c ON b.customer_id = c.id 
        WHERE b.deleted_at IS NULL";
$params = [];

if (!empty($statusFilter) && in_array($statusFilter, ['paid', 'partial', 'unpaid'])) {
    $sql .= " AND b.payment_status = ?";
    $params[] = $statusFilter;
}

if (!empty($searchFilter)) {
    $sql .= " AND (b.bill_number LIKE ? OR b.customer_name LIKE ? OR b.customer_phone LIKE ?)";
    $term = "%{$searchFilter}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$sql .= " ORDER BY b.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bills = $stmt->fetchAll();

// Financial metrics
$totStmt = $pdo->query("SELECT COALESCE(SUM(grand_total), 0) AS billed, COALESCE(SUM(paid_amount), 0) AS collected, COALESCE(SUM(due_amount), 0) AS due FROM bills WHERE deleted_at IS NULL");
$totals = $totStmt->fetch();
$totalBilled = (float) $totals['billed'];
$totalCollected = (float) $totals['collected'];
$totalDue = (float) $totals['due'];

$pageTitle = 'Bills History - MOMAI PLYWOOD';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/topbar.php';
?>

<div x-data="{ paymentModal: false, selectedBill: null, paymentAmount: '' }"
     @open-payment-modal.window="selectedBill = $event.detail; paymentAmount = Number($event.detail.due_amount); paymentModal = true"
     class="space-y-6">
    
    <!-- Header & Metrics -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-gray-200/80 shadow-2xs">
        <div>
            <h1 class="text-xl font-bold text-gray-800 tracking-tight">
                Bills & Transaction History
            </h1>
            <p class="text-xs text-gray-500">
                Full DataTable ledger of customer bills, payments, and outstanding balances.
            </p>
        </div>
        <div>
            <a href="bill-create.php"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-[#324b3e] hover:bg-[#23382f] text-white text-xs font-bold shadow-md transition active:scale-[0.98]">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                <span>Make New Bill</span>
            </a>
        </div>
    </div>

    <!-- 3 Financial Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl p-4 border border-gray-200/80 shadow-2xs">
            <span class="text-[11px] font-medium text-gray-500 block">Total Billed Volume</span>
            <span class="text-xl font-bold text-gray-900 font-mono"><?= format_inr($totalBilled) ?></span>
        </div>
        <div class="bg-white rounded-2xl p-4 border border-gray-200/80 shadow-2xs">
            <span class="text-[11px] font-medium text-emerald-600 block">Total Collected Cash</span>
            <span class="text-xl font-bold text-emerald-800 font-mono"><?= format_inr($totalCollected) ?></span>
        </div>
        <div class="bg-white rounded-2xl p-4 border border-rose-200 shadow-2xs bg-rose-50/40">
            <span class="text-[11px] font-bold text-rose-600 block">Total Customer Due (Khata)</span>
            <span class="text-xl font-bold text-rose-700 font-mono"><?= format_inr($totalDue) ?></span>
        </div>
    </div>

    <!-- Status Filter Tabs -->
    <div class="flex flex-wrap items-center gap-2">
        <span class="text-xs font-semibold text-gray-500">Filter Status:</span>
        <a href="bills.php"
           class="px-3 py-1 rounded-full text-xs font-semibold transition <?= empty($statusFilter) ? 'bg-[#324b3e] text-white shadow-2xs' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50' ?>">
            All Bills
        </a>
        <a href="bills.php?status=partial"
           class="px-3 py-1 rounded-full text-xs font-semibold transition <?= ($statusFilter === 'partial') ? 'bg-amber-600 text-white shadow-2xs' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50' ?>">
            Partial Due
        </a>
        <a href="bills.php?status=paid"
           class="px-3 py-1 rounded-full text-xs font-semibold transition <?= ($statusFilter === 'paid') ? 'bg-emerald-600 text-white shadow-2xs' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50' ?>">
            Fully Paid
        </a>
        <a href="bills.php?status=unpaid"
           class="px-3 py-1 rounded-full text-xs font-semibold transition <?= ($statusFilter === 'unpaid') ? 'bg-rose-600 text-white shadow-2xs' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50' ?>">
            Unpaid
        </a>
    </div>

    <!-- Bills DataTable Card -->
    <div class="bg-white rounded-2xl border border-gray-200/80 shadow-2xs overflow-hidden">
        <div class="p-2 sm:p-4 overflow-x-auto">
            <table id="bills-table" class="display responsive nowrap w-full text-left text-xs">
                <thead>
                    <tr>
                        <th>Bill No</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th class="text-right">Grand Total</th>
                        <th class="text-right">Paid</th>
                        <th class="text-right">Balance Due</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bills as $bill): ?>
                        <tr>
                            <td class="font-mono font-bold text-gray-800">
                                <a href="bill-slip.php?id=<?= $bill['id'] ?>" class="hover:text-[#324b3e] underline">
                                    <?= e($bill['bill_number']) ?>
                                </a>
                            </td>
                            <td>
                                <div class="font-semibold text-gray-900"><?= e($bill['customer_name']) ?></div>
                                <?php if (!empty($bill['customer_phone'])): ?>
                                    <div class="text-[10px] text-gray-400 font-mono"><?= e($bill['customer_phone']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-gray-500 font-mono text-[11px]" data-order="<?= strtotime($bill['created_at']) ?>">
                                <?= date('d M Y, h:i A', strtotime($bill['created_at'])) ?>
                            </td>
                            <td class="text-right font-mono font-semibold text-gray-900" data-order="<?= $bill['grand_total'] ?>">
                                <?= format_inr((float)$bill['grand_total']) ?>
                            </td>
                            <td class="text-right font-mono text-emerald-700 font-semibold" data-order="<?= $bill['paid_amount'] ?>">
                                <?= format_inr((float)$bill['paid_amount']) ?>
                            </td>
                            <td class="text-right font-mono font-bold <?= (float)$bill['due_amount'] > 0 ? 'text-rose-600' : 'text-gray-400' ?>" data-order="<?= $bill['due_amount'] ?>">
                                <?= format_inr((float)$bill['due_amount']) ?>
                            </td>
                            <td class="text-center">
                                <?php if ($bill['payment_status'] === 'paid'): ?>
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                        Paid
                                    </span>
                                <?php elseif ($bill['payment_status'] === 'partial'): ?>
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-900 border border-amber-200">
                                        Partial Due
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-200">
                                        Unpaid
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <!-- Print Slip Button -->
                                    <a href="bill-slip.php?id=<?= $bill['id'] ?>"
                                       class="p-1.5 rounded-lg bg-gray-100 text-gray-700 hover:bg-[#324b3e] hover:text-white transition"
                                       title="View Official Bill">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </a>

                                    <!-- Due Slip Icon -->
                                    <a href="bill-due-slip.php?id=<?= $bill['id'] ?>"
                                       class="p-1.5 rounded-lg bg-amber-50 text-amber-700 hover:bg-amber-600 hover:text-white transition"
                                       title="View Due / Khata Slip">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </a>

                                    <!-- Record Payment Button (If Due exists) -->
                                    <?php if ((float)$bill['due_amount'] > 0): ?>
                                        <button type="button"
                                                class="btn-pay-action px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-800 hover:bg-emerald-600 hover:text-white text-[11px] font-bold transition flex items-center gap-1 shadow-2xs"
                                                data-bill="<?= htmlspecialchars(json_encode($bill), ENT_QUOTES, 'UTF-8') ?>">
                                            <span>+ Pay</span>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- MODAL: Record Due Payment -->
    <div x-show="paymentModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
        <div @click.away="paymentModal = false" class="bg-white rounded-3xl p-6 max-w-sm w-full shadow-2xl border border-gray-100 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                <h3 class="font-bold text-base text-gray-800">Collect Due Payment</h3>
                <button type="button" @click="paymentModal = false" class="text-gray-400 hover:text-gray-600">✕</button>
            </div>

            <div class="p-3 bg-rose-50 rounded-2xl border border-rose-100 text-xs">
                <div class="flex justify-between">
                    <span class="text-gray-600">Bill No:</span>
                    <strong class="font-mono text-gray-900" x-text="selectedBill ? selectedBill.bill_number : ''"></strong>
                </div>
                <div class="flex justify-between mt-1">
                    <span class="text-gray-600">Customer:</span>
                    <strong class="text-gray-900" x-text="selectedBill ? selectedBill.customer_name : ''"></strong>
                </div>
                <div class="flex justify-between mt-1 text-rose-700 font-bold">
                    <span>Outstanding Due:</span>
                    <span class="font-mono text-sm" x-text="selectedBill ? '₹' + Number(selectedBill.due_amount).toFixed(2) : ''"></span>
                </div>
            </div>

            <form method="POST" action="bill-payment.php" class="space-y-3.5">
                <?= csrf_field() ?>
                <input type="hidden" name="bill_id" :value="selectedBill ? selectedBill.id : ''">

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Payment Amount (₹) *</label>
                    <input type="number" step="0.01" name="amount" x-model="paymentAmount" required min="1" :max="selectedBill ? selectedBill.due_amount : ''"
                           class="w-full px-3.5 py-2 rounded-xl text-sm font-mono font-bold bg-gray-50 border border-gray-300 focus:bg-white focus:border-[#324b3e] outline-none">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Payment Method</label>
                    <select name="payment_method" class="w-full px-3.5 py-2 rounded-xl text-xs bg-gray-50 border border-gray-300 outline-none">
                        <option value="cash">Cash</option>
                        <option value="upi">UPI / Online / GPay</option>
                        <option value="bank_transfer">Bank Transfer / NEFT</option>
                        <option value="cheque">Cheque</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Remarks / Note</label>
                    <input type="text" name="note" placeholder="e.g. Cleared pending balance" class="w-full px-3.5 py-2 rounded-xl text-xs bg-gray-50 border border-gray-300 outline-none">
                </div>

                <div class="pt-2 flex justify-end gap-2">
                    <button type="button" @click="paymentModal = false" class="px-4 py-2 rounded-xl bg-gray-100 text-gray-600 text-xs font-semibold">Cancel</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-[#324b3e] text-white text-xs font-bold shadow-md hover:bg-[#23382f]">Record Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        $('#bills-table').DataTable({
            pageLength: 25,
            order: [[2, 'desc']],
            responsive: true,
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search bills, customer..."
            }
        });

        $(document).on('click', '.btn-pay-action', function(e) {
            e.preventDefault();
            const billData = $(this).data('bill');
            window.dispatchEvent(new CustomEvent('open-payment-modal', { detail: billData }));
        });
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
/**
 * Sales Returns & Returned Bills Ledger
 * MOMAI PLYWOOD - Core PHP
 */
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth_guard.php';

$pdo = get_db();

// Filter by refund type
$typeFilter = trim($_GET['type'] ?? '');
$searchFilter = trim($_GET['search'] ?? '');

$sql = "SELECT br.*, 
               b.bill_number, 
               b.customer_name as b_customer_name, 
               b.customer_phone as b_customer_phone,
               b.created_at as bill_created_at,
               b.grand_total as bill_grand_total,
               b.due_amount as bill_due_amount,
               b.payment_status as bill_payment_status,
               p.sku as product_sku
        FROM bill_returns br
        JOIN bills b ON br.bill_id = b.id
        LEFT JOIN products p ON br.product_id = p.id
        WHERE b.deleted_at IS NULL";
$params = [];

if (!empty($typeFilter) && in_array($typeFilter, ['due_deduct', 'cash_refund'])) {
    $sql .= " AND br.refund_type = ?";
    $params[] = $typeFilter;
}

if (!empty($searchFilter)) {
    $sql .= " AND (b.bill_number LIKE ? OR b.customer_name LIKE ? OR br.product_name LIKE ? OR br.reason LIKE ?)";
    $term = "%{$searchFilter}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$sql .= " ORDER BY br.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$returns = $stmt->fetchAll();

// KPI Summary Aggregates
$kpiStmt = $pdo->query("SELECT 
    COUNT(br.id) as total_return_count,
    COALESCE(SUM(br.quantity), 0) as total_returned_qty,
    COALESCE(SUM(br.total_refund), 0) as total_refunded_amt,
    COALESCE(SUM(CASE WHEN br.refund_type = 'due_deduct' THEN br.total_refund ELSE 0 END), 0) as total_due_deducted,
    COALESCE(SUM(CASE WHEN br.refund_type = 'cash_refund' THEN br.total_refund ELSE 0 END), 0) as total_cash_refunded
FROM bill_returns br
JOIN bills b ON br.bill_id = b.id
WHERE b.deleted_at IS NULL");
$kpi = $kpiStmt->fetch();

$totalReturnsCount = (int) ($kpi['total_return_count'] ?? 0);
$totalReturnedQty = (int) ($kpi['total_returned_qty'] ?? 0);
$totalRefundedAmt = (float) ($kpi['total_refunded_amt'] ?? 0.0);
$totalDueDeducted = (float) ($kpi['total_due_deducted'] ?? 0.0);
$totalCashRefunded = (float) ($kpi['total_cash_refunded'] ?? 0.0);

// Recent bills available to return goods from (for quick modal)
$activeBillsStmt = $pdo->query("SELECT id, bill_number, customer_name, customer_phone, grand_total, created_at 
    FROM bills 
    WHERE deleted_at IS NULL 
    ORDER BY id DESC LIMIT 50");
$recentBills = $activeBillsStmt->fetchAll();

$pageTitle = 'Return Bills - MOMAI PLYWOOD';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/topbar.php';
?>

<div x-data="{ selectBillModal: false, searchBillText: '' }" class="space-y-6">

    <!-- Header & Quick Action -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-gray-200/80 shadow-2xs">
        <div class="flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-2xl flex items-center justify-center shadow-xs shrink-0"
                 style="background-color: #fff7ed; border: 1px solid #fed7aa; color: #c2410c;">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                </svg>
            </div>
            <div>
                <h1 class="text-xl font-bold text-gray-900 tracking-tight">
                    Return Bills & Sales Returns
                </h1>
                <p class="text-xs text-gray-500">
                    Dedicated register for all returned customer goods, inventory stock restoration, and refund adjustments.
                </p>
            </div>
        </div>

        <div>
            <button type="button" @click="selectBillModal = true"
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-full text-white text-xs font-bold shadow-md transition active:scale-[0.98]"
               style="background-color: #b53127; color: #ffffff;">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span>Process Return from Bill</span>
            </button>
        </div>
    </div>

    <!-- 4 Summary KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total Refund Amount -->
        <div class="bg-white rounded-2xl p-4 border border-gray-200/80 shadow-2xs space-y-1">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Total Refund Value</span>
                <span class="p-1.5 rounded-lg bg-rose-50 text-rose-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </span>
            </div>
            <div class="text-xl font-bold text-rose-700 font-mono">
                <?= format_inr($totalRefundedAmt) ?>
            </div>
            <p class="text-[11px] text-gray-400">Total amount deducted or refunded</p>
        </div>

        <!-- Total Items Returned -->
        <div class="bg-white rounded-2xl p-4 border border-gray-200/80 shadow-2xs space-y-1">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Restocked Items</span>
                <span class="p-1.5 rounded-lg bg-emerald-50 text-emerald-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                </span>
            </div>
            <div class="text-xl font-bold text-emerald-800 font-mono">
                <?= number_format($totalReturnedQty) ?> <span class="text-xs font-sans font-medium text-gray-500">Units</span>
            </div>
            <p class="text-[11px] text-gray-400">Products returned back to stock</p>
        </div>

        <!-- Deducted from Customer Due -->
        <div class="bg-white rounded-2xl p-4 border border-gray-200/80 shadow-2xs space-y-1">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Khata Balance Reduced</span>
                <span class="p-1.5 rounded-lg bg-amber-50 text-amber-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </span>
            </div>
            <div class="text-xl font-bold text-amber-900 font-mono">
                <?= format_inr($totalDueDeducted) ?>
            </div>
            <p class="text-[11px] text-gray-400">Deducted from pending customer balance</p>
        </div>

        <!-- Cash Refunded -->
        <div class="bg-white rounded-2xl p-4 border border-gray-200/80 shadow-2xs space-y-1">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Cash Returned</span>
                <span class="p-1.5 rounded-lg bg-blue-50 text-blue-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
            </div>
            <div class="text-xl font-bold text-blue-900 font-mono">
                <?= format_inr($totalCashRefunded) ?>
            </div>
            <p class="text-[11px] text-gray-400">Cash refunded directly to customer</p>
        </div>
    </div>

    <!-- Settlement Filter Tabs -->
    <div class="flex flex-wrap items-center gap-2">
        <span class="text-xs font-bold text-gray-500">Filter Settlement:</span>
        <a href="bill-returns.php"
           class="px-3.5 py-1.5 rounded-full text-xs font-bold transition <?= empty($typeFilter) ? 'bg-[#23382f] text-white shadow-2xs' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50' ?>">
            All Returns (<?= $totalReturnsCount ?>)
        </a>
        <a href="bill-returns.php?type=due_deduct"
           class="px-3.5 py-1.5 rounded-full text-xs font-bold transition <?= ($typeFilter === 'due_deduct') ? 'bg-amber-600 text-white shadow-2xs' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50' ?>">
            Deduct from Due / Khata
        </a>
        <a href="bill-returns.php?type=cash_refund"
           class="px-3.5 py-1.5 rounded-full text-xs font-bold transition <?= ($typeFilter === 'cash_refund') ? 'bg-blue-600 text-white shadow-2xs' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50' ?>">
            Cash Refund
        </a>
    </div>

    <!-- Returns Table Container -->
    <div class="bg-white rounded-2xl border border-gray-200/80 shadow-2xs overflow-hidden">
        <div class="p-4 border-b border-gray-100 flex items-center justify-between gap-4">
            <div>
                <h2 class="text-sm font-bold text-gray-900">
                    Returned Items Ledger
                </h2>
                <p class="text-[11px] text-gray-500">
                    Showing <?= count($returns) ?> transaction logs
                </p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table id="returns-table" class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-gray-600 text-[11px] font-bold uppercase tracking-wider">
                        <th class="py-3 px-3.5">Date & Time</th>
                        <th class="py-3 px-3.5">Bill Number</th>
                        <th class="py-3 px-3.5">Customer</th>
                        <th class="py-3 px-3.5">Returned Product</th>
                        <th class="py-3 px-3.5 text-center">Qty</th>
                        <th class="py-3 px-3.5 text-right">Unit Rate</th>
                        <th class="py-3 px-3.5 text-right">Refund Amount</th>
                        <th class="py-3 px-3.5 text-center">Settlement</th>
                        <th class="py-3 px-3.5">Reason / Note</th>
                        <th class="py-3 px-3.5 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($returns)): ?>
                        <tr>
                            <td colspan="10" class="py-12 text-center text-gray-400">
                                <div class="max-w-xs mx-auto space-y-3">
                                    <div class="w-12 h-12 mx-auto rounded-2xl bg-gray-100 flex items-center justify-center text-gray-400">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                        </svg>
                                    </div>
                                    <p class="text-xs font-semibold text-gray-600">No product returns recorded yet.</p>
                                    <p class="text-[11px] text-gray-400">When customers return items, the stock is automatically restored and recorded here.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($returns as $ret): ?>
                            <tr class="hover:bg-amber-50/20 transition">
                                <!-- Date & Time -->
                                <td class="py-3 px-3.5 text-gray-500 font-mono text-[11px] whitespace-nowrap" data-order="<?= strtotime($ret['created_at']) ?>">
                                    <?= date('d M Y, h:i A', strtotime($ret['created_at'])) ?>
                                </td>

                                <!-- Bill Number -->
                                <td class="py-3 px-3.5 whitespace-nowrap">
                                    <a href="bill-slip.php?id=<?= $ret['bill_id'] ?>"
                                       class="font-mono font-bold text-[#b53127] hover:underline flex items-center gap-1"
                                       title="View Bill Slip">
                                        <span><?= e($ret['bill_number']) ?></span>
                                        <svg class="w-3 h-3 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                    </a>
                                </td>

                                <!-- Customer -->
                                <td class="py-3 px-3.5">
                                    <div class="font-bold text-gray-900"><?= e($ret['b_customer_name']) ?></div>
                                    <?php if (!empty($ret['b_customer_phone'])): ?>
                                        <div class="text-[11px] text-gray-400 font-mono"><?= e($ret['b_customer_phone']) ?></div>
                                    <?php endif; ?>
                                </td>

                                <!-- Product -->
                                <td class="py-3 px-3.5">
                                    <div class="font-semibold text-gray-900"><?= e($ret['product_name']) ?></div>
                                    <?php if (!empty($ret['product_sku'])): ?>
                                        <span class="text-[10px] text-gray-400 font-mono">SKU: <?= e($ret['product_sku']) ?></span>
                                    <?php endif; ?>
                                </td>

                                <!-- Quantity -->
                                <td class="py-3 px-3.5 text-center whitespace-nowrap" data-order="<?= (int)$ret['quantity'] ?>">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-900 border border-amber-200">
                                        <?= (int) $ret['quantity'] ?> Units
                                    </span>
                                </td>

                                <!-- Unit Rate -->
                                <td class="py-3 px-3.5 text-right font-mono text-gray-600 whitespace-nowrap" data-order="<?= (float)$ret['unit_price'] ?>">
                                    ₹<?= number_format((float) $ret['unit_price'], 2) ?>
                                </td>

                                <!-- Refund Amount -->
                                <td class="py-3 px-3.5 text-right font-mono font-bold text-rose-700 whitespace-nowrap" data-order="<?= (float)$ret['total_refund'] ?>">
                                    -₹<?= number_format((float) $ret['total_refund'], 2) ?>
                                </td>

                                <!-- Settlement -->
                                <td class="py-3 px-3.5 text-center whitespace-nowrap">
                                    <?php if ($ret['refund_type'] === 'due_deduct'): ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-900 border border-amber-300 shadow-2xs">
                                            <span>Deduct from Due</span>
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-300 shadow-2xs">
                                            <span>Cash Refund</span>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Reason -->
                                <td class="py-3 px-3.5 text-gray-600 max-w-xs truncate">
                                    <?= e($ret['reason'] ?: 'Customer Sales Return') ?>
                                </td>

                                <!-- Action -->
                                <td class="py-3 px-3.5 text-center whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1.5">
                                        <!-- Open Dedicated Return Bill Slip -->
                                        <a href="return-slip.php?id=<?= $ret['id'] ?>"
                                           class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-rose-50 hover:bg-[#b53127] hover:text-white text-[#b53127] border border-rose-200 text-[11px] font-bold transition shadow-2xs"
                                           title="Open Dedicated Return Bill Slip">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                            <span>Return Slip</span>
                                        </a>

                                        <!-- Secondary link to original sales bill -->
                                        <a href="bill-slip.php?id=<?= $ret['bill_id'] ?>"
                                           class="p-1 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-500 hover:text-gray-800 text-[10px] font-bold transition"
                                           title="View Original Bill Slip">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                            </svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- MODAL: Select Bill to Process Return (Responsive with Pinned Search Bar & Custom Scrollbar) -->
    <div x-show="selectBillModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-5 bg-black/60 backdrop-blur-xs transition-opacity duration-300"
         style="backdrop-filter: blur(4px);">
        <div @click.away="selectBillModal = false"
             class="bg-white rounded-3xl shadow-2xl border border-gray-100 w-full max-w-xl flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-200"
             style="max-height: calc(100vh - 48px); height: min(650px, 90vh); display: flex; flex-direction: column;">
            
            <!-- Modal Header (Fixed / Shrink-0) -->
            <div class="p-4 sm:p-5 border-b border-gray-100 bg-white shrink-0 space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl flex items-center justify-center shadow-xs shrink-0"
                             style="background-color: #fff7ed; border: 1px solid #fed7aa; color: #c2410c;">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-base text-gray-900 leading-tight">Select Bill for Return</h3>
                            <p class="text-[11px] text-gray-500">Pick any bill to return items and restock inventory</p>
                        </div>
                    </div>
                    <button type="button" @click="selectBillModal = false"
                            class="w-8 h-8 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-400 hover:text-gray-700 flex items-center justify-center transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- Prominent Pinned Search Bar -->
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input type="text" x-model="searchBillText"
                           placeholder="Search by Bill Number (e.g. 505), Customer Name, or Phone..."
                           class="w-full pl-10 pr-9 py-2.5 text-xs bg-gray-50 border border-gray-300 rounded-xl focus:bg-white focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition font-medium text-gray-900 outline-none">
                    <button type="button" x-show="searchBillText.length > 0" @click="searchBillText = ''"
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-700">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            <!-- Scrollable Bills List with Custom Visible Scrollbar -->
            <div class="flex-1 overflow-y-auto p-3 sm:p-4 space-y-2.5 bill-modal-scroll"
                 style="overflow-y: auto; flex: 1 1 auto; min-height: 0; scrollbar-width: thin; scrollbar-color: #cbd5e1 #f8fafc;">
                <?php foreach ($recentBills as $rb): ?>
                    <div x-show="!searchBillText || '<?= strtolower(addslashes($rb['bill_number'] . ' ' . $rb['customer_name'] . ' ' . ($rb['customer_phone'] ?? ''))) ?>'.includes(searchBillText.toLowerCase())"
                         class="p-3 sm:p-3.5 rounded-2xl bg-white hover:bg-amber-50/40 border border-gray-200 hover:border-amber-300 transition flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-2xs">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-mono font-bold text-xs text-[#b53127]"><?= e($rb['bill_number']) ?></span>
                                <span class="text-[10px] text-gray-400 font-mono">• <?= date('d M Y, h:i A', strtotime($rb['created_at'])) ?></span>
                            </div>
                            <div class="text-xs font-bold text-gray-900 truncate mt-1">
                                <?= e($rb['customer_name']) ?>
                                <?php if (!empty($rb['customer_phone'])): ?>
                                    <span class="text-gray-500 font-normal font-mono text-[11px] ml-1">(<?= e($rb['customer_phone']) ?>)</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-[11px] font-mono font-bold text-gray-700 mt-0.5">
                                Bill Total: <span class="text-gray-900 font-bold"><?= format_inr((float) $rb['grand_total']) ?></span>
                            </div>
                        </div>

                        <div class="shrink-0 flex items-center justify-end">
                            <a href="bill-slip.php?id=<?= $rb['id'] ?>&action=return"
                               class="w-full sm:w-auto px-3.5 py-2 rounded-xl text-xs font-bold text-white shadow-xs transition active:scale-95 flex items-center justify-center gap-1.5"
                               style="background-color: #b53127; color: #ffffff;">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                                </svg>
                                <span>Return Items →</span>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Zero Search Results State -->
                <div x-show="searchBillText && !Array.from($el.querySelectorAll('[x-show]:not([x-show*=searchBillText])')).some(el => el.style.display !== 'none')"
                     class="py-12 text-center text-gray-400 space-y-1.5">
                    <div class="w-10 h-10 mx-auto rounded-xl bg-gray-100 flex items-center justify-center text-gray-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <p class="text-xs font-semibold text-gray-600">No matching bills found.</p>
                    <p class="text-[11px] text-gray-400">Try typing a different invoice number or customer name.</p>
                </div>
            </div>

            <!-- Modal Footer (Fixed / Shrink-0) -->
            <div class="p-3 sm:p-4 border-t border-gray-100 bg-gray-50 shrink-0 flex items-center justify-between text-xs">
                <a href="bills.php" class="text-gray-600 hover:text-gray-900 font-semibold underline flex items-center gap-1">
                    <span>View All Bills in Ledger</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
                <button type="button" @click="selectBillModal = false"
                        class="px-4 py-2 rounded-xl bg-white hover:bg-gray-100 border border-gray-200 text-gray-700 font-bold transition shadow-xs">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Custom Scrollbar Style for Modal -->
    <style>
        .bill-modal-scroll::-webkit-scrollbar {
            width: 8px;
        }
        .bill-modal-scroll::-webkit-scrollbar-track {
            background: #f8fafc;
            border-radius: 9999px;
        }
        .bill-modal-scroll::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 9999px;
            border: 2px solid #f8fafc;
        }
        .bill-modal-scroll::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
    $(document).ready(function() {
        if ($('#returns-table tbody tr').length > 0 && !$('#returns-table tbody td[colspan]').length) {
            $('#returns-table').DataTable({
                pageLength: 25,
                order: [[0, 'desc']],
                responsive: true,
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search return records, product, reason..."
                }
            });
        }
    });
</script>

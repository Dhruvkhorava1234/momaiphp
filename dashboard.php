<?php
/**
 * Modern High-Impact Dashboard & Business Intelligence
 * MOMAI PLYWOOD - Core PHP
 */
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth_guard.php';

$pdo = get_db();

// 1. Core KPIs
// Total Products
$stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE deleted_at IS NULL");
$totalProducts = (int) $stmt->fetchColumn();

// Total Orders / Bills
$stmt = $pdo->query("SELECT COUNT(*) FROM bills WHERE deleted_at IS NULL");
$totalOrders = (int) $stmt->fetchColumn();

// Total Stock (Units)
$stmt = $pdo->query("SELECT COALESCE(SUM(stock_quantity), 0) FROM products WHERE deleted_at IS NULL");
$totalStock = (int) $stmt->fetchColumn();

// Out of Stock & Low Stock
$stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE deleted_at IS NULL AND stock_quantity <= 0");
$outOfStock = (int) $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE deleted_at IS NULL AND stock_quantity > 0 AND stock_quantity <= min_alert_stock");
$lowStock = (int) $stmt->fetchColumn();

// Total Customers
$stmt = $pdo->query("SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL");
$totalCustomers = (int) $stmt->fetchColumn();

// Total Billed, Revenue & Due
$stmt = $pdo->query("
    SELECT COALESCE(SUM(grand_total), 0) AS total_billed,
           COALESCE(SUM(paid_amount), 0) AS total_revenue,
           COALESCE(SUM(due_amount), 0) AS total_due
    FROM bills 
    WHERE deleted_at IS NULL
");
$billTotals = $stmt->fetch(PDO::FETCH_ASSOC);
$totalBilled = (float) $billTotals['total_billed'];
$totalRevenue = (float) $billTotals['total_revenue'];
$totalDue = (float) $billTotals['total_due'];

// Realization Rate
$realizationRate = $totalBilled > 0 ? round(($totalRevenue / $totalBilled) * 100, 1) : 0;

// Sold Units vs Total Inventory
$stmt = $pdo->query("SELECT COALESCE(SUM(quantity), 0) FROM bill_items WHERE deleted_at IS NULL");
$soldUnits = (int) $stmt->fetchColumn();
$totalUnitsInventory = $soldUnits + $totalStock;
$soldPercentage = $totalUnitsInventory > 0 ? round(($soldUnits / $totalUnitsInventory) * 100) : 0;

// Today's Performance
$todayStmt = $pdo->query("
    SELECT COALESCE(SUM(grand_total), 0) as today_sales,
           COALESCE(SUM(paid_amount), 0) as today_paid,
           COUNT(*) as today_bills
    FROM bills
    WHERE deleted_at IS NULL AND DATE(created_at) = CURDATE()
");
$todayData = $todayStmt->fetch(PDO::FETCH_ASSOC);
$todaySales = (float)($todayData['today_sales'] ?? 0);
$todayPaid = (float)($todayData['today_paid'] ?? 0);
$todayBills = (int)($todayData['today_bills'] ?? 0);

// This Month's Performance
$monthStmt = $pdo->query("
    SELECT COALESCE(SUM(grand_total), 0) as month_sales,
           COALESCE(SUM(paid_amount), 0) as month_paid,
           COUNT(*) as month_bills
    FROM bills
    WHERE deleted_at IS NULL 
      AND YEAR(created_at) = YEAR(CURDATE()) 
      AND MONTH(created_at) = MONTH(CURDATE())
");
$monthData = $monthStmt->fetch(PDO::FETCH_ASSOC);
$monthSales = (float)($monthData['month_sales'] ?? 0);
$monthBills = (int)($monthData['month_bills'] ?? 0);

// Helper function to build daily trend datasets for ApexCharts
function getSalesTrend(PDO $pdo, int $days): array {
    $dateMap = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $dateStr = date('Y-m-d', strtotime("-$i days"));
        $dateMap[$dateStr] = [
            'label' => date('d M', strtotime($dateStr)),
            'sales' => 0.0,
            'paid'  => 0.0,
            'due'   => 0.0,
            'bills' => 0
        ];
    }

    $startDate = date('Y-m-d 00:00:00', strtotime("-".($days - 1)." days"));
    $stmt = $pdo->prepare("
        SELECT DATE(created_at) as bill_date,
               COALESCE(SUM(grand_total), 0) as total_sales,
               COALESCE(SUM(paid_amount), 0) as total_paid,
               COALESCE(SUM(due_amount), 0) as total_due,
               COUNT(*) as bill_count
        FROM bills
        WHERE deleted_at IS NULL AND created_at >= :start_date
        GROUP BY DATE(created_at)
        ORDER BY bill_date ASC
    ");
    $stmt->execute([':start_date' => $startDate]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $bd = $row['bill_date'];
        if (isset($dateMap[$bd])) {
            $dateMap[$bd]['sales'] = (float) $row['total_sales'];
            $dateMap[$bd]['paid']  = (float) $row['total_paid'];
            $dateMap[$bd]['due']   = (float) $row['total_due'];
            $dateMap[$bd]['bills'] = (int) $row['bill_count'];
        }
    }

    return [
        'labels' => array_column($dateMap, 'label'),
        'sales'  => array_column($dateMap, 'sales'),
        'paid'   => array_column($dateMap, 'paid'),
        'due'    => array_column($dateMap, 'due'),
        'bills'  => array_column($dateMap, 'bills'),
    ];
}

$trend7 = getSalesTrend($pdo, 7);
$trend30 = getSalesTrend($pdo, 30);

// Top 5 Products by Sales
$stmt = $pdo->query("
    SELECT product_name, SUM(quantity) as total_qty, SUM(total_price) as total_sales 
    FROM bill_items 
    WHERE deleted_at IS NULL 
    GROUP BY product_name 
    ORDER BY total_sales DESC 
    LIMIT 5
");
$topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
$maxProductSales = 0;
foreach ($topProducts as $item) {
    if ((float)$item['total_sales'] > $maxProductSales) {
        $maxProductSales = (float)$item['total_sales'];
    }
}

// Category Distribution for Stock Breakdown
$catStmt = $pdo->query("
    SELECT COALESCE(NULLIF(TRIM(category), ''), 'General') as category_name,
           COUNT(*) as count,
           COALESCE(SUM(stock_quantity), 0) as total_stock
    FROM products
    WHERE deleted_at IS NULL
    GROUP BY category_name
    ORDER BY total_stock DESC
    LIMIT 5
");
$categoryData = $catStmt->fetchAll(PDO::FETCH_ASSOC);
$catLabels = [];
$catStock = [];
foreach ($categoryData as $cd) {
    $catLabels[] = ucfirst($cd['category_name']);
    $catStock[] = (int)$cd['total_stock'];
}

// Recent 6 Invoices
$stmt = $pdo->query("
    SELECT id, bill_number, customer_name, customer_phone, grand_total, paid_amount, due_amount, payment_status, created_at 
    FROM bills 
    WHERE deleted_at IS NULL 
    ORDER BY id DESC 
    LIMIT 6
");
$recentBills = $stmt->fetchAll(PDO::FETCH_ASSOC);

$currentUser = auth_user();
$userName = $currentUser['name'] ?? 'Admin';

$pageTitle = 'Executive Dashboard - MOMAI PLYWOOD';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/topbar.php';
?>

<!-- Custom Dashboard Micro-Animations & Guaranteed Botanical Styling -->
<style>
    @keyframes dashFadeInUp {
        from {
            opacity: 0;
            transform: translateY(16px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .anim-fade-up {
        animation: dashFadeInUp 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    .anim-delay-1 { animation-delay: 0.05s; }
    .anim-delay-2 { animation-delay: 0.12s; }
    .anim-delay-3 { animation-delay: 0.18s; }
    .anim-delay-4 { animation-delay: 0.24s; }

    .card-hover-lift {
        transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.25s ease;
    }
    .card-hover-lift:hover {
        transform: translateY(-3px);
        box-shadow: 0 16px 32px -8px rgba(27, 43, 36, 0.14), 0 4px 12px -2px rgba(27, 43, 36, 0.06) !important;
    }

    /* GUARANTEED DARK BOTANICAL HERO BANNER */
    .dashboard-hero-banner {
        background: linear-gradient(135deg, #152d21 0%, #1e3d2e 50%, #2b543f 100%) !important;
        color: #ffffff !important;
        border-radius: 1.5rem !important;
        box-shadow: 0 20px 45px -12px rgba(21, 45, 33, 0.5), 0 4px 12px rgba(0,0,0,0.08) !important;
        border: 1px solid rgba(255, 255, 255, 0.15) !important;
        position: relative !important;
        overflow: hidden !important;
        padding: 1.75rem 2rem !important;
    }

    .hero-btn-pos {
        background: #ffffff !important;
        color: #152d21 !important;
        font-weight: 800 !important;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.2) !important;
        border: 1px solid rgba(255, 255, 255, 0.9) !important;
        text-decoration: none !important;
        display: inline-flex !important;
        align-items: center !important;
        transition: all 0.2s ease !important;
    }
    .hero-btn-pos:hover {
        background: #ecfdf5 !important;
        color: #0d2117 !important;
        transform: translateY(-1px) scale(1.02) !important;
        box-shadow: 0 8px 22px rgba(0, 0, 0, 0.25) !important;
    }

    .hero-btn-glass {
        background: rgba(255, 255, 255, 0.14) !important;
        color: #ffffff !important;
        border: 1px solid rgba(255, 255, 255, 0.25) !important;
        backdrop-filter: blur(8px) !important;
        -webkit-backdrop-filter: blur(8px) !important;
        text-decoration: none !important;
        display: inline-flex !important;
        align-items: center !important;
        transition: all 0.2s ease !important;
    }
    .hero-btn-glass:hover {
        background: rgba(255, 255, 255, 0.25) !important;
        color: #ffffff !important;
        transform: translateY(-1px) !important;
    }

    .hero-system-pill {
        background: rgba(255, 255, 255, 0.12) !important;
        border: 1px solid rgba(255, 255, 255, 0.2) !important;
        color: #a7f3d0 !important;
    }

    .hero-kpi-label {
        color: #a7f3d0 !important;
        opacity: 0.85 !important;
        font-size: 0.6875rem !important;
        font-weight: 600 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.05em !important;
    }
    .hero-kpi-val {
        color: #ffffff !important;
        font-weight: 800 !important;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace !important;
    }
    .hero-kpi-sub {
        color: #6ee7b7 !important;
        font-size: 0.625rem !important;
        margin-top: 0.125rem !important;
    }

    /* Card Gradient Accent Tops */
    .card-top-emerald { background: linear-gradient(90deg, #059669, #10b981) !important; }
    .card-top-teal    { background: linear-gradient(90deg, #0d9488, #06b6d4) !important; }
    .card-top-rose    { background: linear-gradient(90deg, #e11d48, #f59e0b) !important; }
    .card-top-amber   { background: linear-gradient(90deg, #d97706, #10b981) !important; }

    /* Range Toggle Buttons */
    .btn-range-active {
        background: #152d21 !important;
        color: #ffffff !important;
        box-shadow: 0 2px 6px rgba(21, 45, 33, 0.3) !important;
    }
    .btn-range-inactive {
        background: transparent !important;
        color: #4b5563 !important;
    }
    .btn-range-inactive:hover {
        color: #111827 !important;
        background: rgba(0, 0, 0, 0.04) !important;
    }
</style>

<div class="space-y-6 pb-8" x-data="{ chartRange: '7d' }">

    <!-- HERO SECTION: High-Aesthetic Welcome Banner -->
    <div class="dashboard-hero-banner anim-fade-up anim-delay-1">
        <!-- Ambient Decorative Glows -->
        <div class="absolute -right-16 -top-16 w-64 h-64 rounded-full pointer-events-none" style="background: radial-gradient(circle, rgba(52, 211, 153, 0.22) 0%, rgba(0,0,0,0) 70%);"></div>
        <div class="absolute -left-12 -bottom-12 w-48 h-48 rounded-full pointer-events-none" style="background: radial-gradient(circle, rgba(20, 184, 166, 0.2) 0%, rgba(0,0,0,0) 70%);"></div>
        <div class="absolute right-1/3 top-1/2 w-40 h-40 rounded-full pointer-events-none" style="background: radial-gradient(circle, rgba(251, 191, 36, 0.1) 0%, rgba(0,0,0,0) 70%);"></div>

        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="space-y-2">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full hero-system-pill text-[11px] font-medium tracking-wide">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                    <span class="font-bold text-emerald-200">MOMAI PLYWOOD ERP</span>
                    <span class="text-white/40">•</span>
                    <span class="text-white">Live POS & Inventory Active</span>
                </div>
                
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white flex items-center gap-2" style="color: #ffffff !important;">
                    <span>Namaste, <?= e($userName) ?></span>
                    <span class="text-xl sm:text-2xl">🌿</span>
                </h1>
                
                <p class="text-xs sm:text-sm max-w-xl leading-relaxed" style="color: #d1fae5 !important; opacity: 0.9;">
                    Here is what is happening across your showroom today. Real-time billing, stock valuation, and customer khata tracking.
                </p>
            </div>

            <!-- Quick Action Buttons with guaranteed high contrast -->
            <div class="flex flex-wrap items-center gap-2.5 sm:gap-3 shrink-0">
                <a href="bill-create.php"
                   class="hero-btn-pos px-5 py-3 rounded-2xl text-xs font-extrabold gap-2 group">
                    <div class="w-5 h-5 rounded-lg flex items-center justify-center transition-transform group-hover:scale-110" style="background: #152d21; color: #ffffff;">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                        </svg>
                    </div>
                    <span style="color: #152d21 !important; font-weight: 800 !important;">New Bill (POS)</span>
                </a>

                <a href="products.php"
                   class="hero-btn-glass px-4 py-3 rounded-2xl text-xs font-bold gap-2">
                    <svg class="w-4 h-4 text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                    <span style="color: #ffffff !important;">Inventory</span>
                </a>

                <a href="bills.php"
                   class="hero-btn-glass px-4 py-3 rounded-2xl text-xs font-bold gap-2">
                    <svg class="w-4 h-4 text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span style="color: #ffffff !important;">All Bills</span>
                </a>
            </div>
        </div>

        <!-- Mini Today Metric Highlights Bar -->
        <div class="mt-6 pt-5 border-t grid grid-cols-2 sm:grid-cols-4 gap-4" style="border-color: rgba(255, 255, 255, 0.15) !important;">
            <div>
                <span class="hero-kpi-label block">Today's Sales</span>
                <strong class="hero-kpi-val text-base sm:text-lg block mt-0.5">
                    <?= format_inr($todaySales) ?>
                </strong>
                <span class="hero-kpi-sub block"><?= $todayBills ?> invoices today</span>
            </div>
            <div>
                <span class="hero-kpi-label block">Month-To-Date</span>
                <strong class="hero-kpi-val text-base sm:text-lg block mt-0.5">
                    <?= format_inr($monthSales) ?>
                </strong>
                <span class="hero-kpi-sub block"><?= $monthBills ?> invoices this month</span>
            </div>
            <div>
                <span class="hero-kpi-label block">Cash Realization</span>
                <strong class="hero-kpi-val text-base sm:text-lg block mt-0.5" style="color: #a7f3d0 !important;">
                    <?= $realizationRate ?>%
                </strong>
                <span class="hero-kpi-sub block">Payment collected</span>
            </div>
            <div>
                <span class="hero-kpi-label block">Customers Count</span>
                <strong class="hero-kpi-val text-base sm:text-lg block mt-0.5">
                    <?= number_format($totalCustomers) ?>
                </strong>
                <span class="hero-kpi-sub block">Registered accounts</span>
            </div>
        </div>
    </div>

    <!-- INVENTORY ALERTS BANNER (Only displays when items need replenishment) -->
    <?php if ($outOfStock > 0 || $lowStock > 0): ?>
        <div class="rounded-2xl p-4 bg-gradient-to-r from-amber-500/10 via-rose-500/10 to-amber-500/5 border border-amber-300/60 shadow-xs flex flex-wrap items-center justify-between gap-4 anim-fade-up anim-delay-2">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-900 flex items-center justify-center shrink-0 shadow-2xs">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div>
                    <h4 class="text-xs sm:text-sm font-bold text-gray-800">
                        Attention Needed: Stock Health Alert
                    </h4>
                    <p class="text-xs text-gray-600">
                        <?php if ($outOfStock > 0): ?>
                            <span class="font-bold text-rose-700"><?= $outOfStock ?> item(s)</span> are completely out of stock.
                        <?php endif; ?>
                        <?php if ($lowStock > 0): ?>
                            <span class="font-bold text-amber-700"><?= $lowStock ?> item(s)</span> are running critically low.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <a href="reports.php" class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold transition shadow-xs flex items-center gap-1.5">
                <span>View Low Stock Items</span>
                <span>→</span>
            </a>
        </div>
    <?php endif; ?>

    <!-- 4 EXECUTIVE METRIC CARDS WITH ANIMATED COUNTERS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 anim-fade-up anim-delay-2">
        
        <!-- CARD 1: Total Billed Revenue -->
        <div class="relative overflow-hidden bg-white rounded-3xl p-5 border border-gray-200/80 shadow-xs card-hover-lift group">
            <div class="absolute top-0 left-0 right-0 h-1.5 card-top-emerald"></div>
            
            <div class="flex items-start justify-between">
                <div class="space-y-1">
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Total Sales Billed</span>
                    <div class="text-2xl sm:text-3xl font-black font-mono text-gray-900 tracking-tight"
                         data-counter-currency="<?= $totalBilled ?>">
                        <?= format_inr($totalBilled) ?>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-700 group-hover:scale-110 transition-transform">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>

            <div class="mt-4 pt-3 border-t border-gray-100 flex items-center justify-between text-xs">
                <span class="text-gray-500 font-medium">All Time Ledger</span>
                <span class="inline-flex items-center gap-1 font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-100">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                    <?= $totalOrders ?> Invoices
                </span>
            </div>
        </div>

        <!-- CARD 2: Cash Realized / Collected -->
        <div class="relative overflow-hidden bg-white rounded-3xl p-5 border border-gray-200/80 shadow-xs card-hover-lift group">
            <div class="absolute top-0 left-0 right-0 h-1.5 card-top-teal"></div>
            
            <div class="flex items-start justify-between">
                <div class="space-y-1">
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Collected Cash</span>
                    <div class="text-2xl sm:text-3xl font-black font-mono text-emerald-800 tracking-tight"
                         data-counter-currency="<?= $totalRevenue ?>">
                        <?= format_inr($totalRevenue) ?>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-teal-50 border border-teal-100 flex items-center justify-center text-teal-700 group-hover:scale-110 transition-transform">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>

            <div class="mt-4 pt-3 border-t border-gray-100 flex items-center justify-between text-xs">
                <span class="text-gray-500 font-medium">Realization Rate</span>
                <span class="inline-flex items-center font-bold text-teal-800 bg-teal-50 px-2 py-0.5 rounded-md border border-teal-100">
                    <?= $realizationRate ?>% Realized
                </span>
            </div>
        </div>

        <!-- CARD 3: Pending Khata / Customer Dues -->
        <div class="relative overflow-hidden bg-white rounded-3xl p-5 border border-gray-200/80 shadow-xs card-hover-lift group">
            <div class="absolute top-0 left-0 right-0 h-1.5 card-top-rose"></div>
            
            <div class="flex items-start justify-between">
                <div class="space-y-1">
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Pending Dues (Khata)</span>
                    <div class="text-2xl sm:text-3xl font-black font-mono text-rose-700 tracking-tight"
                         data-counter-currency="<?= $totalDue ?>">
                        <?= format_inr($totalDue) ?>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-rose-50 border border-rose-100 flex items-center justify-center text-rose-700 group-hover:scale-110 transition-transform">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>

            <div class="mt-4 pt-3 border-t border-gray-100 flex items-center justify-between text-xs">
                <span class="text-gray-500 font-medium">Customer Credit</span>
                <a href="bills.php" class="font-bold text-rose-700 hover:text-rose-900 underline flex items-center gap-0.5">
                    <span>Collect Due</span>
                    <span>→</span>
                </a>
            </div>
        </div>

        <!-- CARD 4: Total Inventory Stock -->
        <div class="relative overflow-hidden bg-white rounded-3xl p-5 border border-gray-200/80 shadow-xs card-hover-lift group">
            <div class="absolute top-0 left-0 right-0 h-1.5 card-top-amber"></div>
            
            <div class="flex items-start justify-between">
                <div class="space-y-1">
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Inventory Stock</span>
                    <div class="text-2xl sm:text-3xl font-black font-mono text-gray-900 tracking-tight">
                        <span data-counter-number="<?= $totalStock ?>"><?= number_format($totalStock) ?></span>
                        <span class="text-xs font-semibold text-gray-400">units</span>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-amber-50 border border-amber-100 flex items-center justify-center text-amber-800 group-hover:scale-110 transition-transform">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                </div>
            </div>

            <div class="mt-4 pt-3 border-t border-gray-100 flex items-center justify-between text-xs">
                <span class="text-gray-500 font-medium"><?= number_format($totalProducts) ?> Product SKUs</span>
                <span class="font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-100">
                    <?= $soldUnits ?> Dispatched
                </span>
            </div>
        </div>
    </div>

    <!-- MAIN ANALYTICS SECTION (12 COLUMNS GRID) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start anim-fade-up anim-delay-3">
        
        <!-- LEFT COLUMN (8 Columns): Sales Spline Area Chart + Top Items -->
        <div class="lg:col-span-8 space-y-6">
            
            <!-- INTERACTIVE GRAPH 1: Sales & Collection Dynamics Area Chart -->
            <div class="bg-white rounded-3xl p-5 sm:p-6 border border-gray-200/80 shadow-xs space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-extrabold text-base sm:text-lg text-gray-800 tracking-tight">
                                Sales & Collection Trends
                            </h3>
                            <span class="px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-800 text-[10px] font-bold uppercase tracking-wider border border-emerald-200">
                                Dynamic
                            </span>
                        </div>
                        <p class="text-xs text-gray-400 mt-0.5">
                            Daily gross turnover versus actual realized collections in ₹ INR
                        </p>
                    </div>

                    <!-- Filter Switcher -->
                    <div class="flex items-center bg-gray-100 p-1 rounded-xl border border-gray-200 text-xs font-semibold self-start sm:self-auto">
                        <button type="button"
                                @click="chartRange = '7d'; updateTrendChart('7d')"
                                :class="chartRange === '7d' ? 'btn-range-active' : 'btn-range-inactive'"
                                class="px-3 py-1.5 rounded-lg transition font-bold">
                            Last 7 Days
                        </button>
                        <button type="button"
                                @click="chartRange = '30d'; updateTrendChart('30d')"
                                :class="chartRange === '30d' ? 'btn-range-active' : 'btn-range-inactive'"
                                class="px-3 py-1.5 rounded-lg transition font-bold">
                            Last 30 Days
                        </button>
                    </div>
                </div>

                <!-- ApexChart Container -->
                <div class="w-full relative min-h-[320px]">
                    <div id="sales-trend-chart" class="w-full"></div>
                </div>

                <!-- Chart Footer Summary Indicators -->
                <div class="pt-3 border-t border-gray-100 flex flex-wrap items-center justify-between gap-4 text-xs">
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-1.5">
                            <span class="w-3 h-3 rounded-full bg-[#233f31]"></span>
                            <span class="text-gray-600 font-medium">Gross Billed Sales</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="w-3 h-3 rounded-full bg-[#10b981]"></span>
                            <span class="text-gray-600 font-medium">Realized Cash</span>
                        </div>
                    </div>
                    <div class="text-gray-400 text-[11px]">
                        Auto-updates with every invoice generated
                    </div>
                </div>
            </div>

            <!-- TOP PERFORMING PRODUCTS LEADERBOARD -->
            <div class="bg-white rounded-3xl p-5 sm:p-6 border border-gray-200/80 shadow-xs space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="font-extrabold text-base text-gray-800 tracking-tight">
                            Top Performing Goods & Plywood
                        </h3>
                        <p class="text-xs text-gray-400 mt-0.5">Ranked by total revenue generated</p>
                    </div>
                    <a href="products.php" class="text-xs font-bold hover:underline flex items-center gap-1" style="color: #152d21;">
                        <span>All Products</span>
                        <span>→</span>
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead>
                            <tr class="text-gray-400 border-b border-gray-100 font-bold uppercase tracking-wider text-[10px]">
                                <th class="pb-3 w-10">Rank</th>
                                <th class="pb-3">Product Name</th>
                                <th class="pb-3 text-center">Units Sold</th>
                                <th class="pb-3 text-right">Revenue Generated</th>
                                <th class="pb-3 w-32 text-right">Share</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100/70">
                            <?php if (!empty($topProducts)): ?>
                                <?php foreach ($topProducts as $idx => $prod): ?>
                                    <?php 
                                        $sharePct = $maxProductSales > 0 ? round(((float)$prod['total_sales'] / $maxProductSales) * 100) : 0;
                                    ?>
                                    <tr class="hover:bg-gray-50/80 transition group">
                                        <td class="py-3.5">
                                            <span class="w-6 h-6 rounded-full flex items-center justify-center font-mono font-bold text-[11px] <?= $idx === 0 ? 'bg-amber-100 text-amber-900 font-extrabold ring-2 ring-amber-300' : ($idx === 1 ? 'bg-slate-200 text-slate-800' : ($idx === 2 ? 'bg-amber-50 text-amber-800' : 'bg-gray-100 text-gray-600')) ?>">
                                                #<?= $idx + 1 ?>
                                            </span>
                                        </td>
                                        <td class="py-3.5 font-bold text-gray-900 group-hover:text-emerald-800 transition">
                                            <div class="flex items-center gap-2">
                                                <span><?= e($prod['product_name']) ?></span>
                                            </div>
                                        </td>
                                        <td class="py-3.5 text-center font-mono">
                                            <span class="px-2.5 py-1 rounded-lg bg-gray-100 text-gray-800 font-bold text-xs">
                                                <?= number_format((float)$prod['total_qty']) ?> pcs
                                            </span>
                                        </td>
                                        <td class="py-3.5 text-right font-mono font-bold text-emerald-900 text-xs">
                                            <?= format_inr((float)$prod['total_sales']) ?>
                                        </td>
                                        <td class="py-3.5 text-right">
                                            <div class="w-full bg-gray-100 h-2 rounded-full overflow-hidden">
                                                <div class="h-full rounded-full transition-all duration-700"
                                                     style="background: linear-gradient(90deg, #152d21, #10b981); width: <?= max(5, $sharePct) ?>%;"></div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="py-8 text-center text-gray-400">
                                        No sales records recorded yet. Create an invoice to view leaderboard analytics.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN (4 Columns): Financial Donut + Category Breakdown + Recent Invoices -->
        <div class="lg:col-span-4 space-y-6">
            
            <!-- GRAPH 2: Financial Realization Donut Chart -->
            <div class="bg-white rounded-3xl p-5 sm:p-6 border border-gray-200/80 shadow-xs space-y-4">
                <div>
                    <h3 class="font-extrabold text-base text-gray-800 tracking-tight">
                        Cash Realization vs Khata
                    </h3>
                    <p class="text-xs text-gray-400 mt-0.5">Realized payments vs outstanding balance</p>
                </div>

                <div class="relative flex justify-center items-center py-2">
                    <div id="payment-donut-chart" class="w-full"></div>
                </div>

                <div class="grid grid-cols-2 gap-3 pt-3 border-t border-gray-100 text-xs">
                    <div class="p-2.5 rounded-2xl bg-emerald-50/70 border border-emerald-100">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-800 block">Collected Cash</span>
                        <strong class="font-mono text-sm text-emerald-900 block mt-1">
                            <?= format_inr($totalRevenue) ?>
                        </strong>
                        <span class="text-[10px] text-emerald-700"><?= $realizationRate ?>% of billed</span>
                    </div>

                    <div class="p-2.5 rounded-2xl bg-rose-50/70 border border-rose-100">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-rose-800 block">Pending Due</span>
                        <strong class="font-mono text-sm text-rose-900 block mt-1">
                            <?= format_inr($totalDue) ?>
                        </strong>
                        <span class="text-[10px] text-rose-700"><?= $totalBilled > 0 ? round(($totalDue / $totalBilled) * 100, 1) : 0 ?>% of billed</span>
                    </div>
                </div>
            </div>

            <!-- GRAPH 3: Category Stock Distribution (Radial/Bar Chart) -->
            <?php if (!empty($catLabels)): ?>
                <div class="bg-white rounded-3xl p-5 sm:p-6 border border-gray-200/80 shadow-xs space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-extrabold text-base text-gray-800 tracking-tight">
                                Stock by Category
                            </h3>
                            <p class="text-xs text-gray-400 mt-0.5">Available units in inventory</p>
                        </div>
                        <span class="text-xs font-mono font-bold text-gray-500">
                            <?= number_format($totalStock) ?> units
                        </span>
                    </div>

                    <div id="category-stock-chart" class="w-full"></div>
                </div>
            <?php endif; ?>

            <!-- RECENT TRANSACTIONS STREAM -->
            <div class="bg-white rounded-3xl p-5 sm:p-6 border border-gray-200/80 shadow-xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                    <div>
                        <h3 class="font-extrabold text-base text-gray-800 tracking-tight">
                            Recent Invoices
                        </h3>
                        <p class="text-xs text-gray-400 mt-0.5">Latest customer bills</p>
                    </div>
                    <a href="bills.php" class="text-xs font-bold hover:underline flex items-center gap-1" style="color: #152d21;">
                        <span>View All</span>
                        <span>→</span>
                    </a>
                </div>

                <div class="space-y-3">
                    <?php if (!empty($recentBills)): ?>
                        <?php foreach ($recentBills as $bill): ?>
                            <div class="p-3.5 rounded-2xl border border-gray-100 hover:border-emerald-200/80 bg-gray-50/50 hover:bg-emerald-50/20 transition-all flex items-center justify-between gap-3 shadow-2xs group">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <a href="bill-slip.php?id=<?= $bill['id'] ?>" class="font-mono text-xs font-bold hover:underline" style="color: #152d21;">
                                            <?= e($bill['bill_number']) ?>
                                        </a>
                                        <span class="px-2 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider <?= $bill['payment_status'] === 'paid' ? 'bg-emerald-100 text-emerald-900 border border-emerald-200' : ($bill['payment_status'] === 'partial' ? 'bg-amber-100 text-amber-900 border border-amber-200' : 'bg-rose-100 text-rose-900 border border-rose-200') ?>">
                                            <?= ucfirst($bill['payment_status']) ?>
                                        </span>
                                    </div>
                                    <div class="text-xs font-semibold text-gray-800 mt-1 truncate">
                                        <?= e($bill['customer_name']) ?>
                                    </div>
                                    <div class="text-[10px] text-gray-400 font-mono mt-0.5">
                                        <?= date('d M Y, h:i A', strtotime($bill['created_at'])) ?>
                                    </div>
                                </div>
                                <div class="text-right shrink-0">
                                    <div class="font-mono font-extrabold text-xs text-gray-900">
                                        <?= format_inr((float)$bill['grand_total']) ?>
                                    </div>
                                    <?php if ((float)$bill['due_amount'] > 0): ?>
                                        <div class="text-[10px] font-mono text-rose-600 font-bold mt-0.5">
                                            Due: <?= format_inr((float)$bill['due_amount']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <a href="bill-slip.php?id=<?= $bill['id'] ?>" class="inline-block text-[10px] font-bold text-gray-400 group-hover:text-emerald-800 transition mt-1">
                                        View Slip ↗
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="py-8 text-center text-gray-400 text-xs">
                            No recent invoices recorded.
                        </div>
                    <?php endif; ?>
                </div>

                <a href="bill-create.php"
                   class="w-full py-3 px-4 rounded-2xl text-white text-xs font-bold flex items-center justify-center gap-2 shadow-sm hover:shadow-md transition active:scale-95"
                   style="background: #152d21 !important; color: #ffffff !important;">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span style="color: #ffffff !important;">Create New Invoice (POS)</span>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- JAVASCRIPT: APEXCHARTS INTEGRATION & COUNTER ANIMATIONS -->
<script>
document.addEventListener('DOMContentLoaded', function() {

    // 1. ANIMATED NUMBER COUNTERS
    function animateValue(obj, start, end, duration, isCurrency = false) {
        let startTimestamp = null;
        const step = (timestamp) => {
            if (!startTimestamp) startTimestamp = timestamp;
            const progress = Math.min((timestamp - startTimestamp) / duration, 1);
            // Ease out cubic
            const easeProgress = 1 - Math.pow(1 - progress, 3);
            const current = start + (end - start) * easeProgress;
            
            if (isCurrency) {
                obj.innerHTML = '₹' + current.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            } else {
                obj.innerHTML = Math.floor(current).toLocaleString('en-IN');
            }

            if (progress < 1) {
                window.requestAnimationFrame(step);
            } else {
                if (isCurrency) {
                    obj.innerHTML = '₹' + end.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                } else {
                    obj.innerHTML = Math.floor(end).toLocaleString('en-IN');
                }
            }
        };
        window.requestAnimationFrame(step);
    }

    // Run counters for currency elements
    document.querySelectorAll('[data-counter-currency]').forEach(el => {
        const val = parseFloat(el.getAttribute('data-counter-currency')) || 0;
        if (val > 0) {
            animateValue(el, 0, val, 1100, true);
        }
    });

    // Run counters for regular number elements
    document.querySelectorAll('[data-counter-number]').forEach(el => {
        const val = parseInt(el.getAttribute('data-counter-number')) || 0;
        if (val > 0) {
            animateValue(el, 0, val, 1000, false);
        }
    });

    // 2. CHART DATASETS (Injected cleanly from PHP)
    const trendData7 = <?= json_encode($trend7, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const trendData30 = <?= json_encode($trend30, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

    const totalRevenue = <?= (float)$totalRevenue ?>;
    const totalDue = <?= (float)$totalDue ?>;
    const totalBilled = <?= (float)$totalBilled ?>;

    const catLabels = <?= json_encode($catLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const catStock = <?= json_encode($catStock, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

    // 3. CHART 1: SALES & COLLECTIONS DYNAMICS (Spline Area)
    let trendOptions = {
        series: [
            {
                name: 'Gross Billed Sales',
                data: trendData7.sales
            },
            {
                name: 'Realized Collections',
                data: trendData7.paid
            }
        ],
        chart: {
            height: 320,
            type: 'area',
            toolbar: { show: false },
            fontFamily: 'Plus Jakarta Sans, sans-serif',
            animations: {
                enabled: true,
                easing: 'easeinout',
                speed: 800,
                animateGradually: {
                    enabled: true,
                    delay: 150
                },
                dynamicAnimation: {
                    enabled: true,
                    speed: 450
                }
            }
        },
        colors: ['#152d21', '#10b981'],
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.45,
                opacityTo: 0.05,
                stops: [0, 95, 100]
            }
        },
        stroke: {
            curve: 'smooth',
            width: [3.5, 2.5]
        },
        markers: {
            size: 4,
            colors: ['#152d21', '#10b981'],
            strokeColors: '#fff',
            strokeWidth: 2,
            hover: { size: 6 }
        },
        dataLabels: { enabled: false },
        xaxis: {
            categories: trendData7.labels,
            axisBorder: { show: false },
            axisTicks: { show: false },
            labels: {
                style: {
                    colors: '#9ca3af',
                    fontSize: '11px',
                    fontWeight: 600
                }
            }
        },
        yaxis: {
            labels: {
                formatter: function(val) {
                    if (val >= 100000) return '₹' + (val / 100000).toFixed(1) + 'L';
                    if (val >= 1000) return '₹' + (val / 1000).toFixed(1) + 'k';
                    return '₹' + Math.round(val);
                },
                style: {
                    colors: '#9ca3af',
                    fontSize: '11px',
                    fontFamily: 'ui-monospace, monospace'
                }
            }
        },
        grid: {
            borderColor: '#f3f4f6',
            strokeDashArray: 4,
            yaxis: { lines: { show: true } },
            xaxis: { lines: { show: false } }
        },
        legend: {
            position: 'top',
            horizontalAlign: 'right',
            fontSize: '12px',
            markers: { radius: 12 }
        },
        tooltip: {
            theme: 'light',
            y: {
                formatter: function(val) {
                    return '₹' + val.toLocaleString('en-IN', { minimumFractionDigits: 2 });
                }
            }
        }
    };

    const trendChart = new ApexCharts(document.querySelector("#sales-trend-chart"), trendOptions);
    trendChart.render();

    // Switcher function between 7d and 30d
    window.updateTrendChart = function(range) {
        if (range === '30d') {
            trendChart.updateOptions({
                xaxis: { categories: trendData30.labels }
            });
            trendChart.updateSeries([
                { name: 'Gross Billed Sales', data: trendData30.sales },
                { name: 'Realized Collections', data: trendData30.paid }
            ]);
        } else {
            trendChart.updateOptions({
                xaxis: { categories: trendData7.labels }
            });
            trendChart.updateSeries([
                { name: 'Gross Billed Sales', data: trendData7.sales },
                { name: 'Realized Collections', data: trendData7.paid }
            ]);
        }
    };

    // 4. CHART 2: PAYMENT REALIZATION DONUT CHART
    const donutValues = (totalRevenue === 0 && totalDue === 0) 
        ? [1] 
        : [totalRevenue, totalDue];
    const donutLabels = (totalRevenue === 0 && totalDue === 0) 
        ? ['No Invoices Yet'] 
        : ['Collected Cash', 'Pending Due (Khata)'];
    const donutColors = (totalRevenue === 0 && totalDue === 0) 
        ? ['#e5e7eb'] 
        : ['#10b981', '#f43f5e'];

    const donutOptions = {
        series: donutValues,
        labels: donutLabels,
        chart: {
            type: 'donut',
            height: 250,
            fontFamily: 'Plus Jakarta Sans, sans-serif',
            animations: {
                enabled: true,
                speed: 900
            }
        },
        colors: donutColors,
        stroke: { width: 3, colors: ['#ffffff'] },
        plotOptions: {
            pie: {
                donut: {
                    size: '72%',
                    labels: {
                        show: true,
                        name: {
                            show: true,
                            fontSize: '12px',
                            fontWeight: 600,
                            color: '#6b7280'
                        },
                        value: {
                            show: true,
                            fontSize: '18px',
                            fontWeight: 800,
                            fontFamily: 'ui-monospace, monospace',
                            color: '#111827',
                            formatter: function(val) {
                                if (totalRevenue === 0 && totalDue === 0) return '₹0';
                                return '₹' + Number(val).toLocaleString('en-IN', { minimumFractionDigits: 0 });
                            }
                        },
                        total: {
                            show: true,
                            showAlways: true,
                            label: 'Total Turnover',
                            fontSize: '11px',
                            fontWeight: 600,
                            color: '#9ca3af',
                            formatter: function(w) {
                                return '₹' + totalBilled.toLocaleString('en-IN', { minimumFractionDigits: 0 });
                            }
                        }
                    }
                }
            }
        },
        dataLabels: { enabled: false },
        legend: { show: false },
        tooltip: {
            theme: 'light',
            y: {
                formatter: function(val) {
                    if (totalRevenue === 0 && totalDue === 0) return '₹0';
                    return '₹' + Number(val).toLocaleString('en-IN', { minimumFractionDigits: 2 });
                }
            }
        }
    };

    const donutChart = new ApexCharts(document.querySelector("#payment-donut-chart"), donutOptions);
    donutChart.render();

    // 5. CHART 3: CATEGORY STOCK DISTRIBUTION BAR/RADIAL
    if (catLabels && catLabels.length > 0 && document.querySelector("#category-stock-chart")) {
        const catOptions = {
            series: [{
                name: 'Stock Units',
                data: catStock
            }],
            chart: {
                type: 'bar',
                height: 180,
                toolbar: { show: false },
                fontFamily: 'Plus Jakarta Sans, sans-serif'
            },
            plotOptions: {
                bar: {
                    borderRadius: 8,
                    horizontal: true,
                    distributed: true,
                    barHeight: '60%'
                }
            },
            colors: ['#233f31', '#2f5341', '#3d6c54', '#528f70', '#88afc2'],
            dataLabels: {
                enabled: true,
                textAnchor: 'start',
                style: {
                    colors: ['#ffffff'],
                    fontSize: '11px',
                    fontWeight: 700,
                    fontFamily: 'ui-monospace, monospace'
                },
                formatter: function(val, opt) {
                    return val + ' pcs';
                },
                offsetX: 0
            },
            xaxis: {
                categories: catLabels,
                labels: { show: false },
                axisBorder: { show: false },
                axisTicks: { show: false }
            },
            yaxis: {
                labels: {
                    style: {
                        colors: '#374151',
                        fontSize: '11px',
                        fontWeight: 600
                    }
                }
            },
            grid: { show: false },
            legend: { show: false },
            tooltip: {
                theme: 'light',
                y: {
                    formatter: function(val) {
                        return val + ' Available Units';
                    }
                }
            }
        };

        const catChart = new ApexCharts(document.querySelector("#category-stock-chart"), catOptions);
        catChart.render();
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

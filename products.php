<?php
/**
 * Products & Inventory Management
 * MOMAI PLYWOOD - Core PHP
 */
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth_guard.php';

$pdo = get_db();

// Handle POST actions (Create, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    // 1. Add Product
    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $companyName = trim($_POST['company_name'] ?? '');
        $sku = trim($_POST['sku'] ?? '');
        $category = trim($_POST['category'] ?? 'General');
        $costPrice = (float) ($_POST['cost_price'] ?? 0);
        $sellingPrice = (float) ($_POST['selling_price'] ?? 0);
        $stockQuantity = (int) ($_POST['stock_quantity'] ?? 0);
        $minAlertStock = (int) ($_POST['min_alert_stock'] ?? 5);
        $unit = trim($_POST['unit'] ?? 'pcs') ?: 'pcs';

        if (empty($name) || $sellingPrice <= 0) {
            flash_set('error', 'Product name and selling price are required.');
        } else {
            if (empty($sku)) {
                $sku = 'SKU-' . strtoupper(substr(uniqid(), -6));
            }

            // Check SKU uniqueness
            $chk = $pdo->prepare("SELECT COUNT(*) FROM products WHERE sku = ? AND deleted_at IS NULL");
            $chk->execute([$sku]);
            if ($chk->fetchColumn() > 0) {
                flash_set('error', 'Product SKU already exists. Please choose a unique SKU.');
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO products (name, company_name, sku, category, cost_price, selling_price, stock_quantity, min_alert_stock, unit, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$name, $companyName ?: null, $sku, $category, $costPrice, $sellingPrice, $stockQuantity, $minAlertStock, $unit]);
                flash_set('success', "Product '{$name}' added successfully!");
            }
        }
        redirect('products.php');
    }

    // 2. Update Product
    if ($action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $companyName = trim($_POST['company_name'] ?? '');
        $sku = trim($_POST['sku'] ?? '');
        $category = trim($_POST['category'] ?? 'General');
        $costPrice = (float) ($_POST['cost_price'] ?? 0);
        $sellingPrice = (float) ($_POST['selling_price'] ?? 0);
        $stockQuantity = (int) ($_POST['stock_quantity'] ?? 0);
        $minAlertStock = (int) ($_POST['min_alert_stock'] ?? 5);
        $unit = trim($_POST['unit'] ?? 'pcs') ?: 'pcs';

        if ($id <= 0 || empty($name) || $sellingPrice <= 0) {
            flash_set('error', 'Invalid product data for updating.');
        } else {
            // Check SKU uniqueness excluding current
            $chk = $pdo->prepare("SELECT COUNT(*) FROM products WHERE sku = ? AND id != ? AND deleted_at IS NULL");
            $chk->execute([$sku, $id]);
            if ($chk->fetchColumn() > 0) {
                flash_set('error', 'SKU is already in use by another product.');
            } else {
                $stmt = $pdo->prepare("
                    UPDATE products 
                    SET name = ?, company_name = ?, sku = ?, category = ?, cost_price = ?, selling_price = ?, stock_quantity = ?, min_alert_stock = ?, unit = ?, updated_at = NOW()
                    WHERE id = ? AND deleted_at IS NULL
                ");
                $stmt->execute([$name, $companyName ?: null, $sku, $category, $costPrice, $sellingPrice, $stockQuantity, $minAlertStock, $unit, $id]);
                flash_set('success', "Product '{$name}' updated successfully!");
            }
        }
        redirect('products.php');
    }

    // 3. Delete Product (Soft Delete)
    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE products SET deleted_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            flash_set('success', 'Product deleted successfully!');
        }
        redirect('products.php');
    }
}

// Fetch filter parameters
$search = trim($_GET['search'] ?? '');
$categoryFilter = trim($_GET['category'] ?? '');
$stockFilter = trim($_GET['stock_status'] ?? '');

$sql = "SELECT * FROM products WHERE deleted_at IS NULL";
$params = [];

if (!empty($search)) {
    $sql .= " AND (name LIKE ? OR company_name LIKE ? OR sku LIKE ? OR category LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if (!empty($categoryFilter)) {
    $sql .= " AND category = ?";
    $params[] = $categoryFilter;
}

if ($stockFilter === 'low') {
    $sql .= " AND stock_quantity > 0 AND stock_quantity <= min_alert_stock";
} elseif ($stockFilter === 'out') {
    $sql .= " AND stock_quantity <= 0";
}

$sql .= " ORDER BY id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Distinct categories for filters
$catStmt = $pdo->query("SELECT DISTINCT category FROM products WHERE deleted_at IS NULL AND category IS NOT NULL ORDER BY category ASC");
$categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Inventory & Stock - MOMAI PLYWOOD';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/topbar.php';
?>

<div x-data="{ addModal: false, stockModal: false, editModal: false, selectedProduct: null, editingProduct: null }"
     @open-stock-modal.window="selectedProduct = $event.detail; stockModal = true"
     @open-edit-modal.window="editingProduct = $event.detail; editModal = true"
     class="space-y-6">
    
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-gray-200/80 shadow-2xs">
        <div>
            <h1 class="text-xl font-bold text-gray-800 tracking-tight">
                Inventory & Stock Management
            </h1>
            <p class="text-xs text-gray-500">
                Real-time DataTable with live search, column sorting, pagination, and stock replenishment.
            </p>
        </div>
        <div class="flex items-center gap-2.5">
            <button type="button"
                    @click="addModal = true"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-[#324b3e] hover:bg-[#23382f] text-white text-xs font-bold shadow-md transition active:scale-[0.98]">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                <span>Add Product</span>
            </button>
        </div>
    </div>

    <!-- Stock Status Quick Filter Pills -->
    <div class="flex flex-wrap items-center gap-2">
        <span class="text-xs font-semibold text-gray-500">Filter Status:</span>
        <a href="products.php"
           class="px-3 py-1 rounded-full text-xs font-semibold transition <?= empty($stockFilter) ? 'bg-[#324b3e] text-white shadow-2xs' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50' ?>">
            All Items
        </a>
        <a href="products.php?stock_status=low"
           class="px-3 py-1 rounded-full text-xs font-semibold transition <?= ($stockFilter === 'low') ? 'bg-amber-600 text-white shadow-2xs' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50' ?>">
            Low Stock
        </a>
        <a href="products.php?stock_status=out"
           class="px-3 py-1 rounded-full text-xs font-semibold transition <?= ($stockFilter === 'out') ? 'bg-rose-600 text-white shadow-2xs' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50' ?>">
            Out of Stock
        </a>
    </div>

    <!-- Products DataTable Card -->
    <div class="bg-white rounded-2xl border border-gray-200/80 shadow-2xs overflow-hidden">
        <div class="p-2 sm:p-4 overflow-x-auto">
            <table id="products-table" class="display responsive nowrap w-full text-left text-xs">
                <thead>
                    <tr>
                        <th>SKU / Code</th>
                        <th>Product Name</th>
                        <th>Company Name</th>
                        <th>Category</th>
                        <th class="text-right">Cost (₹)</th>
                        <th class="text-right">Selling Price (₹)</th>
                        <th class="text-center">Stock Level</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $prod): ?>
                        <tr>
                            <td class="font-mono text-gray-600 font-bold">
                                <?= e($prod['sku']) ?>
                            </td>
                            <td class="font-semibold text-gray-900">
                                <?= e($prod['name']) ?>
                            </td>
                            <td class="text-gray-600 font-medium">
                                <?= e($prod['company_name'] ?: '—') ?>
                            </td>
                            <td>
                                <span class="px-2 py-0.5 rounded-lg bg-gray-100 text-gray-600 text-[11px] font-medium">
                                    <?= e($prod['category']) ?>
                                </span>
                            </td>
                            <td class="text-right font-mono text-gray-500">
                                <?= format_inr((float)$prod['cost_price']) ?>
                            </td>
                            <td class="text-right font-mono font-bold text-gray-900">
                                <?= format_inr((float)$prod['selling_price']) ?>
                            </td>
                            <td class="text-center font-mono font-bold">
                                <span class="<?= $prod['stock_quantity'] <= 0 ? 'text-rose-600 font-black' : ($prod['stock_quantity'] <= $prod['min_alert_stock'] ? 'text-amber-600' : 'text-emerald-700') ?>">
                                    <?= $prod['stock_quantity'] ?> <?= e($prod['unit']) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if ($prod['stock_quantity'] <= 0): ?>
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-200">
                                        Out of Stock
                                    </span>
                                <?php elseif ($prod['stock_quantity'] <= $prod['min_alert_stock']): ?>
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-900 border border-amber-200">
                                        Low Stock
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                        In Stock
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <!-- Adjust Stock Button -->
                                    <button type="button"
                                            class="btn-stock-action p-1.5 rounded-lg bg-[#324b3e]/10 text-[#324b3e] hover:bg-[#324b3e] hover:text-white transition"
                                            data-product="<?= htmlspecialchars(json_encode($prod), ENT_QUOTES, 'UTF-8') ?>"
                                            title="Quick Stock Adjustment">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                        </svg>
                                    </button>

                                    <!-- Edit Button -->
                                    <button type="button"
                                            class="btn-edit-action p-1.5 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200 transition"
                                            data-product="<?= htmlspecialchars(json_encode($prod), ENT_QUOTES, 'UTF-8') ?>"
                                            title="Edit Product">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                        </svg>
                                    </button>

                                    <!-- Delete Button -->
                                    <form method="POST" action="products.php" onsubmit="return confirm('Delete this product permanently?');" class="inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $prod['id'] ?>">
                                        <button type="submit"
                                                class="p-1.5 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white transition"
                                                title="Delete Product">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- MODAL 1: Add Product -->
    <div x-show="addModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
        <div @click.away="addModal = false" class="bg-white rounded-3xl p-6 max-w-lg w-full shadow-2xl border border-gray-100 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                <h3 class="font-bold text-base text-gray-800">Add New Inventory Product</h3>
                <button type="button" @click="addModal = false" class="text-gray-400 hover:text-gray-600">✕</button>
            </div>

            <form method="POST" action="products.php" class="space-y-3.5">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Product Name *</label>
                    <input type="text" name="name" required placeholder="e.g. 18mm Commercial Plywood" class="w-full px-3.5 py-2 rounded-xl text-xs bg-gray-50 border border-gray-300 focus:bg-white focus:border-[#324b3e] outline-none">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Company / Brand</label>
                        <input type="text" name="company_name" placeholder="e.g. Greenply" class="w-full px-3.5 py-2 rounded-xl text-xs bg-gray-50 border border-gray-300 focus:bg-white focus:border-[#324b3e] outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Category *</label>
                        <input type="text" name="category" required value="General" placeholder="Plywood, Hardware..." class="w-full px-3.5 py-2 rounded-xl text-xs bg-gray-50 border border-gray-300 focus:bg-white focus:border-[#324b3e] outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">SKU / Barcode (Optional)</label>
                        <input type="text" name="sku" placeholder="Auto-generated if blank" class="w-full px-3.5 py-2 rounded-xl text-xs bg-gray-50 border border-gray-300 focus:bg-white focus:border-[#324b3e] outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Unit</label>
                        <input type="text" name="unit" value="pcs" placeholder="pcs, sheet, kg" class="w-full px-3.5 py-2 rounded-xl text-xs bg-gray-50 border border-gray-300 focus:bg-white focus:border-[#324b3e] outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Cost Price (₹)</label>
                        <input type="number" step="0.01" name="cost_price" value="0" min="0" class="w-full px-3.5 py-2 rounded-xl text-xs bg-gray-50 border border-gray-300 focus:bg-white focus:border-[#324b3e] outline-none font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Selling Price (₹) *</label>
                        <input type="number" step="0.01" name="selling_price" required min="0" placeholder="0.00" class="w-full px-3.5 py-2 rounded-xl text-xs bg-gray-50 border border-gray-300 focus:bg-white focus:border-[#324b3e] outline-none font-mono">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Initial Stock *</label>
                        <input type="number" name="stock_quantity" value="10" required min="0" class="w-full px-3.5 py-2 rounded-xl text-xs bg-gray-50 border border-gray-300 focus:bg-white focus:border-[#324b3e] outline-none font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Low Stock Alert Level</label>
                        <input type="number" name="min_alert_stock" value="5" min="0" class="w-full px-3.5 py-2 rounded-xl text-xs bg-gray-50 border border-gray-300 focus:bg-white focus:border-[#324b3e] outline-none font-mono">
                    </div>
                </div>

                <div class="pt-3 flex justify-end gap-2">
                    <button type="button" @click="addModal = false" class="px-4 py-2 rounded-xl bg-gray-100 text-gray-600 text-xs font-semibold">Cancel</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-[#324b3e] text-white text-xs font-bold shadow-md hover:bg-[#23382f]">Save Product</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 2: Edit Product -->
    <div x-show="editModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
        <div @click.away="editModal = false" class="bg-white rounded-3xl p-6 max-w-lg w-full shadow-2xl border border-gray-100 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                <h3 class="font-bold text-base text-gray-800">Edit Product</h3>
                <button type="button" @click="editModal = false" class="text-gray-400 hover:text-gray-600">✕</button>
            </div>

            <form method="POST" action="products.php" class="space-y-3.5">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" :value="editingProduct ? editingProduct.id : ''">

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Product Name *</label>
                    <input type="text" name="name" :value="editingProduct ? editingProduct.name : ''" required class="w-full px-3.5 py-2 rounded-xl text-xs bg-gray-50 border border-gray-300 focus:bg-white focus:border-[#324b3e] outline-none">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Company / Brand</label>
                        <input type="text" name="company_name" :value="editingProduct ? editingProduct.company_name : ''" class="w-full px-3.5 py-2 rounded-xl text-xs bg-gray-50 border border-gray-300 focus:bg-white focus:border-[#324b3e] outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Category *</label>
                        <input type="text" name="category" :value="editingProduct ? editingProduct.category : ''" required class="w-full px-3.5 py-2 rounded-xl text-xs bg-gray-50 border border-gray-300 focus:bg-white focus:border-[#324b3e] outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">SKU / Code *</label>
                        <input type="text" name="sku" :value="editingProduct ? editingProduct.sku : ''" required class="w-full px-3.5 py-2 rounded-xl text-xs bg-gray-50 border border-gray-300 focus:bg-white focus:border-[#324b3e] outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Unit</label>
                        <input type="text" name="unit" :value="editingProduct ? editingProduct.unit : 'pcs'" class="w-full px-3.5 py-2 rounded-xl text-xs bg-gray-50 border border-gray-300 focus:bg-white focus:border-[#324b3e] outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Cost Price (₹)</label>
                        <input type="number" step="0.01" name="cost_price" :value="editingProduct ? editingProduct.cost_price : '0'" min="0" class="w-full px-3.5 py-2 rounded-xl text-xs bg-gray-50 border border-gray-300 focus:bg-white focus:border-[#324b3e] outline-none font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Selling Price (₹) *</label>
                        <input type="number" step="0.01" name="selling_price" :value="editingProduct ? editingProduct.selling_price : ''" required min="0" class="w-full px-3.5 py-2 rounded-xl text-xs bg-gray-50 border border-gray-300 focus:bg-white focus:border-[#324b3e] outline-none font-mono">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Stock Quantity</label>
                        <input type="number" name="stock_quantity" :value="editingProduct ? editingProduct.stock_quantity : '0'" required min="0" class="w-full px-3.5 py-2 rounded-xl text-xs bg-gray-50 border border-gray-300 focus:bg-white focus:border-[#324b3e] outline-none font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Low Stock Alert Level</label>
                        <input type="number" name="min_alert_stock" :value="editingProduct ? editingProduct.min_alert_stock : '5'" min="0" class="w-full px-3.5 py-2 rounded-xl text-xs bg-gray-50 border border-gray-300 focus:bg-white focus:border-[#324b3e] outline-none font-mono">
                    </div>
                </div>

                <div class="pt-3 flex justify-end gap-2">
                    <button type="button" @click="editModal = false" class="px-4 py-2 rounded-xl bg-gray-100 text-gray-600 text-xs font-semibold">Cancel</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-[#324b3e] text-white text-xs font-bold shadow-md hover:bg-[#23382f]">Update Product</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 3: Stock Adjustment -->
    <div x-show="stockModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
        <div @click.away="stockModal = false" class="bg-white rounded-3xl p-6 max-w-sm w-full shadow-2xl border border-gray-100 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                <h3 class="font-bold text-base text-gray-800">Quick Stock Adjustment</h3>
                <button type="button" @click="stockModal = false" class="text-gray-400 hover:text-gray-600">✕</button>
            </div>

            <div class="p-3 bg-gray-50 rounded-2xl border border-gray-200 text-xs">
                <div class="font-bold text-gray-900" x-text="selectedProduct ? selectedProduct.name : ''"></div>
                <div class="text-gray-500 mt-0.5">Current Balance: <strong class="text-gray-900" x-text="selectedProduct ? selectedProduct.stock_quantity + ' ' + selectedProduct.unit : ''"></strong></div>
            </div>

            <form method="POST" action="product-adjust-stock.php" class="space-y-4">
                <?= csrf_field() ?>
                <input type="hidden" name="product_id" :value="selectedProduct ? selectedProduct.id : ''">

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Adjustment Action</label>
                    <select name="adjustment_type" class="w-full px-3.5 py-2 rounded-xl text-xs bg-gray-50 border border-gray-300 outline-none">
                        <option value="add">+ Add Stock (Inward Purchase)</option>
                        <option value="subtract">- Subtract Stock (Outward / Damage)</option>
                        <option value="set">= Set Exact Count (Stock Audit)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Quantity</label>
                    <input type="number" name="quantity" required min="0" placeholder="10" class="w-full px-3.5 py-2 rounded-xl text-xs bg-gray-50 border border-gray-300 outline-none font-mono">
                </div>

                <div class="pt-2 flex justify-end gap-2">
                    <button type="button" @click="stockModal = false" class="px-4 py-2 rounded-xl bg-gray-100 text-gray-600 text-xs font-semibold">Cancel</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-[#324b3e] text-white text-xs font-bold shadow-md hover:bg-[#23382f]">Apply Adjustment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        $('#products-table').DataTable({
            pageLength: 25,
            order: [[0, 'desc']],
            responsive: true,
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search inventory items..."
            }
        });

        $(document).on('click', '.btn-stock-action', function(e) {
            e.preventDefault();
            const product = $(this).data('product');
            window.dispatchEvent(new CustomEvent('open-stock-modal', { detail: product }));
        });

        $(document).on('click', '.btn-edit-action', function(e) {
            e.preventDefault();
            const product = $(this).data('product');
            window.dispatchEvent(new CustomEvent('open-edit-modal', { detail: product }));
        });
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

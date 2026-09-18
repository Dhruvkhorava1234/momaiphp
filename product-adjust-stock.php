<?php
/**
 * Handle Stock Adjustment Action (Add, Subtract, Set)
 * MOMAI PLYWOOD - Core PHP
 */
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth_guard.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $productId = (int) ($_POST['product_id'] ?? 0);
    $type = $_POST['adjustment_type'] ?? '';
    $quantity = (int) ($_POST['quantity'] ?? 0);

    if ($productId <= 0 || !in_array($type, ['add', 'subtract', 'set']) || $quantity < 0) {
        flash_set('error', 'Invalid stock adjustment parameters.');
        redirect('products.php');
    }

    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT id, name, stock_quantity FROM products WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product) {
        flash_set('error', 'Product not found.');
        redirect('products.php');
    }

    $currentStock = (int) $product['stock_quantity'];
    $newStock = $currentStock;

    if ($type === 'add') {
        $newStock = $currentStock + $quantity;
    } elseif ($type === 'subtract') {
        $newStock = max(0, $currentStock - $quantity);
    } elseif ($type === 'set') {
        $newStock = $quantity;
    }

    $upd = $pdo->prepare("UPDATE products SET stock_quantity = ?, updated_at = NOW() WHERE id = ?");
    $upd->execute([$newStock, $productId]);

    flash_set('success', "Stock updated for '{$product['name']}'! Previous: {$currentStock}, New: {$newStock}");
    redirect('products.php');
}

redirect('products.php');

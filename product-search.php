<?php
/**
 * AJAX Live Product Search API
 * MOMAI PLYWOOD - Core PHP
 */
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth_guard.php';

header('Content-Type: application/json');

$term = trim($_GET['q'] ?? '');

if (mb_strlen($term) < 2) {
    echo json_encode([]);
    exit;
}

$pdo = get_db();
$query = "SELECT id, name, company_name, sku, category, cost_price, selling_price, stock_quantity, unit, min_alert_stock
          FROM products 
          WHERE deleted_at IS NULL 
            AND (name LIKE ? OR company_name LIKE ? OR sku LIKE ? OR category LIKE ?)
          ORDER BY id DESC 
          LIMIT 15";

$searchTerm = "%{$term}%";
$stmt = $pdo->prepare($query);
$stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
$products = $stmt->fetchAll();

echo json_encode($products);
exit;

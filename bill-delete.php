<?php
/**
 * Bill Soft Delete Handler
 * Restores product stock, adjusts customer balance, and soft-deletes the bill
 * MOMAI PLYWOOD - Core PHP
 */
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth_guard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('bills.php');
}

csrf_verify();

$billId = (int) ($_POST['bill_id'] ?? 0);

if ($billId <= 0) {
    flash_set('error', 'Invalid bill ID.');
    redirect('bills.php');
}

$pdo = get_db();

try {
    $pdo->beginTransaction();

    // 1. Fetch bill and lock for update
    $stmt = $pdo->prepare("SELECT * FROM bills WHERE id = ? AND deleted_at IS NULL FOR UPDATE");
    $stmt->execute([$billId]);
    $bill = $stmt->fetch();

    if (!$bill) {
        $pdo->rollBack();
        flash_set('error', 'Bill not found or already deleted.');
        redirect('bills.php');
    }

    // 2. Fetch bill items to restore product inventory stock
    $itemStmt = $pdo->prepare("SELECT * FROM bill_items WHERE bill_id = ? AND deleted_at IS NULL");
    $itemStmt->execute([$billId]);
    $items = $itemStmt->fetchAll();

    foreach ($items as $item) {
        $remainingQty = max(0, (int) $item['quantity'] - (int) ($item['returned_quantity'] ?? 0));
        if (!empty($item['product_id']) && $remainingQty > 0) {
            $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity + ?, updated_at = NOW() WHERE id = ?")
                ->execute([$remainingQty, (int) $item['product_id']]);
        }
        // Soft delete the bill item
        $pdo->prepare("UPDATE bill_items SET deleted_at = NOW() WHERE id = ?")
            ->execute([$item['id']]);
    }

    // 3. Deduct outstanding due from customer's total_due (if customer linked and due was active)
    if (!empty($bill['customer_id']) && (float)$bill['due_amount'] > 0) {
        $pdo->prepare("UPDATE customers SET total_due = GREATEST(0, total_due - ?), updated_at = NOW() WHERE id = ?")
            ->execute([(float)$bill['due_amount'], $bill['customer_id']]);
    }

    // 4. Soft delete bill payments
    $pdo->prepare("UPDATE bill_payments SET deleted_at = NOW() WHERE bill_id = ? AND deleted_at IS NULL")
        ->execute([$billId]);

    // 5. Soft delete the bill
    $pdo->prepare("UPDATE bills SET deleted_at = NOW(), updated_at = NOW() WHERE id = ?")
        ->execute([$billId]);

    $pdo->commit();

    // Recalculate totals for AJAX updates
    $totStmt = $pdo->query("SELECT COALESCE(SUM(grand_total), 0) AS billed, COALESCE(SUM(paid_amount), 0) AS collected, COALESCE(SUM(due_amount), 0) AS due FROM bills WHERE deleted_at IS NULL");
    $totals = $totStmt->fetch();

    $message = "Bill '{$bill['bill_number']}' was deleted successfully (stock restored & due adjusted).";
    
    // Check if AJAX / JSON request
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
           || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => $message,
            'bill_number' => $bill['bill_number'],
            'totals' => [
                'billed' => format_inr((float)$totals['billed']),
                'collected' => format_inr((float)$totals['collected']),
                'due' => format_inr((float)$totals['due'])
            ]
        ]);
        exit;
    }

    flash_set('success', $message);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $errMsg = 'Failed to delete bill: ' . $e->getMessage();
    
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
           || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

    if ($isAjax) {
        header('Content-Type: application/json', true, 500);
        echo json_encode(['success' => false, 'message' => $errMsg]);
        exit;
    }

    flash_set('error', $errMsg);
}

redirect('bills.php');

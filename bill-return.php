<?php
/**
 * Handle Sales Return / Bill Item Return
 * Restores product stock, logs return records, reduces bill amount,
 * and adjusts customer Khata balance due.
 * MOMAI PLYWOOD - Core PHP
 */
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth_guard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('bills.php');
}

csrf_verify();

$billId = (int) ($_POST['bill_id'] ?? 0);
$returns = $_POST['returns'] ?? []; // Array of [item_id => return_qty]
$refundType = trim($_POST['refund_type'] ?? 'due_deduct'); // 'due_deduct' or 'cash_refund'
$reason = trim($_POST['reason'] ?? '') ?: 'Customer Sales Return';

if ($billId <= 0 || empty($returns) || !is_array($returns)) {
    flash_set('error', 'Invalid return submission. No items selected.');
    redirect('bills.php');
}

$pdo = get_db();

try {
    $pdo->beginTransaction();

    // 1. Fetch & lock bill
    $stmt = $pdo->prepare("SELECT * FROM bills WHERE id = ? AND deleted_at IS NULL FOR UPDATE");
    $stmt->execute([$billId]);
    $bill = $stmt->fetch();

    if (!$bill) {
        throw new Exception('Bill not found or already deleted.');
    }

    // 2. Fetch active bill items
    $itemsStmt = $pdo->prepare("SELECT * FROM bill_items WHERE bill_id = ? AND deleted_at IS NULL FOR UPDATE");
    $itemsStmt->execute([$billId]);
    $dbItems = $itemsStmt->fetchAll();

    $itemsById = [];
    foreach ($dbItems as $it) {
        $itemsById[$it['id']] = $it;
    }

    $totalReturnedAmount = 0.0;
    $returnedCount = 0;
    $returnedItemsSummary = [];

    // 3. Process each item return
    foreach ($returns as $itemId => $returnQty) {
        $itemId = (int) $itemId;
        $returnQty = (int) $returnQty;

        if ($returnQty <= 0) {
            continue;
        }

        if (!isset($itemsById[$itemId])) {
            throw new Exception("Invalid item reference (#{$itemId}).");
        }

        $item = $itemsById[$itemId];
        $alreadyReturned = (int) ($item['returned_quantity'] ?? 0);
        $originalQty = (int) $item['quantity'];
        $availableQty = $originalQty - $alreadyReturned;

        if ($returnQty > $availableQty) {
            throw new Exception("Return quantity ({$returnQty}) exceeds available quantity ({$availableQty}) for '{$item['product_name']}'.");
        }

        $unitPrice = (float) $item['unit_price'];
        $lineRefund = round($returnQty * $unitPrice, 2);
        $newReturnedQty = $alreadyReturned + $returnQty;

        // A. Restore product inventory stock
        if (!empty($item['product_id'])) {
            $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity + ?, updated_at = NOW() WHERE id = ?")
                ->execute([$returnQty, $item['product_id']]);
        }

        // B. Update bill_items returned_quantity
        $pdo->prepare("UPDATE bill_items SET returned_quantity = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$newReturnedQty, $itemId]);

        // C. Record log in bill_returns
        $insReturn = $pdo->prepare("
            INSERT INTO bill_returns (bill_id, bill_item_id, product_id, product_name, unit_price, quantity, total_refund, refund_type, reason, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $insReturn->execute([
            $billId,
            $itemId,
            $item['product_id'] ?: null,
            $item['product_name'],
            $unitPrice,
            $returnQty,
            $lineRefund,
            $refundType,
            $reason
        ]);

        $totalReturnedAmount += $lineRefund;
        $returnedCount += $returnQty;
        $returnedItemsSummary[] = "{$item['product_name']} (Qty: {$returnQty})";
    }

    if ($totalReturnedAmount <= 0) {
        throw new Exception("Please specify at least 1 item quantity to return.");
    }

    // 4. Adjust bill balance only — original grand_total / subtotal are NEVER changed.
    // The original invoice amount must remain intact; only track payment balance.
    $discount = (float) $bill['discount'];

    $currentPaid = (float) $bill['paid_amount'];
    $currentDue = (float) $bill['due_amount'];
    $customerId = !empty($bill['customer_id']) ? (int) $bill['customer_id'] : null;

    if ($refundType === 'due_deduct') {
        if ($currentDue > 0) {
            $dueReduction = min($currentDue, $totalReturnedAmount);
            $newDue = max(0, $currentDue - $dueReduction);
            $excessRefund = $totalReturnedAmount - $dueReduction;

            // Reduce customer total_due in Khata
            if ($customerId && $dueReduction > 0) {
                $pdo->prepare("UPDATE customers SET total_due = GREATEST(0, total_due - ?), updated_at = NOW() WHERE id = ?")
                    ->execute([$dueReduction, $customerId]);
            }

            // If return exceeded due amount, the rest is refunded from paid amount
            $newPaid = max(0, $currentPaid - $excessRefund);
        } else {
            // Bill was fully paid; refund from paid amount
            $newPaid = max(0, $currentPaid - $totalReturnedAmount);
            $newDue = 0;
        }
    } else {
        // Cash Refund directly given to customer
        $newPaid = max(0, $currentPaid - $totalReturnedAmount);
        $newDue = max(0, $newGrandTotal - $newPaid);

        $dueDiff = $newDue - $currentDue;
        if ($customerId && $dueDiff != 0) {
            if ($dueDiff > 0) {
                $pdo->prepare("UPDATE customers SET total_due = total_due + ?, updated_at = NOW() WHERE id = ?")
                    ->execute([$dueDiff, $customerId]);
            } else {
                $pdo->prepare("UPDATE customers SET total_due = GREATEST(0, total_due - ?), updated_at = NOW() WHERE id = ?")
                    ->execute([abs($dueDiff), $customerId]);
            }
        }
    }

    // Determine status
    if ($newDue <= 0) {
        $newStatus = 'paid';
    } elseif ($newPaid > 0) {
        $newStatus = 'partial';
    } else {
        $newStatus = 'unpaid';
    }

    // 5. Update Bill — preserve original subtotal, discount, grand_total.
    //    Only paid_amount, due_amount and payment_status are adjusted for balance tracking.
    $updBill = $pdo->prepare("
        UPDATE bills 
        SET paid_amount = ?, due_amount = ?, payment_status = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $updBill->execute([
        $newPaid,
        $newDue,
        $newStatus,
        $billId
    ]);

    $pdo->commit();

    $summaryStr = implode(', ', $returnedItemsSummary);
    flash_set('success', "✅ Return processed successfully! {$returnedCount} items returned ({$summaryStr}). ₹" . number_format($totalReturnedAmount, 2) . " deducted from bill & inventory stock restored.");
    redirect("return-slip.php?bill_id={$billId}");

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash_set('error', 'Failed to process return: ' . $e->getMessage());
    redirect("bill-slip.php?id={$billId}");
}

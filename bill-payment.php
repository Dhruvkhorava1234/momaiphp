<?php
/**
 * Handle Due Payment Action
 * MOMAI PLYWOOD - Core PHP
 */
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth_guard.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $billId = (int) ($_POST['bill_id'] ?? 0);
    $amount = (float) ($_POST['amount'] ?? 0);
    $paymentMethod = trim($_POST['payment_method'] ?? 'cash') ?: 'cash';
    $note = trim($_POST['note'] ?? 'Due payment collected') ?: 'Due payment collected';

    if ($billId <= 0 || $amount <= 0) {
        flash_set('error', 'Invalid payment amount.');
        redirect('bills.php');
    }

    $pdo = get_db();

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT * FROM bills WHERE id = ? AND deleted_at IS NULL FOR UPDATE");
        $stmt->execute([$billId]);
        $bill = $stmt->fetch();

        if (!$bill) {
            throw new Exception('Bill record not found.');
        }

        $dueAmount = (float) $bill['due_amount'];
        $paidAmount = (float) $bill['paid_amount'];

        if ($amount > $dueAmount) {
            $amount = $dueAmount; // Prevent overpayment
        }

        $newPaid = $paidAmount + $amount;
        $newDue = max(0, $dueAmount - $amount);
        $newStatus = ($newDue <= 0) ? 'paid' : 'partial';

        // Update Bill
        $upd = $pdo->prepare("UPDATE bills SET paid_amount = ?, due_amount = ?, payment_status = ?, updated_at = NOW() WHERE id = ?");
        $upd->execute([$newPaid, $newDue, $newStatus, $billId]);

        // Insert Payment Log
        $ins = $pdo->prepare("INSERT INTO bill_payments (bill_id, amount_paid, payment_method, note, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
        $ins->execute([$billId, $amount, $paymentMethod, $note]);
        $paymentId = $pdo->lastInsertId();

        // Update Customer Total Due
        if (!empty($bill['customer_id'])) {
            $pdo->prepare("UPDATE customers SET total_due = GREATEST(0, total_due - ?), updated_at = NOW() WHERE id = ?")
                ->execute([$amount, $bill['customer_id']]);
        }

        $pdo->commit();

        if ($newDue <= 0) {
            flash_set('success', "Bill {$bill['bill_number']} is now FULLY PAID! Official full invoice unlocked.");
            redirect("bill-slip.php?id={$billId}");
        } else {
            flash_set('success', "Due payment of " . format_inr($amount) . " recorded. Small due receipt generated.");
            redirect("bill-due-slip.php?id={$billId}&payment_id={$paymentId}");
        }

    } catch (Exception $e) {
        $pdo->rollBack();
        flash_set('error', 'Error recording payment: ' . $e->getMessage());
        redirect('bills.php');
    }
}

redirect('bills.php');

<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

if (!isset($_SESSION['customer_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}

$customerId = $_SESSION['customer_id'];
$input = json_decode(file_get_contents('php://input'), true);

try {
    // Start the transaction
    $conn->begin_transaction();

    // Input parameters
    $credit_order_id = $input['credit_order_id'];
    $amount_paying = floatval($input['repay_amount']);
    $payment_date = date('Y-m-d H:i:s'); // Assuming the current timestamp for payment date

    // Fetch the credit order details
    $stmt = $conn->prepare("SELECT * FROM credit_orders WHERE credit_order_id = ? FOR UPDATE");
    $stmt->bind_param("i", $credit_order_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Credit order not found.");
    }

    $credit_order = $result->fetch_assoc();

    // Extract necessary fields
    $remaining_balance = floatval($credit_order['remaining_balance']);
    $total_credit_amount = floatval($credit_order['total_credit_amount']);
    $repayment_status = $credit_order['repayment_status'];
    $amount_paid = floatval($credit_order['amount_paid']);
    $order_id = $credit_order['order_id'];

    // Validation checks
    if ($repayment_status === 'Paid') {
        throw new Exception("This credit order is already fully paid.");
    }

    if (($amount_paid + $amount_paying) > $total_credit_amount) {
        throw new Exception("The payment amount exceeds the total credit amount.");
    }

    // Fetch wallet balance
    $wallet_stmt = $conn->prepare("SELECT balance FROM wallets WHERE customer_id = ? FOR UPDATE");
    $wallet_stmt->bind_param("i", $customerId);
    $wallet_stmt->execute();
    $wallet_result = $wallet_stmt->get_result();

    if ($wallet_result->num_rows === 0) {
        throw new Exception("Wallet not found for the customer.");
    }

    $wallet = $wallet_result->fetch_assoc();
    $wallet_balance = floatval($wallet['balance']);

    if ($wallet_balance < $amount_paying) {
        throw new Exception("Insufficient wallet balance to process the repayment.");
    }

    // Debit the wallet
    $new_wallet_balance = $wallet_balance - $amount_paying;
    $debit_wallet_stmt = $conn->prepare("UPDATE wallets SET balance = ? WHERE customer_id = ?");
    $debit_wallet_stmt->bind_param("di", $new_wallet_balance, $customerId);
    $debit_wallet_stmt->execute();

    // Calculate new remaining balance and repayment status
    $amount_paid += $amount_paying;
    $new_remaining_balance = $total_credit_amount - $amount_paid;
    $new_repayment_status = ($amount_paid < $total_credit_amount) ? 'Partially Paid' : 'Paid';

    // Update the credit_orders table
    // Prepare the statement
    $update_stmt = $conn->prepare("
        UPDATE credit_orders
        SET remaining_balance = ?, repayment_status = ?, amount_paid = ?, date_last_modified = ?
        WHERE credit_order_id = ?
    ");

    // Get the current timestamp
    $date_last_updated = date('Y-m-d H:i:s');

    // Bind the parameters
    $update_stmt->bind_param("dsssi", $new_remaining_balance, $new_repayment_status, $amount_paid, $date_last_updated, $credit_order_id);

    // Execute the statement
    $update_stmt->execute();

    //Update Revenue Table
    $update_stmt = $conn->prepare("
        UPDATE revenue
        SET total_amount = ?, retained_amount = ?,  updated_at = ?, revenue_type_id = ?
        WHERE order_id = ?
    ");

    // Get the current timestamp
    $updated_at = date('Y-m-d H:i:s');
    $revenue_type_id = 7;

    // Bind the parameters
    $update_stmt->bind_param("ddsii", $amount_paid, $amount_paid, $updated_at, $revenue_type_id, $order_id);

    // Execute the statement
    $update_stmt->execute();

    //Update transaction Table
    $update_stmt = $conn->prepare("
        UPDATE transactions
        SET amount = ?, updated_at = ?, revenue_type_id = ?
        WHERE order_id = ?
    ");

    // Get the current timestamp
    $updated_at = date('Y-m-d H:i:s');
    $revenue_type_id = 7;

    // Bind the parameters
    $update_stmt->bind_param("dsii", $amount_paid, $updated_at, $revenue_type_id, $order_id);

    // Execute the statement
    $update_stmt->execute();

    // Insert into repayment_history table
    $insert_stmt = $conn->prepare("
        INSERT INTO repayment_history (credit_order_id, customer_id, amount_paid, payment_date)
        VALUES (?, ?, ?, ?)
    ");
    $insert_stmt->bind_param("iids", $credit_order_id, $customerId, $amount_paying, $payment_date);
    $insert_stmt->execute();

    // Insert transaction into customer_transactions table
    $transaction_stmt = $conn->prepare("
        INSERT INTO customer_transactions (customer_id, amount, date_created, transaction_type, payment_method, description) VALUES (?, ?, NOW(), ?, ?, ?)")
    ;
    $transaction_type = "Debit";
    $payment_method = 'Diredct Debit';
    $description = "Repayment of credit order #$credit_order_id for Order #$order_id";

    $transaction_stmt->bind_param("idsss", $customerId, $amount_paying, $transaction_type, $payment_method, $description);
    $transaction_stmt->execute();

    // Commit the transaction
    $conn->commit();

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Repayment processed successfully.',
        'remaining_balance' => $new_remaining_balance,
        'repayment_status' => $new_repayment_status
    ]);
} catch (Exception $e) {
    // Rollback the transaction on error
    $conn->rollback();

    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

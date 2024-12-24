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
function generateTransactionReference()
{
    $prefix = 'TRX';
    $uniqueId = uniqid($prefix, true);
    $randomNumber = mt_rand(1000, 9999);
    return strtoupper(str_replace('.', '', $uniqueId . $randomNumber));
}

try {
    // Start the transaction
    $conn->begin_transaction();

    // Input parameters
    $credit_order_id = $input['credit_order_id'];
    $amount_paying = floatval($input['repay_amount']);
    $repayment_type = $input['repaymentMethod'];
    $payment_date = date('Y-m-d H:i:s'); // Assuming the current timestamp for payment date
    $order_id = $input['order_id'];

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
    $status = $credit_order['status'];
    $due_date = $credit_order['due_date'];

    // Validation checks
    if ($repayment_status === 'Paid') {
        throw new Exception("This credit order is already fully paid.");
    }
    if ($repayment_status === 'Void' && $status == 'Declined') {
        throw new Exception("This credit order has been Cancelled. There's no need for repayment");
    }
    if ($status === 'Pending') {
        throw new Exception("This Order is still Pending. Kindly wait for approval before making Repayment");
    }

    if (($amount_paid + $amount_paying) > $total_credit_amount) {
        throw new Exception("The payment amount exceeds the total credit amount.");
    }

    // Handle repayment type
    if ($repayment_type === "Full Repayment") {
        $amount_paying = $remaining_balance; // Full repayment clears the balance
    } else if ($repayment_type === "Partial Repayment") {
        if ($amount_paying <= 0 || $amount_paying > $remaining_balance) {
            throw new Exception("Invalid repayment amount for partial repayment.");
        }
        // Apply late payment fee for partial payments if overdue
        // $current_date = date('Y-m-d H:i:s');
        // if ($due_date < $current_date) {
        //     $late_payment_fee = (0.3 * $total_credit_amount);
        //     $amount_paying += $late_payment_fee;
        // }
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

    // Get the current date and time
    $current_date = date('Y-m-d H:i:s');

    // Debit the wallet and calculate late payment fee if due_date has passed
    if ($due_date < $current_date) {
        // Apply 30% late payment fee
        $late_payment_fee = (0.3 * $total_credit_amount);
        $amount_paying += $late_payment_fee;
        if ($wallet_balance < $amount_paying) {
            throw new Exception("Insufficient wallet balance to process your repayment.");
        }
    }
    else{
        if ($wallet_balance < $amount_paying) {
            throw new Exception("Insufficient wallet balance to process your repayment.");
        }
    }

    // Calculate the new wallet balance
    $new_wallet_balance = $wallet_balance - $amount_paying;

    // Update the wallet balance in the database
    $debit_wallet_stmt = $conn->prepare("UPDATE wallets SET balance = ? WHERE customer_id = ?");
    $debit_wallet_stmt->bind_param("di", $new_wallet_balance, $customerId);
    $debit_wallet_stmt->execute();

    // Calculate the new remaining balance and repayment status
    $amount_paid += $amount_paying;
    $new_remaining_balance = $total_credit_amount - $amount_paid;
    $new_repayment_status = ($amount_paid < $total_credit_amount) ? 'Partially Paid' : 'Paid';

    // Prepare the update query for the credit_orders table
    $update_stmt = $conn->prepare("
    UPDATE credit_orders
    SET remaining_balance = ?, repayment_status = ?, amount_paid = ?, date_last_modified = ?
    WHERE credit_order_id = ?
");

    // Get the current timestamp for the update
    $date_last_updated = date('Y-m-d H:i:s');

    // Bind the parameters to the query
    $update_stmt->bind_param("dsssi", $new_remaining_balance, $new_repayment_status, $amount_paid, $date_last_updated, $credit_order_id);

    // Execute the update statement
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
        INSERT INTO repayment_history (order_id, credit_order_id, customer_id, amount_paid, payment_date)
        VALUES (?, ?, ?, ?, ?)
    ");
    $insert_stmt->bind_param("iiids", $order_id, $credit_order_id, $customerId, $amount_paying, $payment_date);
    $insert_stmt->execute();

    // Insert transaction into customer_transactions table
    $transactionReference = generateTransactionReference();
    $transaction_stmt = $conn->prepare("
        INSERT INTO customer_transactions (transaction_ref, customer_id, amount, date_created, transaction_type, payment_method, description) VALUES (?, ?, ?, NOW(), ?, ?, ?)")
    ;
    $transaction_type = "Debit";
    $payment_method = 'Diredct Debit';
    $description = "Repayment of credit order #$credit_order_id for Order #$order_id";

    $transaction_stmt->bind_param("sidsss", $transactionReference, $customerId, $amount_paying, $transaction_type, $payment_method, $description);
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

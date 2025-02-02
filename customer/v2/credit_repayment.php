<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log the start of the script
logActivity("Repayment processing script started.");

// Check if the customer is logged in
if (!isset($_SESSION['customer_id'])) {
    logActivity("Unauthorized access. Customer not logged in.");
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}

$customerId = $_SESSION['customer_id'];
logActivity("Customer ID retrieved from session: $customerId");

$input = json_decode(file_get_contents('php://input'), true);
logActivity("Incoming request data: " . json_encode($input));

// Validate input
if (!isset($input['credit_order_id'], $input['repay_amount'], $input['repaymentMethod'], $input['order_id'])) {
    logActivity("Invalid input data received.");
    echo json_encode(["success" => false, "message" => "Invalid input data."]);
    exit();
}

// Generate unique transaction reference
function generateTransactionReference()
{
    $prefix = 'TRX';
    $uniqueId = uniqid($prefix, true);
    $randomNumber = mt_rand(1000, 9999);
    $transactionRef = strtoupper(str_replace('.', '', $uniqueId . $randomNumber));
    logActivity("Generated transaction reference: $transactionRef");
    return $transactionRef;
}

try {
    // Start transaction
    $conn->begin_transaction();
    logActivity("Transaction started.");

    $credit_order_id = $input['credit_order_id'];
    $amount_paying = floatval($input['repay_amount']);
    $repayment_type = $input['repaymentMethod'];
    $order_id = $input['order_id'];
    $payment_date = date('Y-m-d H:i:s');

    logActivity("Processing repayment for credit order ID: $credit_order_id, amount: $amount_paying, type: $repayment_type.");

    // Fetch credit order details
    $stmt = $conn->prepare("SELECT * FROM credit_orders WHERE credit_order_id = ? FOR UPDATE");
    $stmt->bind_param("i", $credit_order_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Credit order not found.");
    }

    $credit_order = $result->fetch_assoc();
    $remaining_balance = floatval($credit_order['remaining_balance']);
    $total_credit_amount = floatval($credit_order['total_credit_amount']);
    $repayment_status = $credit_order['repayment_status'];
    $amount_paid = floatval($credit_order['amount_paid']);
    $due_date = $credit_order['due_date'];
    $status = $credit_order['status'];

    $current_date = date('Y-m-d H:i:s');

    logActivity("Credit order details fetched: " . json_encode($credit_order));

    // Validate repayment
    if ($repayment_status === 'Paid') {
        throw new Exception("This credit order is already fully paid.");
    }
    if ($repayment_status === 'Void' && $status === 'Declined') {
        throw new Exception("This credit order has been canceled. No repayment is required.");
    }
    if ($status === 'Pending') {
        throw new Exception("Order approval is pending. Please wait before making repayment.");
    }
    if (($amount_paid + $amount_paying) > $total_credit_amount) {
        throw new Exception("Your Payment exceeds Remaining Balance.");
    }
    if (($repayment_type === "Partial Repayment") && ((strtotime($due_date) < strtotime($current_date)))) {
        throw new Exception("Your Credit Order is Over Due. Partial Repayment not allowed.");
    }

    // Handle repayment type
    if ($repayment_type === "Full Repayment") {
        $amount_paying = $remaining_balance; // Set remaining balance to the amount the customer is paying if Full Repayment
        logActivity("Full repayment detected. Amount paying set to remaining balance: $amount_paying.");
    } elseif ($repayment_type === "Partial Repayment") {
        if ($amount_paying <= 0 || $amount_paying > $remaining_balance) {
            throw new Exception("Invalid repayment amount for partial repayment.");
        }
        logActivity("Partial repayment detected. Amount paying: $amount_paying.");
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
    logActivity("Wallet balance fetched: $wallet_balance.");

    $late_payment_fee = 0;

    // Calculate late payment fee
    if (strtotime($due_date) < strtotime($current_date)) {
        $late_payment_fee = 0.3 * $total_credit_amount;
        logActivity("Late payment fee calculated: $late_payment_fee.");
    }

    $total_debit = $amount_paying + $late_payment_fee;
    if ($wallet_balance < $total_debit) {
        $outstanding_debt = $total_debit - $wallet_balance;
        throw new Exception(
            "Insufficient wallet balance for repayment and late payment fee. " .
            "Outstanding debt: $amount_paying. " .
            "Late payment fee: $late_payment_fee."
        );
    }

    // Debit wallet
    $new_wallet_balance = $wallet_balance - $total_debit;
    $debit_wallet_stmt = $conn->prepare("UPDATE wallets SET balance = ? WHERE customer_id = ?");
    $debit_wallet_stmt->bind_param("di", $new_wallet_balance, $customerId);
    $debit_wallet_stmt->execute();
    logActivity("Wallet debited. New wallet balance: $new_wallet_balance.");

    // Update credit order
    $amount_paid += $amount_paying;
    $new_remaining_balance = $total_credit_amount - $amount_paid;
    $new_repayment_status = ($amount_paid < $total_credit_amount) ? 'Partially Paid' : 'Paid';

    $update_credit_stmt = $conn->prepare("
        UPDATE credit_orders
        SET remaining_balance = ?, repayment_status = ?, amount_paid = ?, date_last_modified = ?
        WHERE credit_order_id = ?
    ");
    $date_last_updated = date('Y-m-d H:i:s');
    $update_credit_stmt->bind_param("dsssi", $new_remaining_balance, $new_repayment_status, $amount_paid, $date_last_updated, $credit_order_id);
    $update_credit_stmt->execute();
    logActivity("Credit order updated. Remaining balance: $new_remaining_balance, Repayment status: $new_repayment_status.");

    // Update transaction table
    $update_stmt = $conn->prepare("
        UPDATE transactions
        SET amount = ?, updated_at = ?, revenue_type_id = ?
        WHERE order_id = ? AND revenue_type_id IN (2,7)
    ");
    $updated_at = date('Y-m-d H:i:s');
    $revenue_type_id = 7;
    $update_stmt->bind_param("dsii", $amount_paid, $updated_at, $revenue_type_id, $order_id);
    $update_stmt->execute();
    logActivity("Transactions table updated for order ID: $order_id.");

    // Update revenue table
    $update_stmt = $conn->prepare("
       UPDATE revenue
       SET total_amount = ?, retained_amount = ?,  updated_at = ?, revenue_type_id = ?
       WHERE order_id = ? AND revenue_type_id IN (2, 7)
   ");
    $updated_at = date('Y-m-d H:i:s');
    $revenue_type_id = 7;
    $update_stmt->bind_param("ddsii", $amount_paid, $amount_paid, $updated_at, $revenue_type_id, $order_id);
    $update_stmt->execute();
    logActivity("Revenue table updated for order ID: $order_id.");

    // Insert late payment fee into revenue and transactions if applicable
    if ($late_payment_fee > 0) {
        $transaction_ref = generateTransactionReference();
        $description = "Late payment fee for Credit Order #$order_id";
        $insert_revenue_stmt = $conn->prepare("
            INSERT INTO revenue (order_id, customer_id, total_amount, retained_amount, transaction_date, status, revenue_type_id)
            VALUES (?, ?, ?, ?, ?, 'Completed', 9)
        ");
        $insert_revenue_stmt->bind_param("iidds", $order_id, $customerId, $late_payment_fee, $late_payment_fee, $current_date);
        $insert_revenue_stmt->execute();
        logActivity("Late payment fee recorded in revenue table.");

        $insert_transaction_stmt = $conn->prepare("
            INSERT INTO customer_transactions (transaction_ref, customer_id, amount, date_created, transaction_type, payment_method, description)
            VALUES (?, ?, ?, NOW(), 'Debit', 'Wallet', ?)
        ");
        $insert_transaction_stmt->bind_param("sids", $transaction_ref, $customerId, $late_payment_fee, $description);
        $insert_transaction_stmt->execute();
        logActivity("Late payment fee recorded in customer transactions table.");

        // Insert into transactions table
        $transactionType2 = "Credit";
        $paymentMethod2 = "Direct Credit for Late Payment on Credit Order with Order ID: #$order_id";
        $status = "Completed";
        $revenue_type_id = 9;
        $insertTransactionStmt = $conn->prepare("
        INSERT INTO transactions (transaction_ref, customer_id, order_id, transaction_type, amount, transaction_date, payment_method, status, created_at, updated_at, revenue_type_id)
        VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, NOW(), NOW(), ?)
    ");
        if (!$insertTransactionStmt) {
            throw new Exception("Failed to prepare transaction statement: " . $conn->error);
        }
        $insertTransactionStmt->bind_param("siisdssi", $transaction_ref, $customerId, $order_id, $transactionType2, $late_payment_fee, $paymentMethod2, $status, $revenue_type_id);
        if (!$insertTransactionStmt->execute()) {
            throw new Exception("Failed to insert into transactions table: " . $conn->error);
        }
        $insertTransactionStmt->close();
        logActivity("Late payment fee recorded in transactions table.");
    }

    // Final transaction insertion
    $transaction_ref = generateTransactionReference();
    $description = "Repayment for credit order #$credit_order_id.";
    $insert_transaction_stmt = $conn->prepare("
        INSERT INTO customer_transactions (transaction_ref, customer_id, amount, date_created, transaction_type, payment_method, description)
        VALUES (?, ?, ?, NOW(), 'Debit', 'Wallet', ?)
    ");
    $insert_transaction_stmt->bind_param("sids", $transaction_ref, $customerId, $total_debit, $description);
    $insert_transaction_stmt->execute();
    logActivity("Repayment recorded in customer transactions table.");

    // Insert into repayment history
    $repayment_history_stmt = $conn->prepare("
     INSERT INTO repayment_history (order_id, credit_order_id, customer_id, amount_paid, payment_date)
     VALUES (?, ?, ?, ?, ?)
 ");
    $repayment_history_stmt->bind_param("iiids", $order_id, $credit_order_id, $customerId, $amount_paying, $payment_date);
    $repayment_history_stmt->execute();
    logActivity("Repayment recorded in repayment history table.");

    // Commit transaction
    $conn->commit();
    logActivity("Transaction committed successfully.");

    echo json_encode([
        'success' => true,
        'message' => 'Repayment processed successfully.',
        'remaining_balance' => $new_remaining_balance,
        'repayment_status' => $new_repayment_status,
    ]);
} catch (Exception $e) {
    // Rollback transaction and handle errors
    $conn->rollback();
    logActivity("Transaction failed: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
logActivity("Database connection closed.");
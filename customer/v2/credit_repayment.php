<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log the start of the script
logActivity("REPAYMENT_PROCESS_START: Script initiated for customer session");

// Check if the customer is logged in
if (!isset($_SESSION["customer_id"])) {
    logActivity("REPAYMENT_AUTH_FAIL: No customer session found");
    http_response_code(401);
    exit(json_encode(["success" => false, "message" => "Authentication required"]));
}

$customerId = (int)$_SESSION["customer_id"];
if ($customerId <= 0) {
    logActivity("REPAYMENT_INVALID_SESSION: Invalid customer ID in session");
    http_response_code(400);
    exit(json_encode(["success" => false, "message" => "Invalid session data"]));
}

logActivity("REPAYMENT_CUSTOMER_ID: Processing for customer ID $customerId");

// Validate and parse input
$input = json_decode(file_get_contents('php://input'), true) ?? [];
logActivity("REPAYMENT_INPUT_DATA: " . json_encode($input));

if (!isset($input['credit_order_id'], $input['repay_amount'], $input['repaymentMethod'], $input['order_id'])) {
    logActivity("REPAYMENT_INVALID_INPUT: Missing required fields");
    http_response_code(400);
    exit(json_encode(["success" => false, "message" => "Invalid input data"]));
}

// Generate unique transaction reference
function generateTransactionReference() {
    $prefix = 'TRX';
    $uniqueId = uniqid($prefix, true);
    $randomNumber = mt_rand(1000, 9999);
    $transactionRef = strtoupper(str_replace('.', '', $uniqueId . $randomNumber));
    logActivity("REPAYMENT_TRX_REF: Generated reference $transactionRef");
    return $transactionRef;
}

// Initialize database connection
$conn->begin_transaction();
logActivity("REPAYMENT_DB_TRANSACTION: Transaction started");

try {
    // Extract and validate input
    $creditOrderId = (int)$input['credit_order_id'];
    $amountPaying = (float)$input['repay_amount'];
    $repaymentType = $input['repaymentMethod'];
    $orderId = (int)$input['order_id'];
    $paymentDate = date('Y-m-d H:i:s');
    $currentDate = date('Y-m-d H:i:s');

    logActivity("REPAYMENT_PARAMS: credit_order_id=$creditOrderId, amount=$amountPaying, type=$repaymentType, order_id=$orderId");

    // Fetch and validate credit order
    $creditOrder = fetchCreditOrder($conn, $creditOrderId);
    validateCreditOrder($creditOrder, $amountPaying, $repaymentType, $currentDate);

    // Fetch and validate order status
    $order = fetchOrder($conn, $orderId);
    if ($order['delivery_status'] !== 'Delivered') {
        throw new Exception("Repayment Failed: Delivery is not completed!");
    }

    // Process repayment based on type
    if ($repaymentType === "Full Repayment") {
        $amountPaying = (float)$creditOrder['remaining_balance'];
        logActivity("REPAYMENT_FULL: Setting amount to remaining balance $amountPaying");
    }

    // Process wallet transaction
    $wallet = fetchWallet($conn, $customerId);
    $lateFee = calculateLateFee($creditOrder['due_date'], $currentDate, $creditOrder['total_credit_amount']);
    $totalDebit = processWalletDebit($conn, $customerId, $wallet['balance'], $amountPaying, $lateFee);

    // Update credit order
    $newAmountPaid = (float)$creditOrder['amount_paid'] + $amountPaying;
    $newRemainingBalance = (float)$creditOrder['total_credit_amount'] - $newAmountPaid;
    $newRepaymentStatus = ($newAmountPaid < $creditOrder['total_credit_amount']) ? 'Partially Paid' : 'Paid';
    
    updateCreditOrder($conn, $creditOrderId, $newRemainingBalance, $lateFee, $newRepaymentStatus, $newAmountPaid);

    // Update financial records
    updateFinancialRecords($conn, $orderId, $customerId, $newAmountPaid, $lateFee, $creditOrderId, $amountPaying, $paymentDate);

    // Commit transaction
    $conn->commit();
    logActivity("REPAYMENT_SUCCESS: Transaction completed successfully");

    echo json_encode([
        'success' => true,
        'message' => 'Repayment processed successfully.',
        'remaining_balance' => $newRemainingBalance,
        'repayment_status' => $newRepaymentStatus,
    ]);

} catch (Exception $e) {
    $conn->rollback();
    logActivity("REPAYMENT_ERROR: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    $conn->close();
    logActivity("REPAYMENT_PROCESS_END: Script completed");
}

// Helper functions
function fetchCreditOrder($conn, $creditOrderId) {
    $stmt = $conn->prepare("SELECT * FROM credit_orders WHERE credit_order_id = ? FOR UPDATE");
    $stmt->bind_param("i", $creditOrderId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Credit order not found.");
    }
    
    $creditOrder = $result->fetch_assoc();
    logActivity("REPAYMENT_CREDIT_ORDER: Fetched order " . json_encode($creditOrder));
    return $creditOrder;
}

function validateCreditOrder($creditOrder, $amountPaying, $repaymentType, $currentDate) {
    if ($creditOrder['repayment_status'] === 'Paid') {
        throw new Exception("This credit order is already fully paid.");
    }
    if ($creditOrder['repayment_status'] === 'Void' && $creditOrder['status'] === 'Declined') {
        throw new Exception("This credit order has been canceled. No repayment is required.");
    }
    if ($creditOrder['status'] === 'Pending') {
        throw new Exception("Order approval is pending. Please wait before making repayment.");
    }
    if (($creditOrder['amount_paid'] + $amountPaying) > $creditOrder['total_credit_amount']) {
        throw new Exception("Your Payment exceeds Remaining Balance.");
    }
    if (($repaymentType === "Partial Repayment") && (strtotime($creditOrder['due_date']) < strtotime($currentDate))) {
        throw new Exception("Your Credit Order is Over Due. Partial Repayment not allowed.");
    }
}

function fetchOrder($conn, $orderId) {
    $stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ? FOR UPDATE");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Order not found.");
    }
    
    $order = $result->fetch_assoc();
    logActivity("REPAYMENT_ORDER_DETAILS: Fetched order " . json_encode($order));
    return $order;
}

function fetchWallet($conn, $customerId) {
    $stmt = $conn->prepare("SELECT balance FROM wallets WHERE customer_id = ? FOR UPDATE");
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Wallet not found for the customer.");
    }
    
    $wallet = $result->fetch_assoc();
    logActivity("REPAYMENT_WALLET_BALANCE: " . $wallet['balance']);
    return $wallet;
}

function calculateLateFee($dueDate, $currentDate, $totalCreditAmount) {
    $lateFee = 0.00;
    if (strtotime($dueDate) < strtotime($currentDate)) {
        $lateFee = 0.3 * $totalCreditAmount;
        logActivity("REPAYMENT_LATE_FEE: Calculated late fee $lateFee");
    }
    return $lateFee;
}

function processWalletDebit($conn, $customerId, $walletBalance, $amountPaying, $lateFee) {
    $totalDebit = $amountPaying + $lateFee;
    
    if ($walletBalance < $totalDebit) {
        $outstandingDebt = $totalDebit - $walletBalance;
        throw new Exception(
            "Insufficient wallet balance for repayment and late payment fee. " .
            "Outstanding debt: $amountPaying. " .
            "Late payment fee: $lateFee."
        );
    }
    
    $newBalance = $walletBalance - $totalDebit;
    $stmt = $conn->prepare("UPDATE wallets SET balance = ? WHERE customer_id = ?");
    $stmt->bind_param("di", $newBalance, $customerId);
    $stmt->execute();
    logActivity("REPAYMENT_WALLET_DEBIT: New balance $newBalance");
    
    return $totalDebit;
}

function updateCreditOrder($conn, $creditOrderId, $newRemainingBalance, $lateFee, $newRepaymentStatus, $newAmountPaid) {
    $stmt = $conn->prepare("
        UPDATE credit_orders
        SET remaining_balance = ?, late_payment_fee = ?, repayment_status = ?, amount_paid = ?, date_last_modified = ?
        WHERE credit_order_id = ?
    ");
    $dateUpdated = date('Y-m-d H:i:s');
    $stmt->bind_param("ddsssi", $newRemainingBalance, $lateFee, $newRepaymentStatus, $newAmountPaid, $dateUpdated, $creditOrderId);
    $stmt->execute();
    logActivity("REPAYMENT_CREDIT_UPDATE: Updated credit order $creditOrderId");
}

function updateFinancialRecords($conn, $orderId, $customerId, $amountPaid, $lateFee, $creditOrderId, $amountPaying, $paymentDate) {
    // Update transactions table
    $updatedAt = date('Y-m-d H:i:s');
    $revenueTypeId = 7;
    $stmt = $conn->prepare("
        UPDATE transactions
        SET amount = ?, updated_at = ?, revenue_type_id = ?
        WHERE order_id = ? AND revenue_type_id IN (2,7)
    ");
    $stmt->bind_param("dsii", $amountPaid, $updatedAt, $revenueTypeId, $orderId);
    $stmt->execute();
    logActivity("REPAYMENT_TRANSACTION_UPDATE: Updated transactions for order $orderId");

    // Update revenue table
    $stmt = $conn->prepare("
        UPDATE revenue
        SET total_amount = ?, retained_amount = ?, updated_at = ?, revenue_type_id = ?
        WHERE order_id = ? AND revenue_type_id IN (2, 7)
    ");
    $stmt->bind_param("ddsii", $amountPaid, $amountPaid, $updatedAt, $revenueTypeId, $orderId);
    $stmt->execute();
    logActivity("REPAYMENT_REVENUE_UPDATE: Updated revenue for order $orderId");

    // Process late fee if applicable
    if ($lateFee > 0) {
        $transactionRef = generateTransactionReference();
        $description = "Late payment fee for Credit Order #$orderId";
        
        // Insert into revenue
        $stmt = $conn->prepare("
            INSERT INTO revenue (order_id, customer_id, total_amount, retained_amount, transaction_date, status, revenue_type_id)
            VALUES (?, ?, ?, ?, ?, 'Completed', 9)
        ");
        $stmt->bind_param("iidds", $orderId, $customerId, $lateFee, $lateFee, $paymentDate);
        $stmt->execute();
        logActivity("REPAYMENT_LATE_FEE_REVENUE: Recorded late fee in revenue");

        // Insert into customer transactions
        $stmt = $conn->prepare("
            INSERT INTO customer_transactions (transaction_ref, customer_id, amount, date_created, transaction_type, payment_method, description)
            VALUES (?, ?, ?, NOW(), 'Debit', 'Wallet', ?)
        ");
        $stmt->bind_param("sids", $transactionRef, $customerId, $lateFee, $description);
        $stmt->execute();
        logActivity("REPAYMENT_LATE_FEE_CUSTOMER_TRX: Recorded late fee in customer transactions");

        // Insert into transactions table
        $transactionType = "Credit";
        $paymentMethod = "Direct Credit for Late Payment on Credit Order with Order ID: #$orderId";
        $status = "Completed";
        $revenueTypeId = 9;
        $stmt = $conn->prepare("
            INSERT INTO transactions (transaction_ref, customer_id, order_id, transaction_type, amount, transaction_date, payment_method, status, created_at, updated_at, revenue_type_id)
            VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, NOW(), NOW(), ?)
        ");
        $stmt->bind_param("siisdssi", $transactionRef, $customerId, $orderId, $transactionType, $lateFee, $paymentMethod, $status, $revenueTypeId);
        $stmt->execute();
        logActivity("REPAYMENT_LATE_FEE_TRANSACTION: Recorded late fee in transactions");
    }

    // Record main repayment transaction
    $transactionRef = generateTransactionReference();
    $description = "Repayment for credit order #$creditOrderId.";
    $stmt = $conn->prepare("
        INSERT INTO customer_transactions (transaction_ref, customer_id, amount, date_created, transaction_type, payment_method, description)
        VALUES (?, ?, ?, NOW(), 'Debit', 'Wallet', ?)
    ");
    $totalDebit = $amountPaying + $lateFee;
    $stmt->bind_param("sids", $transactionRef, $customerId, $totalDebit, $description);
    $stmt->execute();
    logActivity("REPAYMENT_CUSTOMER_TRX: Recorded customer transaction");

    // Record repayment history
    $stmt = $conn->prepare("
        INSERT INTO repayment_history (order_id, credit_order_id, customer_id, amount_paid, payment_date)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iiids", $orderId, $creditOrderId, $customerId, $amountPaying, $paymentDate);
    $stmt->execute();
    logActivity("REPAYMENT_HISTORY: Recorded repayment history");
}
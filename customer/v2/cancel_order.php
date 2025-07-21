<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Constants
const CANCELLATION_FEE_PERCENTAGE = 0.2;
const VALID_STATUSES = ['Cancelled'];
const REVENUE_TYPE_CANCELLATION = 1;

// Check session and get customer ID
$customerId = $_SESSION["customer_id"] ?? null;
if (!$customerId) {
    logActivity("ORDER_CANCELLATION_AUTH_FAIL: No customer session found");
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Authentication required']));
}

logActivity("ORDER_CANCELLATION_START: Processing for customer ID $customerId");

// Process input
$input = json_decode(file_get_contents('php://input'), true) ?? [];
logActivity("ORDER_CANCELLATION_INPUT: " . json_encode($input));

if (!isset($input['order_id'], $input['status']) || !in_array($input['status'], VALID_STATUSES)) {
    logActivity("ORDER_CANCELLATION_INVALID_INPUT: Missing or invalid parameters");
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Invalid request data']));
}

$orderId = (int)$input['order_id'];
$status = $input['status'];
logActivity("ORDER_CANCELLATION_REQUEST: Order ID $orderId, Status $status");

try {
    // Start transaction
    $conn->begin_transaction();
    logActivity("ORDER_CANCELLATION_TRANSACTION: Started");

    // Fetch order details
    $order = fetchOrderDetails($conn, $orderId);
    validateOrderForCancellation($order);

    if ($order['is_credit']) {
        throw new Exception("You cannot cancel an order placed on credit.");
    }

    // Process cancellation
    $cancellationDetails = calculateCancellationFees($order);
    processCancellation($conn, $orderId, $customerId, $order, $cancellationDetails);

    // Commit transaction
    $conn->commit();
    logActivity("ORDER_CANCELLATION_SUCCESS: Order $orderId cancelled successfully");
    echo json_encode(['success' => true, 'message' => 'Your order has been successfully cancelled.']);

} catch (Exception $e) {
    $conn->rollback();
    logActivity("ORDER_CANCELLATION_ERROR: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    $conn->close();
    logActivity("ORDER_CANCELLATION_COMPLETE: Process ended");
}

// Helper functions
function fetchOrderDetails($conn, $orderId) {
    $stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ? FOR UPDATE");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Order not found.");
    }
    
    $order = $result->fetch_assoc();
    logActivity("ORDER_CANCELLATION_DETAILS: Fetched order " . json_encode($order));
    return $order;
}

function validateOrderForCancellation($order) {
    if ($order['status'] === 'Cancelled') {
        throw new Exception("Order is already cancelled.");
    }
    // Add any other validation rules here
}

function calculateCancellationFees($order) {
    $totalAmount = (float)$order['total_amount'] + (float)$order['discount'];
    $cancellationFee = $totalAmount * CANCELLATION_FEE_PERCENTAGE;
    $refundAmount = $totalAmount - $cancellationFee;
    
    logActivity("ORDER_CANCELLATION_FEES: Total=$totalAmount, Fee=$cancellationFee, Refund=$refundAmount");
    
    return [
        'total_amount' => $totalAmount,
        'cancellation_fee' => $cancellationFee,
        'refund_amount' => $refundAmount,
        'order_date' => $order['order_date']
    ];
}

function processCancellation($conn, $orderId, $customerId, $order, $details) {
    // Generate transaction reference
    $transactionRef = generateTransactionReference();
    
    // Update wallet balance
    updateWalletBalance($conn, $customerId, $details['refund_amount']);
    
    // Record transactions
    recordCancellationTransactions($conn, $orderId, $customerId, $transactionRef, $details);
    
    // Update food stock
    restoreFoodStock($conn, $orderId);
    
    // Update order status
    updateOrderStatus($conn, $orderId, $customerId);
}

function generateTransactionReference() {
    $prefix = 'TRX';
    $uniqueId = uniqid($prefix, true);
    $randomNumber = mt_rand(1000, 9999);
    $transactionRef = strtoupper(str_replace('.', '', $uniqueId . $randomNumber));
    logActivity("ORDER_CANCELLATION_TRX_REF: Generated $transactionRef");
    return $transactionRef;
}

function updateWalletBalance($conn, $customerId, $amount) {
    $stmt = $conn->prepare("UPDATE wallets SET balance = balance + ? WHERE customer_id = ?");
    $stmt->bind_param("di", $amount, $customerId);
    $stmt->execute();
    logActivity("ORDER_CANCELLATION_WALLET: Credited $amount to customer $customerId");
}

function recordCancellationTransactions($conn, $orderId, $customerId, $transactionRef, $details) {
    // Record cancellation fee (debit)
    $feeDescription = "Cancellation fee for Order ID: $orderId";
    $stmt = $conn->prepare(
        "INSERT INTO customer_transactions 
        (transaction_ref, customer_id, amount, date_created, transaction_type, payment_method, description) 
        VALUES (?, ?, ?, NOW(), 'debit', 'Order Cancellation Fee', ?)"
    );
    $stmt->bind_param("sids", $transactionRef, $customerId, $details['cancellation_fee'], $feeDescription);
    $stmt->execute();
    
    // Record refund (credit)
    $refundDescription = "Refund for Cancelled Order: $orderId";
    $stmt = $conn->prepare(
        "INSERT INTO customer_transactions 
        (transaction_ref, customer_id, amount, date_created, transaction_type, payment_method, description) 
        VALUES (?, ?, ?, NOW(), 'credit', 'Transaction Refund', ?)"
    );
    $stmt->bind_param("sids", $transactionRef, $customerId, $details['refund_amount'], $refundDescription);
    $stmt->execute();
    
    // Record in transactions table
    $stmt = $conn->prepare(
        "INSERT INTO transactions 
        (transaction_ref, customer_id, order_id, transaction_type, amount, transaction_date, payment_method, status, created_at, revenue_type_id) 
        VALUES (?, ?, ?, 'Credit', ?, NOW(), 'Direct Debit Cancelled Order', 'Completed', NOW(), ?)"
    );
    $stmt->bind_param("siidi", $transactionRef, $customerId, $orderId, $details['cancellation_fee'], REVENUE_TYPE_CANCELLATION);
    $stmt->execute();
    
    // Record in revenue table
    $stmt = $conn->prepare(
        "INSERT INTO revenue 
        (order_id, customer_id, total_amount, refunded_amount, retained_amount, transaction_date, status, updated_at, revenue_type_id) 
        VALUES (?, ?, ?, ?, ?, ?, 'Completed', NOW(), ?)"
    );
    $stmt->bind_param("iidddsi", $orderId, $customerId, $details['total_amount'], 
        $details['refund_amount'], $details['cancellation_fee'], $details['order_date'], REVENUE_TYPE_CANCELLATION);
    $stmt->execute();
    
    logActivity("ORDER_CANCELLATION_TRANSACTIONS: Recorded all financial transactions");
}

function restoreFoodStock($conn, $orderId) {
    $stmt = $conn->prepare("SELECT food_id, quantity FROM order_details WHERE order_id = ?");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $updateStmt = $conn->prepare("UPDATE food SET available_quantity = available_quantity + ? WHERE food_id = ?");
        $updateStmt->bind_param("ii", $row['quantity'], $row['food_id']);
        $updateStmt->execute();
        logActivity("ORDER_CANCELLATION_STOCK: Restored {$row['quantity']} units for food {$row['food_id']}");
    }
}

function updateOrderStatus($conn, $orderId, $customerId) {
    $status = 'Cancelled';
    $reason = "Customer Cancelled Order before Delivery";
    
    // Update orders table
    $stmt = $conn->prepare(
        "UPDATE orders 
        SET status = ?, delivery_status = ?, cancellation_reason = ?, updated_at = NOW(), approved_by = ? 
        WHERE order_id = ?"
    );
    $stmt->bind_param("sssii", $status, $status, $reason, $customerId, $orderId);
    $stmt->execute();
    
    // Update order_details table
    $stmt = $conn->prepare("UPDATE order_details SET status = ?, updated_at = NOW() WHERE order_id = ?");
    $stmt->bind_param("si", $status, $orderId);
    $stmt->execute();
    
    logActivity("ORDER_CANCELLATION_STATUS: Updated status to Cancelled for order $orderId");
}
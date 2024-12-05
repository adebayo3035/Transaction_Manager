<?php
include('config.php');
session_start();

$data = json_decode(file_get_contents("php://input"), true);

// Constants for statuses
const STATUS_CANCELLED = 'Cancelled on Delivery';
const STATUS_DELIVERED = 'Delivered';
const STATUS_IN_TRANSIT = 'In Transit';

// Validate inputs
if (!isset($data['id'], $data['currentStatus'], $data['orderStatus'])) {
    respond(false, "Invalid order - Order details missing.");
}

$orderId = $data['id'];
$currentStatus = $data['currentStatus'];
$orderStatus = $data['orderStatus'];
$driverId = $_SESSION['driver_id'];

// Check invalid transitions
if (isInvalidTransition($currentStatus, $orderStatus)) {
    respond(false, "Invalid Order Status - From {$currentStatus} to {$orderStatus}");
}

// Verify delivery pin if required
if (($orderStatus === STATUS_DELIVERED || $orderStatus === STATUS_CANCELLED) && isset($data['deliveryPin'])) {
    $deliveryPin = $data['deliveryPin'];
    $orderDetails = getOrderDetails($orderId, $driverId);

    if ($orderDetails['is_credit']) {
        respond(false, 'You cannot cancel an order placed on credit.');
    }

    if ($orderDetails['delivery_pin'] !== $deliveryPin) {
        respond(false, "Invalid delivery pin. Please try again.");
    }
}

function generateTransactionReference()
    {
        $prefix = 'TRX';
        $uniqueId = uniqid($prefix, true);
        $randomNumber = mt_rand(1000, 9999);
        $transactionRef = strtoupper(str_replace('.', '', $uniqueId . $randomNumber));
        return $transactionRef;
    }

    $transactionReference = generateTransactionReference();

$conn->begin_transaction();

try {
    if ($orderStatus === STATUS_CANCELLED) {
        handleOrderCancellation($orderId, $driverId, $data['cancelReason'], $transactionReference, $status);
    } elseif ($orderStatus === STATUS_DELIVERED) {
        handleOrderDelivery($orderId, $driverId, $transactionReference, $status);
    } else {
        $status = 'Pending';
        updateOrderStatus($orderId, $driverId, $status, $orderStatus);
    }

    $conn->commit();
    respond(true, "Your Order has been successfully updated.");
} catch (Exception $e) {
    $conn->rollback();
    respond(false, "Transaction failed: " . $e->getMessage());
}

$conn->close();

/**
 * Function definitions
 */

function respond($success, $message) {
    echo json_encode(["success" => $success, "message" => $message]);
    exit();
}

function isInvalidTransition($current, $target) {
    $invalidTransitions = [
        ["from" => "Assigned", "to" => STATUS_DELIVERED],
        ["from" => "Assigned", "to" => STATUS_CANCELLED],
        ["from" => STATUS_IN_TRANSIT, "to" => STATUS_IN_TRANSIT],
    ];

    foreach ($invalidTransitions as $transition) {
        if ($current === $transition['from'] && $target === $transition['to']) {
            return true;
        }
    }
    return false;
}

function getOrderDetails($orderId, $driverId) {
    global $conn;
    $sql = "SELECT delivery_pin, is_credit FROM orders WHERE order_id = ? AND driver_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $orderId, $driverId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function handleOrderCancellation($orderId, $driverId, $cancelReason, $transactionReference, $status) {
    global $conn;

    // Update order status
    $delivery_status = 'Cancelled on Delivery';
    $status = 'Cancelled';
    $stmt = $conn->prepare("UPDATE orders SET status = ?, delivery_status = ?, cancellation_reason = ? WHERE order_id = ? AND driver_id = ?");
    $stmt->bind_param("sssii", $status, $delivery_status, $cancelReason, $orderId, $driverId);
    $stmt->execute();

    // Fetch order and calculate fees
    $stmt = $conn->prepare("SELECT total_amount, customer_id, order_date, payment_method FROM orders WHERE order_id = ?");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $stmt->bind_result($totalAmount, $customerId, $orderDate, $payment_method);
    $stmt->fetch();
    $stmt->close();

    $cancellationFee = 0.7 * $totalAmount;
    $refundAmount = $totalAmount - $cancellationFee;

    // Update customer wallet
    $stmt = $conn->prepare("UPDATE wallets SET balance = balance + ? WHERE customer_id = ?");
    $stmt->bind_param("di", $refundAmount, $customerId);
    $stmt->execute();

    // Insert transactions and revenue records
    insertCustomerTransaction(
        $transactionReference,        // Same transaction reference as cancellation fee
        $customerId,            // ID of the customer
        $refundAmount,          // Amount for the refund
        'credit',               // Transaction type
        'Order Refund',         // Payment method
        "Refund for Cancelled Order ID: $orderId" // Description
    );
    
    insertCustomerTransaction(
        $transactionReference,          // Unique transaction reference
        $customerId,              // ID of the customer
        $cancellationFee,        // Amount for the cancellation fee
        'debit',                  // Transaction type
        'Order Cancellation Fee on Delivery', // Payment method
        "Cancellation fee for Order ID: $orderId" // Description
    );
    insertTransaction(
        $transactionReference,          // Unique transaction reference
        $customerId, 
        $orderId,
        'credit',
        $cancellationFee,        // Amount for the cancellation fee
        $payment_method,                  // Transaction type
        $status,// Payment method
        $revenue_type_id = 4
    );
    $status = 'Cancelled';
    $revenue_type_id = 4;
    insertRevenue($orderId, $customerId, $totalAmount, $refundAmount, $cancellationFee, $orderDate, $status, $revenue_type_id);

   
}

function handleOrderDelivery($orderId, $driverId, $transactionReference, $status) {
    global $conn;

    // Fetch order and calculate fees
    $stmt = $conn->prepare("SELECT total_amount, customer_id, order_date FROM orders WHERE order_id = ?");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $stmt->bind_result($totalAmount, $customerId, $orderDate);
    $stmt->fetch();
    $stmt->close();

    $cancellationFee = 0.7 * $totalAmount;
    $refundAmount = $totalAmount - $cancellationFee;

    // Update order status
    $status = 'Completed';
    updateOrderStatus($orderId, $driverId, $status, STATUS_DELIVERED);

    // Insert revenue and transactions
    $stmt = $conn->prepare("SELECT total_amount, customer_id, payment_method FROM orders WHERE order_id = ?");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $stmt->bind_result($totalAmount, $customerId, $payment_method);
    $stmt->fetch();
    $stmt->close();

    insertCustomerTransaction(
        $transactionReference,        // Same transaction reference as cancellation fee
        $customerId,            // ID of the customer
        $refundAmount,          // Amount for the refund
        'debit',               // Transaction type
        $payment_method,         // Payment method
        "Food Order Payment for Order ID: $orderId" // Description
    );

    insertTransaction(
        $transactionReference,          // Unique transaction reference
        $customerId, 
        $orderId,
        'credit',
        $cancellationFee,        // Amount for the cancellation fee
        $payment_method,                  // Transaction type
        $status,// Payment method
        $revenue_type_id = 4
    );

    $status = 'Completed';
    $revenue_type_id = 2;
    insertRevenue($orderId, $customerId, $totalAmount, $refundAmount, $cancellationFee, $orderDate, $status, $revenue_type_id);
}

function updateOrderStatus($orderId, $driverId, $status, $delivery_status) {
    global $conn;
    $stmt = $conn->prepare("UPDATE orders SET status = ?, delivery_status = ? WHERE order_id = ? AND driver_id = ?");
    $stmt->bind_param("ssii", $status, $deivery_status, $orderId, $driverId);
    $stmt->execute();
}

function insertCustomerTransaction($transactionRef, $customerId, $amount, $transactionType, $paymentMethod, $description) {
    global $conn;

    // Prepare the SQL statement with the desired format
    $stmt = $conn->prepare(
        "INSERT INTO customer_transactions 
        (transaction_ref, customer_id, amount, date_created, transaction_type, payment_method, description) 
        VALUES (?, ?, ?, NOW(), ?, ?, ?)"
    );

    // Bind parameters to the query
    $stmt->bind_param("sidsss", $transactionRef, $customerId, $amount, $transactionType, $paymentMethod, $description);

    // Execute the query
    $stmt->execute();

    // Check for errors
    if ($stmt->error) {
        throw new Exception("Failed to insert transaction: " . $stmt->error);
    }

    // Close the statement
    $stmt->close();
}

function insertRevenue($order_id, $customerId, $totalAmount, $balance, $retained_amount, $orderDate, $status, $revenue_type_id) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO revenue (order_id, customer_id, total_amount, refunded_amount, retained_amount, transaction_date, status, updated_at, revenue_type_id)  VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
    $stmt->bind_param("iidddssi", $order_id, $customerId, $totalAmount, $balance, $retained_amount, $orderDate, $status, $revenue_type_id);
    $stmt->execute();

    // Insert into revenue table
}

function insertTransaction($transactionReference, $customerId, $order_id, $transaction_type, $fee, $paymentMethod, $status, $revenue_type_id ) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO transactions 
        (transaction_ref, customer_id, order_id, transaction_type, amount, transaction_date, payment_method, status, created_at, revenue_type_id) 
        VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, NOW(), ?)"
    );
    $stmt->bind_param("siisdssi", $transactionReference, $customerId, $order_id, $transaction_type, $fee, $paymentMethod, $status, $revenue_type_id);
    $stmt->execute();

}

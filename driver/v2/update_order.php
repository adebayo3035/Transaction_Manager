<?php
include('config.php');
session_start();

$data = json_decode(file_get_contents("php://input"), true);

// Constants for statuses and configuration
const STATUS_CANCELLED = 'Cancelled on Delivery';
const STATUS_DELIVERED = 'Delivered';
const STATUS_IN_TRANSIT = 'In Transit';
const CANCELLATION_FEE_PERCENTAGE = 0.7;

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
    $orderDetails = getOrderDetailsWithCustomer($orderId, $driverId);
    if ($orderDetails['delivery_pin'] !== $deliveryPin) {
        respond(false, "Invalid delivery pin. Please try again.");
    }
}

if ($orderStatus === STATUS_CANCELLED) {
    $orderDetails = getOrderDetailsWithCustomer($orderId, $driverId);
    if ($orderDetails['is_credit']) {
        respond(false, 'You cannot cancel an order placed on credit.');
    }
}

// Begin transaction
$conn->begin_transaction();

try {
    $transactionReference = generateTransactionReference();

    if ($orderStatus === STATUS_CANCELLED) {
        handleOrderCancellation($orderId, $driverId, $data['cancelReason'], $transactionReference);
    } elseif ($orderStatus === STATUS_DELIVERED) {
        handleOrderDelivery($orderId, $driverId, $transactionReference);
    } else {
        $status = 'Pending';
        updateOrderStatus($orderId, $driverId, $status, $orderStatus);
    }

    // Update driver status to 'Available' if Delivered or Cancelled
    if ($orderStatus === STATUS_CANCELLED || $orderStatus === STATUS_DELIVERED) {
        $sql = "UPDATE driver SET status = 'Available' WHERE id = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("i", $driverId);
            $stmt->execute();
            $stmt->close();
        } else {
            throw new Exception("Failed to update driver status.");
        }
    }

    $conn->commit();
    respond(true, "Your Order has been successfully $orderStatus");
} catch (Exception $e) {
    $conn->rollback();
    respond(false, "Transaction failed: " . $e->getMessage());
}

$conn->close();

/**
 * Function definitions
 */

function respond($success, $message)
{
    echo json_encode(["success" => $success, "message" => $message]);
    exit();
}

function isInvalidTransition($current, $target)
{
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

function getOrderDetailsWithCustomer($orderId, $driverId)
{
    global $conn;
    $sql = "SELECT total_amount, customer_id, order_date, delivery_pin, is_credit 
            FROM orders WHERE order_id = ? AND driver_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $orderId, $driverId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function generateTransactionReference()
{
    $prefix = 'TRX';
    $uniqueId = uniqid($prefix, true);
    $randomNumber = mt_rand(1000, 9999);
    return strtoupper(str_replace('.', '', $uniqueId . $randomNumber));
}

function handleOrderCancellation($orderId, $driverId, $cancelReason, $transactionReference)
{
    global $conn;

    $orderDetails = getOrderDetailsWithCustomer($orderId, $driverId);
    $totalAmount = $orderDetails['total_amount'];
    $customerId = $orderDetails['customer_id'];
    $cancellationFee = CANCELLATION_FEE_PERCENTAGE * $totalAmount;
    $refundAmount = $totalAmount - $cancellationFee;
    $transactionDate = $orderDetails['order_date'];

    // Update order status
    $stmt = $conn->prepare("UPDATE orders SET status = 'Cancelled', delivery_status = 'Cancelled on Delivery', cancellation_reason = ? WHERE order_id = ?");
    $stmt->bind_param("si", $cancelReason, $orderId);
    if (!$stmt->execute()) {
        throw new Exception("Failed to update order status: " . $stmt->error);
    }
    // Update Order Details table
    $stmt = $conn->prepare("UPDATE order_details SET status = 'Cancelled' WHERE order_id = ?");
    $stmt->bind_param("i", $orderId);
    if (!$stmt->execute()) {
        throw new Exception("Failed to update order status: " . $stmt->error);
    }

    // Update customer wallet
    $stmt = $conn->prepare("UPDATE wallets SET balance = balance + ? WHERE customer_id = ?");
    $stmt->bind_param("di", $refundAmount, $customerId);
    if (!$stmt->execute()) {
        throw new Exception("Failed to update customer wallet: " . $stmt->error);
    }

    // Update food stock quantities
    $stmt = $conn->prepare("SELECT food_id, quantity FROM order_details WHERE order_id = ?");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $foodId = $row['food_id'];
        $quantity = $row['quantity'];
        $updateFoodStmt = $conn->prepare("UPDATE food SET available_quantity = available_quantity + ? WHERE food_id = ?");
        $updateFoodStmt->bind_param("ii", $quantity, $foodId);
        $updateFoodStmt->execute();
        $updateFoodStmt->close();
    }

    $stmt->close();

    // Insert transactions and revenue
    $revenueTypeId = 4;
    insertCustomerTransaction($transactionReference, $customerId, $refundAmount, 'credit', 'Order Refund', "Refund for Cancelled Order ID: $orderId on Delivery");
    insertCustomerTransaction($transactionReference, $customerId, $cancellationFee, 'debit', 'Order Cancellation Fee on Delivery', "Cancellation fee for Order ID: $orderId on Delivery");
    insertTransaction($transactionReference, $customerId, $orderId, 'Credit', $cancellationFee, $transactionDate, "Direct Debit Cancelled order on Delivery.", 'Completed', $revenueTypeId);
    insertRevenue($orderId, $customerId, $totalAmount, $refundAmount, $cancellationFee, $orderDetails['order_date'], 'Cancelled', 4);
}


// function to Handle Paid Delivery
function handlePaidOrderDelivery($orderId, $driverId, $transactionReference, $orderDetails)
{
    global $conn;

    $customerId = $orderDetails['customer_id'];
    $transactionDate = $orderDetails['order_date'];
    $amount = $orderDetails['total_amount']; // Actual amount
    $status = 'Completed';
    $revenueTypeId = 2;

    // Start transaction
    $conn->begin_transaction();

    try {
        // Update order status in the database
        $stmt = $conn->prepare("UPDATE order_details SET status = 'Delivered' WHERE order_id = ?");
        $stmt->bind_param("i", $orderId);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update order status: " . $stmt->error);
        }

        // Update order status
        updateOrderStatus($orderId, $driverId, $status, STATUS_DELIVERED);

        // Insert customer transaction with actual amount
        insertCustomerTransaction(
            $transactionReference,
            $customerId,
            $amount,
            'debit',
            'Direct Debit',
            "Food Order Payment for Order ID: $orderId",
        );

        // Insert transaction record with actual amount
        insertTransaction(
            $transactionReference,
            $customerId,
            $orderId,
            'Credit',
            $amount,
            $transactionDate,
            "Food Order Payment for Order ID: $orderId",
            'Completed',
            $revenueTypeId,
        );

        // Insert revenue record with actual amount
        insertRevenue($orderId, $customerId, $amount, 0, $amount, $transactionDate, $status, $revenueTypeId);

        // Commit the transaction
        $conn->commit();
        respond(true, "Outright payment order successfully delivered.");
    } catch (Exception $e) {
        $conn->rollback();
        error_log($e->getMessage(), 0);
        respond(false, "An error occurred while delivering the outright payment order. Please try again.");
    }
}

// Handle Order Delivery on Credit
function handleCreditOrderDelivery($orderId, $driverId, $transactionReference, $orderDetails)
{
    global $conn;

    $customerId = $orderDetails['customer_id'];
    $transactionDate = $orderDetails['order_date'];
    $amount = 0.00; // Amount is 0 for credit orders
    $status = 'Completed';
    $revenueTypeId = 2;

    // Start transaction
    $conn->begin_transaction();

    try {
        // Update order status in the database
        $stmt = $conn->prepare("UPDATE order_details SET status = 'Delivered' WHERE order_id = ?");
        $stmt->bind_param("i", $orderId);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update order status: " . $stmt->error);
        }

        // Update order status
        updateOrderStatus($orderId, $driverId, $status, STATUS_DELIVERED);

        // Insert customer transaction with zero amount
        insertCustomerTransaction(
            $transactionReference,
            $customerId,
            $amount,
            'debit',
            'Direct Debit',
            "Food Order Payment for Order ID: $orderId",
        );

        // Insert transaction record with zero amount
        insertTransaction(
            $transactionReference,
            $customerId,
            $orderId,
            'Credit',
            $amount,
            $transactionDate,
            "Food Order Payment for Order ID: $orderId",
            'Completed',
            $revenueTypeId,
            
        );

        // Insert revenue record with zero amount
        insertRevenue($orderId, $customerId, $amount, 0, $amount, $transactionDate, $status, $revenueTypeId);

        // Commit the transaction
        $conn->commit();
        respond(true, "Credit order successfully delivered.");
    } catch (Exception $e) {
        $conn->rollback();
        error_log($e->getMessage(), 0);
        respond(false, "An error occurred while delivering the credit order. Please try again.");
    }
}

function handleOrderDelivery($orderId, $driverId, $transactionReference)
{
    try {
        $orderDetails = getOrderDetailsWithCustomer($orderId, $driverId);

        if ($orderDetails['is_credit']) {
            handleCreditOrderDelivery($orderId, $driverId, $transactionReference, $orderDetails);
        } else {
            handlePaidOrderDelivery($orderId, $driverId, $transactionReference, $orderDetails);
        }
    } catch (Exception $e) {
        error_log($e->getMessage(), 0);
        respond(false, "An error occurred while handling the order delivery. Please try again.");
    }
}



function updateOrderStatus($orderId, $driverId, $status, $delivery_status)
{
    global $conn;
    $stmt = $conn->prepare("UPDATE orders SET status = ?, delivery_status = ? WHERE order_id = ? AND driver_id = ?");
    $stmt->bind_param("ssii", $status, $delivery_status, $orderId, $driverId);
    $stmt->execute();
}

function insertCustomerTransaction($transactionRef, $customerId, $amount, $transactionType, $paymentMethod, $description)
{
    global $conn;
    // Prepare the insert statement
    $stmt = $conn->prepare(
        "INSERT INTO customer_transactions 
        (transaction_ref, customer_id, amount, date_created, transaction_type, payment_method, description) 
        VALUES (?, ?, ?, NOW(), ?, ?, ?)"
    );

    $stmt->bind_param("sidsss", $transactionRef, $customerId, $amount, $transactionType, $paymentMethod, $description);

    if (!$stmt->execute()) {
        throw new Exception("Failed to insert customer transaction: " . $stmt->error);
    }

    $stmt->close();
}

function insertTransaction($transactionRef,$customerId,$orderId,$transactionType,$amount,$transaction_date,$paymentMethod,$status, $revenueTypeId,) {
    global $conn;
   
    $stmt = $conn->prepare(
        "INSERT INTO transactions 
        (transaction_ref, customer_id, order_id, transaction_type, amount, transaction_date, payment_method, status, created_at, updated_at, revenue_type_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?)"
    );

    $stmt->bind_param(
        "siisdsssi",
        $transactionRef,
        $customerId,
        $orderId,
        $transactionType,
        $amount,
        $transaction_date,
        $paymentMethod,
        $status,
        $revenueTypeId
    );

    if (!$stmt->execute()) {
        throw new Exception("Failed to insert transaction: " . $stmt->error);
    }

    $stmt->close();
}


function insertRevenue($orderId, $customerId, $totalAmount, $balance, $retained_amount, $orderDate, $status, $revenue_type_id)
{
    global $conn;
    $stmt = $conn->prepare("INSERT INTO revenue (order_id, customer_id, total_amount, refunded_amount, retained_amount, transaction_date, status, updated_at, revenue_type_id) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
    $stmt->bind_param("iidddssi", $orderId, $customerId, $totalAmount, $balance, $retained_amount, $orderDate, $status, $revenue_type_id);
    if (!$stmt->execute()) {
        throw new Exception("Failed to insert revenue: " . $stmt->error);
    }
}

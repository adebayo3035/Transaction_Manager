<?php
include('config.php');
session_start();

$data = json_decode(file_get_contents("php://input"), true);
$driver_Id = $_SESSION['driver_id'];
checkDriverSession($driver_Id);
logActivity("Session validated successfully for Driver ID: $driver_Id.");

// Constants for statuses and configuration
const STATUS_CANCELLED = 'Cancelled on Delivery';
const STATUS_DELIVERED = 'Delivered';
const STATUS_IN_TRANSIT = 'In Transit';
const CANCELLATION_FEE_PERCENTAGE = 0.7;

// Validate inputs
if (!isset($data['id'], $data['currentStatus'], $data['orderStatus'])) {
    logActivity("Invalid order request - Missing order details.");
    respond(false, "Invalid order - Order details missing.", 400);
}

$orderId = $data['id'];
$currentStatus = $data['currentStatus'];
$orderStatus = $data['orderStatus'];
$driverId = $_SESSION['driver_id'];

logActivity("Order update request received. Order ID: {$orderId}, Current Status: {$currentStatus}, New Status: {$orderStatus}, Driver ID: {$driverId}");

// Check invalid transitions
if (isInvalidTransition($currentStatus, $orderStatus)) {
    logActivity("Invalid status transition - Order ID: {$orderId}, From {$currentStatus} to {$orderStatus}");
    respond(false, "Invalid Order Status - From {$currentStatus} to {$orderStatus}", 400);
}

// Verify delivery pin if required
if (($orderStatus === STATUS_DELIVERED || $orderStatus === STATUS_CANCELLED) && isset($data['deliveryPin'])) {
    $deliveryPin = $data['deliveryPin'];
    $orderDetails = getOrderDetailsWithCustomer($orderId, $driverId);

    if ($orderDetails['delivery_pin'] !== $deliveryPin) {
        logActivity("Invalid delivery pin attempt - Order ID: {$orderId}, Driver ID: {$driverId}");
        respond(false, "Invalid delivery pin. Please try again.", 403);
    }
}

// Restrict credit order cancellation
if ($orderStatus === STATUS_CANCELLED) {
    $orderDetails = getOrderDetailsWithCustomer($orderId, $driverId);

    if ($orderDetails['is_credit']) {
        logActivity("Credit order cancellation attempt blocked - Order ID: {$orderId}, Driver ID: {$driverId}");
        respond(false, "You cannot cancel an order placed on credit.", 403);
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

// function respond($success, $message)
// {
//     echo json_encode(["success" => $success, "message" => $message]);
//     exit();
// }

function respond($success, $message, $statusCode = 200)
{
    http_response_code($statusCode);
    logActivity("Response Sent - Success: " . ($success ? 'true' : 'false') . ", Message: {$message}, Status Code: {$statusCode}");
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

function updateDriverStatus($conn, $driverId, $status)
{
    $sql = "UPDATE driver SET status = ? WHERE id = ?";

    try {
        logActivity("Attempting to update driver status. Driver ID: {$driverId}, New Status: {$status}");

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            logActivity("Failed to prepare statement for driver status update. Error: " . $conn->error);
            throw new Exception("Failed to prepare statement: " . $conn->error);
        }

        $stmt->bind_param("si", $status, $driverId);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            logActivity("Driver status updated successfully. Driver ID: {$driverId}, New Status: {$status}");
        } else {
            logActivity("No changes made to driver status. Driver ID: {$driverId}");
        }

        $stmt->close();
    } catch (Exception $e) {
        logActivity("Driver status update failed. Driver ID: {$driverId}, Error: " . $e->getMessage());
        throw new Exception("Failed to update driver status.");
    }
}

function isInvalidTransition($current, $target)
{
    logActivity("Checking status transition validity. From: {$current}, To: {$target}");

    $invalidTransitions = [
        ["from" => "Assigned", "to" => STATUS_DELIVERED],
        ["from" => "Assigned", "to" => STATUS_CANCELLED],
        ["from" => STATUS_IN_TRANSIT, "to" => STATUS_IN_TRANSIT],
    ];

    foreach ($invalidTransitions as $transition) {
        if ($current === $transition['from'] && $target === $transition['to']) {
            logActivity("Invalid status transition detected. From: {$current}, To: {$target}");
            return true;
        }
    }
    logActivity("Status transition is valid. From: {$current}, To: {$target}");
    return false;
}


function getOrderDetailsWithCustomer($orderId, $driverId)
{
    global $conn;
    logActivity("Fetching order details. Order ID: {$orderId}, Driver ID: {$driverId}");

    $sql = "SELECT total_amount, delivery_fee, customer_id, order_date, delivery_pin, is_credit 
            FROM orders WHERE order_id = ? AND driver_id = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        logActivity("Failed to prepare statement for fetching order details. Error: " . $conn->error);
        return null;
    }

    $stmt->bind_param("ii", $orderId, $driverId);
    $stmt->execute();

    $result = $stmt->get_result();
    $orderDetails = $result->fetch_assoc();

    if ($orderDetails) {
        logActivity("Order details retrieved successfully. Order ID: {$orderId}");
    } else {
        logActivity("No order details found for Order ID: {$orderId}, Driver ID: {$driverId}");
    }

    return $orderDetails;
}

function generateTransactionReference()
{
    $prefix = 'TRX';
    $uniqueId = uniqid($prefix, true);
    $randomNumber = mt_rand(1000, 9999);
    $reference = strtoupper(str_replace('.', '', $uniqueId . $randomNumber));

    logActivity("Generated transaction reference: {$reference}");

    return $reference;
}

function handleOrderCancellation($orderId, $driverId, $cancelReason, $transactionReference)
{
    global $conn;

    // Log function entry
    logActivity("Entering handleOrderCancellation function for order ID: $orderId, driver ID: $driverId.");

    try {
        // Retrieve order details
        $orderDetails = getOrderDetailsWithCustomer($orderId, $driverId);
        $totalAmount = $orderDetails['total_amount'];
        $customerId = $orderDetails['customer_id'];
        $delivery_fee = $orderDetails['delivery_fee'];
        $cancellationFee = CANCELLATION_FEE_PERCENTAGE * $totalAmount;
        $refundAmount = $totalAmount - $cancellationFee;
        $transactionDate = $orderDetails['order_date'];

        // Log order details
        logActivity("Order details retrieved - Total Amount: $totalAmount, Customer ID: $customerId, Delivery Fee: $delivery_fee, Cancellation Fee: $cancellationFee, Refund Amount: $refundAmount.");

        // Update order status
        $queryUpdateOrder = "UPDATE orders SET status = 'Cancelled', delivery_status = 'Cancelled on Delivery', cancellation_reason = ? WHERE order_id = ?";
        $stmt = $conn->prepare($queryUpdateOrder);

        if ($stmt === false) {
            throw new Exception("Failed to prepare SQL statement for updating order status.");
        }

        // Log SQL query and parameters
        logActivity("Executing SQL query: $queryUpdateOrder | Params: [$cancelReason, $orderId]");

        $stmt->bind_param("si", $cancelReason, $orderId);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update order status: " . $stmt->error);
        }

        // Log successful order status update
        logActivity("Order status updated to 'Cancelled' for order ID: $orderId.");

        // Update Order Details table
        $queryUpdateOrderDetails = "UPDATE order_details SET status = 'Cancelled' WHERE order_id = ?";
        $stmt = $conn->prepare($queryUpdateOrderDetails);

        if ($stmt === false) {
            throw new Exception("Failed to prepare SQL statement for updating order details status.");
        }

        // Log SQL query and parameters
        logActivity("Executing SQL query: $queryUpdateOrderDetails | Params: [$orderId]");

        $stmt->bind_param("i", $orderId);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update order details status: " . $stmt->error);
        }

        // Log successful order details status update
        logActivity("Order details status updated to 'Cancelled' for order ID: $orderId.");

        // Update customer wallet
        $queryUpdateWallet = "UPDATE wallets SET balance = balance + ? WHERE customer_id = ?";
        $stmt = $conn->prepare($queryUpdateWallet);

        if ($stmt === false) {
            throw new Exception("Failed to prepare SQL statement for updating customer wallet.");
        }

        // Log SQL query and parameters
        logActivity("Executing SQL query: $queryUpdateWallet | Params: [$refundAmount, $customerId]");

        $stmt->bind_param("di", $refundAmount, $customerId);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update customer wallet: " . $stmt->error);
        }

        // Log successful wallet update
        logActivity("Customer wallet updated for customer ID: $customerId. Refund Amount: $refundAmount.");

        // Update driver status
        updateDriverStatus($conn, $driverId, 'Available');
        logActivity("Driver status updated to 'Available' for driver ID: $driverId.");

        // Update food stock quantities
        $querySelectFood = "SELECT food_id, quantity FROM order_details WHERE order_id = ?";
        $stmt = $conn->prepare($querySelectFood);

        if ($stmt === false) {
            throw new Exception("Failed to prepare SQL statement for selecting food details.");
        }

        // Log SQL query and parameters
        logActivity("Executing SQL query: $querySelectFood | Params: [$orderId]");

        $stmt->bind_param("i", $orderId);
        if (!$stmt->execute()) {
            throw new Exception("Failed to select food details: " . $stmt->error);
        }

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $foodId = $row['food_id'];
            $quantity = $row['quantity'];

            $queryUpdateFood = "UPDATE food SET available_quantity = available_quantity + ? WHERE food_id = ?";
            $updateFoodStmt = $conn->prepare($queryUpdateFood);

            if ($updateFoodStmt === false) {
                throw new Exception("Failed to prepare SQL statement for updating food stock.");
            }

            // Log SQL query and parameters
            logActivity("Executing SQL query: $queryUpdateFood | Params: [$quantity, $foodId]");

            $updateFoodStmt->bind_param("ii", $quantity, $foodId);
            if (!$updateFoodStmt->execute()) {
                throw new Exception("Failed to update food stock: " . $updateFoodStmt->error);
            }

            $updateFoodStmt->close();
        }

        $stmt->close();

        // Log successful food stock update
        logActivity("Food stock updated for order ID: $orderId.");

        // Insert transactions and revenue
        $revenueTypeId = 4;
        insertCustomerTransaction($transactionReference, $customerId, $refundAmount, 'credit', 'Order Refund', "Refund for Cancelled Order ID: $orderId on Delivery");
        insertCustomerTransaction($transactionReference, $customerId, $cancellationFee, 'debit', 'Order Cancellation Fee on Delivery', "Cancellation fee for Order ID: $orderId on Delivery");
        insertTransaction($transactionReference, $customerId, $orderId, 'Credit', $cancellationFee, $transactionDate, "Direct Debit Cancelled order on Delivery.", 'Completed', $revenueTypeId);
        insertRevenue($orderId, $customerId, $totalAmount, $refundAmount, $cancellationFee, $orderDetails['order_date'], 'Cancelled', 4);
        creditDeliveryFee($conn, $orderId, $driverId, $delivery_fee, $transactionReference, $customerId, $transactionDate, 8);

        // Log successful transaction and revenue insertion
        logActivity("Transactions and revenue records inserted for order ID: $orderId.");

        // Log function exit
        logActivity("Exiting handleOrderCancellation function successfully for order ID: $orderId.");
    } catch (Exception $e) {
        // Log error
        logActivity("Error in handleOrderCancellation function: " . $e->getMessage());
        throw $e; // Re-throw the exception for further handling
    }
}

// function to Handle Paid Delivery
function handlePaidOrderDelivery($orderId, $driverId, $transactionReference, $orderDetails)
{
    global $conn;

    // Log function entry
    logActivity("Entering handlePaidOrderDelivery function for order ID: $orderId, driver ID: $driverId.");

    $customerId = $orderDetails['customer_id'];
    $transactionDate = $orderDetails['order_date'];
    $amount = $orderDetails['total_amount']; // Actual amount
    $delivery_fee = $orderDetails['delivery_fee'];
    $status = 'Completed';
    $revenueTypeId = 2;

    // Log order details
    logActivity("Order details - Customer ID: $customerId, Amount: $amount, Delivery Fee: $delivery_fee, Status: $status, Revenue Type ID: $revenueTypeId.");

    // Start transaction
    $conn->begin_transaction();
    logActivity("Transaction started for order ID: $orderId.");

    try {
        // Update order status in the database
        $queryUpdateOrderDetails = "UPDATE order_details SET status = 'Delivered' WHERE order_id = ?";
        $stmt = $conn->prepare($queryUpdateOrderDetails);

        if ($stmt === false) {
            throw new Exception("Failed to prepare SQL statement for updating order status.");
        }

        // Log SQL query and parameters
        logActivity("Executing SQL query: $queryUpdateOrderDetails | Params: [$orderId]");

        $stmt->bind_param("i", $orderId);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update order status: " . $stmt->error);
        }

        // Log successful order status update
        logActivity("Order status updated to 'Delivered' for order ID: $orderId.");

        // Update order status
        updateOrderStatus($orderId, $driverId, $status, STATUS_DELIVERED);
        logActivity("Order status updated to 'Completed' for order ID: $orderId.");

        // Update Driver Status
        updateDriverStatus($conn, $driverId, 'Available');
        logActivity("Driver status updated to 'Available' for driver ID: $driverId.");

        // Insert customer transaction with actual amount
        insertCustomerTransaction(
            $transactionReference,
            $customerId,
            $amount,
            'debit',
            'Direct Debit',
            "Food Order Payment for Order ID: $orderId",
        );
        logActivity("Customer transaction inserted for order ID: $orderId.");

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
        logActivity("Transaction record inserted for order ID: $orderId.");

        // Insert revenue record with actual amount
        insertRevenue($orderId, $customerId, $amount, 0, $amount, $transactionDate, $status, $revenueTypeId);
        logActivity("Revenue record inserted for order ID: $orderId.");

        // Credit delivery fee
        creditDeliveryFee($conn, $orderId, $driverId, $delivery_fee, $transactionReference, $customerId, $transactionDate, 8);
        logActivity("Delivery fee credited for order ID: $orderId.");

        // Commit the transaction
        $conn->commit();
        logActivity("Transaction committed successfully for order ID: $orderId.");

        // Log function exit
        logActivity("Exiting handlePaidOrderDelivery function successfully for order ID: $orderId.");

        respond(true, "Outright payment order successfully delivered.");
    } catch (Exception $e) {
        // Rollback the transaction
        $conn->rollback();
        logActivity("Transaction rolled back for order ID: $orderId. Error: " . $e->getMessage());

        // Log error
        error_log($e->getMessage(), 0);
        respond(false, "An error occurred while delivering the outright payment order. Please try again.");
    }
}
// Handle Order Delivery on Credit
function handleCreditOrderDelivery($orderId, $driverId, $transactionReference, $orderDetails)
{
    global $conn;

    // Log function entry
    logActivity("Entering handleCreditOrderDelivery function for order ID: $orderId, driver ID: $driverId.");

    $customerId = $orderDetails['customer_id'];
    $transactionDate = $orderDetails['order_date'];
    $delivery_fee = $orderDetails['delivery_fee'];
    $amount = 0.00; // Amount is 0 for credit orders
    $status = 'Completed';
    $revenueTypeId = 2;

    // Log order details
    logActivity("Order details - Customer ID: $customerId, Amount: $amount, Delivery Fee: $delivery_fee, Status: $status, Revenue Type ID: $revenueTypeId.");

    // Start transaction
    $conn->begin_transaction();
    logActivity("Transaction started for order ID: $orderId.");

    try {
        // Update order status in the database
        $queryUpdateOrderDetails = "UPDATE order_details SET status = 'Delivered' WHERE order_id = ?";
        $stmt = $conn->prepare($queryUpdateOrderDetails);

        if ($stmt === false) {
            throw new Exception("Failed to prepare SQL statement for updating order status.");
        }

        // Log SQL query and parameters
        logActivity("Executing SQL query: $queryUpdateOrderDetails | Params: [$orderId]");

        $stmt->bind_param("i", $orderId);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update order status: " . $stmt->error);
        }

        // Log successful order status update
        logActivity("Order status updated to 'Delivered' for order ID: $orderId.");

        // Update order status
        updateOrderStatus($orderId, $driverId, $status, STATUS_DELIVERED);
        logActivity("Order status updated to 'Completed' for order ID: $orderId.");

        // Update Driver Status
        updateDriverStatus($conn, $driverId, 'Available');
        logActivity("Driver status updated to 'Available' for driver ID: $driverId.");

        // Insert customer transaction with zero amount
        insertCustomerTransaction(
            $transactionReference,
            $customerId,
            $amount,
            'debit',
            'Direct Debit',
            "Food Order Payment for Order ID: $orderId",
        );
        logActivity("Customer transaction inserted for order ID: $orderId.");

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
        logActivity("Transaction record inserted for order ID: $orderId.");

        // Insert revenue record with zero amount
        insertRevenue($orderId, $customerId, $amount, 0, $amount, $transactionDate, $status, $revenueTypeId);
        logActivity("Revenue record inserted for order ID: $orderId.");

        // Credit delivery fee
        creditDeliveryFee($conn, $orderId, $driverId, $delivery_fee, $transactionReference, $customerId, $transactionDate, 8);
        logActivity("Delivery fee credited for order ID: $orderId.");

        // Commit the transaction
        $conn->commit();
        logActivity("Transaction committed successfully for order ID: $orderId.");

        // Log function exit
        logActivity("Exiting handleCreditOrderDelivery function successfully for order ID: $orderId.");

        respond(true, "Credit order successfully delivered.");
    } catch (Exception $e) {
        // Rollback the transaction
        $conn->rollback();
        logActivity("Transaction rolled back for order ID: $orderId. Error: " . $e->getMessage());

        // Log error
        error_log($e->getMessage(), 0);
        respond(false, "An error occurred while delivering the credit order. Please try again.");
    }
}

function handleOrderDelivery($orderId, $driverId, $transactionReference)
{
    // Log function entry
    logActivity("Entering handleOrderDelivery function for order ID: $orderId, driver ID: $driverId.");

    try {
        // Fetch order details
        $orderDetails = getOrderDetailsWithCustomer($orderId, $driverId);

        // Log order details
        logActivity("Order details retrieved for order ID: $orderId. Is Credit: " . ($orderDetails['is_credit'] ? 'Yes' : 'No'));

        if ($orderDetails['is_credit']) {
            // Handle credit order delivery
            logActivity("Handling credit order delivery for order ID: $orderId.");
            handleCreditOrderDelivery($orderId, $driverId, $transactionReference, $orderDetails);
        } else {
            // Handle paid order delivery
            logActivity("Handling paid order delivery for order ID: $orderId.");
            handlePaidOrderDelivery($orderId, $driverId, $transactionReference, $orderDetails);
        }

        // Log function exit
        logActivity("Exiting handleOrderDelivery function successfully for order ID: $orderId.");
    } catch (Exception $e) {
        // Log error
        logActivity("Error in handleOrderDelivery function for order ID: $orderId. Error: " . $e->getMessage());
        error_log($e->getMessage(), 0);
        respond(false, "An error occurred while handling the order delivery. Please try again.");
    }
}

function updateOrderStatus($orderId, $driverId, $status, $delivery_status)
{
    global $conn;

    // Log function entry
    logActivity("Entering updateOrderStatus function for order ID: $orderId, driver ID: $driverId.");

    // Prepare the SQL query
    $query = "UPDATE orders SET status = ?, delivery_status = ? WHERE order_id = ? AND driver_id = ?";
    $stmt = $conn->prepare($query);

    if ($stmt === false) {
        // Log SQL preparation failure
        logActivity("Failed to prepare SQL statement for updating order status.");
        throw new Exception("Failed to prepare SQL statement for updating order status.");
    }

    // Log SQL query and parameters
    logActivity("Executing SQL query: $query | Params: [$status, $delivery_status, $orderId, $driverId]");

    $stmt->bind_param("ssii", $status, $delivery_status, $orderId, $driverId);
    if (!$stmt->execute()) {
        // Log SQL execution failure
        logActivity("Failed to execute SQL statement for updating order status.");
        throw new Exception("Failed to update order status: " . $stmt->error);
    }

    // Log successful order status update
    logActivity("Order status updated successfully for order ID: $orderId.");

    // Log function exit
    logActivity("Exiting updateOrderStatus function successfully for order ID: $orderId.");
}

function insertCustomerTransaction($transactionRef, $customerId, $amount, $transactionType, $paymentMethod, $description)
{
    global $conn;

    // Log function entry
    logActivity("Entering insertCustomerTransaction function for customer ID: $customerId.");

    // Prepare the SQL query
    $query = "INSERT INTO customer_transactions 
              (transaction_ref, customer_id, amount, date_created, transaction_type, payment_method, description) 
              VALUES (?, ?, ?, NOW(), ?, ?, ?)";
    $stmt = $conn->prepare($query);

    if ($stmt === false) {
        // Log SQL preparation failure
        logActivity("Failed to prepare SQL statement for inserting customer transaction.");
        throw new Exception("Failed to prepare SQL statement for inserting customer transaction.");
    }

    // Log SQL query and parameters
    logActivity("Executing SQL query: $query | Params: [$transactionRef, $customerId, $amount, $transactionType, $paymentMethod, $description]");

    $stmt->bind_param("sidsss", $transactionRef, $customerId, $amount, $transactionType, $paymentMethod, $description);
    if (!$stmt->execute()) {
        // Log SQL execution failure
        logActivity("Failed to execute SQL statement for inserting customer transaction.");
        throw new Exception("Failed to insert customer transaction: " . $stmt->error);
    }

    // Log successful transaction insertion
    logActivity("Customer transaction inserted successfully for customer ID: $customerId.");

    $stmt->close();

    // Log function exit
    logActivity("Exiting insertCustomerTransaction function successfully for customer ID: $customerId.");
}

function insertTransaction($transactionRef, $customerId, $orderId, $transactionType, $amount, $transaction_date, $paymentMethod, $status, $revenueTypeId)
{
    global $conn;

    // Log function entry
    logActivity("Entering insertTransaction function for order ID: $orderId, customer ID: $customerId.");

    // Prepare the SQL query
    $query = "INSERT INTO transactions 
              (transaction_ref, customer_id, order_id, transaction_type, amount, transaction_date, payment_method, status, created_at, updated_at, revenue_type_id) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?)";
    $stmt = $conn->prepare($query);

    if ($stmt === false) {
        // Log SQL preparation failure
        logActivity("Failed to prepare SQL statement for inserting transaction.");
        throw new Exception("Failed to prepare SQL statement for inserting transaction.");
    }

    // Log SQL query and parameters
    logActivity("Executing SQL query: $query | Params: [$transactionRef, $customerId, $orderId, $transactionType, $amount, $transaction_date, $paymentMethod, $status, $revenueTypeId]");

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
        // Log SQL execution failure
        logActivity("Failed to execute SQL statement for inserting transaction.");
        throw new Exception("Failed to insert transaction: " . $stmt->error);
    }

    // Log successful transaction insertion
    logActivity("Transaction inserted successfully for order ID: $orderId.");

    $stmt->close();

    // Log function exit
    logActivity("Exiting insertTransaction function successfully for order ID: $orderId.");
}

function insertRevenue($orderId, $customerId, $totalAmount, $balance, $retained_amount, $orderDate, $status, $revenue_type_id)
{
    global $conn;

    // Log function entry
    logActivity("Entering insertRevenue function for order ID: $orderId, customer ID: $customerId.");

    // Prepare the SQL query
    $query = "INSERT INTO revenue 
              (order_id, customer_id, total_amount, refunded_amount, retained_amount, transaction_date, status, updated_at, revenue_type_id) 
              VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
    $stmt = $conn->prepare($query);

    if ($stmt === false) {
        // Log SQL preparation failure
        logActivity("Failed to prepare SQL statement for inserting revenue.");
        throw new Exception("Failed to prepare SQL statement for inserting revenue.");
    }

    // Log SQL query and parameters
    logActivity("Executing SQL query: $query | Params: [$orderId, $customerId, $totalAmount, $balance, $retained_amount, $orderDate, $status, $revenue_type_id]");

    $stmt->bind_param("iidddssi", $orderId, $customerId, $totalAmount, $balance, $retained_amount, $orderDate, $status, $revenue_type_id);
    if (!$stmt->execute()) {
        // Log SQL execution failure
        logActivity("Failed to execute SQL statement for inserting revenue.");
        throw new Exception("Failed to insert revenue: " . $stmt->error);
    }

    // Log successful revenue insertion
    logActivity("Revenue inserted successfully for order ID: $orderId.");

    $stmt->close();

    // Log function exit
    logActivity("Exiting insertRevenue function successfully for order ID: $orderId.");
}

function creditDeliveryFee($conn, $orderId, $driverId, $amount, $transactionReference, $customerId, $transactionDate, $revenueTypeId)
{
    // Log function entry
    logActivity("Entering creditDeliveryFee function for order ID: $orderId, driver ID: $driverId.");

    // Start transaction
    $conn->begin_transaction();
    logActivity("Transaction started for crediting delivery fee for order ID: $orderId.");

    try {
        // Insert into the delivery_fee table
        $deliveryFeeSql = "INSERT INTO delivery_fee (order_id, driver_id, delivery_fee, status, created_at) VALUES (?, ?, ?, 'Credited', NOW())";
        $stmt = $conn->prepare($deliveryFeeSql);

        if ($stmt === false) {
            throw new Exception("Failed to prepare SQL statement for inserting delivery fee.");
        }

        // Log SQL query and parameters
        logActivity("Executing SQL query: $deliveryFeeSql | Params: [$orderId, $driverId, $amount]");

        $stmt->bind_param("iid", $orderId, $driverId, $amount);
        if (!$stmt->execute()) {
            throw new Exception("Failed to insert delivery fee: " . $stmt->error);
        }

        // Log successful delivery fee insertion
        logActivity("Delivery fee inserted successfully for order ID: $orderId.");

        // Update driver's wallet
        $walletUpdateSql = "UPDATE driver SET wallet_balance = wallet_balance + ? WHERE id = ?";
        $stmt = $conn->prepare($walletUpdateSql);

        if ($stmt === false) {
            throw new Exception("Failed to prepare SQL statement for updating driver's wallet.");
        }

        // Log SQL query and parameters
        logActivity("Executing SQL query: $walletUpdateSql | Params: [$amount, $driverId]");

        $stmt->bind_param("di", $amount, $driverId);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update driver's wallet: " . $stmt->error);
        }

        // Log successful wallet update
        logActivity("Driver's wallet updated successfully for driver ID: $driverId.");

        // Insert transaction for delivery fee
        insertTransaction($transactionReference, $customerId, $orderId, 'Debit', $amount, $transactionDate, "Driver Delivery Fee.", 'Completed', $revenueTypeId);
        logActivity("Transaction inserted successfully for delivery fee of order ID: $orderId.");

        // Commit the transaction
        $conn->commit();
        logActivity("Transaction committed successfully for crediting delivery fee for order ID: $orderId.");

        // Log function exit
        logActivity("Exiting creditDeliveryFee function successfully for order ID: $orderId.");
    } catch (Exception $e) {
        // Rollback the transaction
        $conn->rollback();
        logActivity("Transaction rolled back for crediting delivery fee for order ID: $orderId. Error: " . $e->getMessage());

        // Log error
        error_log("Failed to credit delivery fee: " . $e->getMessage());
        throw new Exception("Transaction failed.");
    }
}

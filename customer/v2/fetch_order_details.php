<?php
include 'config.php';
session_start();

try {
    // Validate session
    $customerId = $_SESSION["customer_id"] ?? null;
    logActivity("Starting order details retrieval process");
    
    if (!$customerId) {
        throw new Exception("No customer ID found in session");
    }
    
    checkSession($customerId);
    logActivity("Session validated successfully");

    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] != 'POST') {
        throw new Exception("Invalid request method. Only POST is allowed");
    }
    logActivity("Request method validation passed");

    // Process input
    logActivity("Reading and validating input data");
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON input: " . json_last_error_msg());
    }

    if (!isset($input['order_id'])) {
        throw new Exception("Order ID is required");
    }

    $orderId = $input['order_id'];
    logActivity("Processing order ID: $orderId");

    // Database operations
    logActivity("Preparing to fetch order details from database");
    $query = "
        SELECT 
            order_details.order_id,
            order_details.food_id, 
            order_details.quantity, 
            order_details.price_per_unit, 
            order_details.total_price, 
            food.food_name, 
            order_details.order_date, 
            order_details.status,
            orders.service_fee,
            orders.delivery_fee,
            orders.discount,
            orders.total_order,
            orders.total_amount,
            orders.delivery_pin,
            orders.delivery_status,
            orders.is_credit,
            driver.firstname AS driver_firstname, 
            driver.lastname AS driver_lastname,
            driver.phone_number AS driver_phoneNumber
        FROM order_details
        JOIN food ON order_details.food_id = food.food_id
        JOIN orders ON orders.order_id = order_details.order_id
        LEFT JOIN driver ON orders.driver_id = driver.id
        WHERE order_details.order_id = ? 
        AND orders.customer_id = ?";

    logActivity("Executing query: " . str_replace(["\n", "\r", "\t"], " ", $query));
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("ii", $orderId, $customerId);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $orderDetails = [];
    $recordCount = 0;

    while ($row = $result->fetch_assoc()) {
        $recordCount++;
        $orderDetails[] = $row;
    }
    logActivity("Fetched $recordCount order detail records");

    // Prepare response
    $response = [
        'success' => true,
        'order_details' => $orderDetails,
        'record_count' => $recordCount
    ];

    logActivity("Successfully processed request for order ID: $orderId");
    echo json_encode($response);

} catch (Exception $e) {
    $errorDetails = [
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ];
    
    logActivity("EXCEPTION: " . json_encode($errorDetails));
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => $e->getCode()
    ]);
} finally {
    // Ensure resources are cleaned up
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
        logActivity("Closed database statement");
    }
    
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
        logActivity("Closed database connection");
    }
    
    logActivity("Script execution completed");
}
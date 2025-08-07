<?php
include 'config.php';
header('Content-Type: application/json');

session_start();
$customerId = $_SESSION['customer_id'] ?? null;

if (!$customerId) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized. Please log in.']);
    logActivity("Unauthorized access attempt to order details endpoint.");
    exit;
}

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

$order_id = $input['order_id'];
logActivity("Processing order ID: $order_id");

if (!$order_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing order ID.']);
    logActivity("Missing order ID for customer_id: $customerId");
    exit;
}

try {
    // First query: Get order details
    $stmt = $conn->prepare("
        SELECT 
            o.order_id, 
            o.order_date, 
            o.status, 
            o.service_fee, 
            o.delivery_fee, 
            o.discount, 
            o.total_order, 
            o.total_amount, 
            o.delivery_pin, 
            o.delivery_status, 
            o.is_credit,
            d.firstname AS driver_firstname, 
            d.lastname AS driver_lastname, 
            d.phone_number AS driver_phoneNumber,
            d.id AS driverID, 
            d.photo AS driver_photo,
            od.food_id, 
            od.quantity, 
            od.price_per_unit, 
            od.total_price, 
            f.food_name
        FROM orders o
        INNER JOIN order_details od ON o.order_id = od.order_id
        INNER JOIN food f ON od.food_id = f.food_id  
        LEFT JOIN driver d ON o.driver_id = d.id  
        WHERE o.order_id = ? AND o.customer_id = ?
    ");

    $stmt->bind_param("ii", $order_id, $customerId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Order not found.']);
        logActivity("Order not found or not authorized. order_id: $order_id, customer_id: $customerId");
        exit;
    }

    $items = [];
    $orderInfo = null;

    while ($row = $result->fetch_assoc()) {
        if (!$orderInfo) {
            // Set shared order info once
            $orderInfo = [
                'order_id' => $row['order_id'],
                'order_date' => $row['order_date'],
                'status' => $row['status'],
                'service_fee' => $row['service_fee'],
                'delivery_fee' => $row['delivery_fee'],
                'discount' => $row['discount'],
                'total_order' => $row['total_order'],
                'total_amount' => $row['total_amount'],
                'delivery_pin' => $row['delivery_pin'],
                'delivery_status' => $row['delivery_status'],
                'is_credit' => $row['is_credit'],
                'driver_firstname' => $row['driver_firstname'],
                'driver_lastname' => $row['driver_lastname'],
                'driver_phoneNumber' => $row['driver_phoneNumber'],
                'driverID' => $row['driverID'],
                'driver_photo' => $row['driver_photo'],
            ];
        }

        // Add food-specific info
        $items[] = [
            'food_id' => $row['food_id'],
            'food_name' => $row['food_name'],
            'quantity' => $row['quantity'],
            'price_per_unit' => $row['price_per_unit'],
            'total_price' => $row['total_price']
        ];
    }

    // Second query: Check if order has been rated
    $ratingCheckStmt = $conn->prepare("
        SELECT COUNT(*) as rating_count 
        FROM order_ratings 
        WHERE order_id = ?
    ");
    $ratingCheckStmt->bind_param("i", $order_id);
    $ratingCheckStmt->execute();
    $ratingResult = $ratingCheckStmt->get_result();
    $ratingData = $ratingResult->fetch_assoc();
    $ratingCheckStmt->close();

    $is_rated = ($ratingData['rating_count'] > 0);

    // Final response with is_rated flag
    $response = $orderInfo;
    $response['items'] = $items;
    $response['is_rated'] = $is_rated;

    echo json_encode($response);
    logActivity("Fetched order details for order_id: $order_id, customer_id: $customerId. Items count: " . count($items) . ". Rated: " . ($is_rated ? 'Yes' : 'No'));

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch order details.']);
    logActivity("Error fetching order_id: $order_id for customer_id: $customerId. Exception: " . $e->getMessage());
}
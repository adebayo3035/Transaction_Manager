<?php
session_start();
include 'config.php';
$driverId = $_SESSION['driver_id'];
checkDriverSession($driverId);
logActivity("Session validated successfully for Driver ID: $driverId.");
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    logActivity("Received a POST request.");
    $input = json_decode(file_get_contents('php://input'), true);
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['order_id'])) {
        logActivity("Order ID is missing in the request.");
        echo json_encode(["success" => false, "message" => "Order ID is missing."]);
        exit();
    }
    $orderId = $input['order_id'];
    logActivity("Processing Pending Order Details for order ID: $orderId for driver ID: $driverId.");

    $query = "
    SELECT 
        order_details.food_id, 
        order_details.quantity, 
        food.food_name, 
        order_details.order_date, 
        order_details.status,
        orders.updated_at,
        orders.delivery_fee,
        orders.delivery_status,
        driver.firstname AS driver_firstname, 
        driver.lastname AS driver_lastname,
        customers.firstname AS customer_firstname,
        customers.lastname AS customer_lastname,
        customers.mobile_number AS customer_phone_number
    FROM order_details
    JOIN food ON order_details.food_id = food.food_id
    JOIN orders ON orders.order_id = order_details.order_id
    LEFT JOIN driver ON orders.driver_id = driver.id
    LEFT JOIN customers ON orders.customer_id = customers.customer_id
    WHERE order_details.order_id = ? 
    AND orders.driver_id = ?";

    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        logActivity("Failed to prepare the SQL statement.");
        echo json_encode(["success" => false, "message" => "Database error."]);
        exit();
    }

    $stmt->bind_param("ii", $orderId, $driverId);
    if (!$stmt->execute()) {
        logActivity("Failed to execute the SQL statement.");
        echo json_encode(["success" => false, "message" => "Database error."]);
        exit();
    }

    $result = $stmt->get_result();
    $orderDetails = [];
    while ($row = $result->fetch_assoc()) {
        $orderDetails[] = $row;
    }
    $stmt->close();

    logActivity("Successfully retrieved order details for order ID: $orderId.");

    echo json_encode(["success" => true, "order_details" => $orderDetails]);
} else {
    logActivity("Invalid request method received.");
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
}
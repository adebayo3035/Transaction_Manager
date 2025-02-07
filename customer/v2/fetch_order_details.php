<?php
include 'config.php';
session_start();

$customerId = $_SESSION["customer_id"];
checkSession($customerId);

// Log script execution start
logActivity("Script started for customer ID: $customerId");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate input
    if (!isset($input['order_id'])) {
        $errorMessage = "Order ID is missing.";
        logActivity("Error: $errorMessage for customer ID: $customerId");
        echo json_encode(["success" => false, "message" => $errorMessage]);
        exit();
    }

    $orderId = $input['order_id'];
    $customerId = $_SESSION['customer_id'];

    // Log order ID being processed
    logActivity("Fetching order details for order ID: $orderId, customer ID: $customerId");

    try {
        // Prepare and execute the query
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

        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $orderId, $customerId);
        $stmt->execute();
        $result = $stmt->get_result();

        $orderDetails = [];
        while ($row = $result->fetch_assoc()) {
            $orderDetails[] = $row;
        }
        $stmt->close();

        // Log successful data fetch
        logActivity("Successfully fetched order details for order ID: $orderId, customer ID: $customerId");

        // Return the response
        echo json_encode(["success" => true, "order_details" => $orderDetails]);
    } catch (Exception $e) {
        // Log the error
        $errorMessage = $e->getMessage();
        logActivity("Error fetching order details for order ID: $orderId, customer ID: $customerId. Error: $errorMessage");

        // Return the error response
        echo json_encode(["success" => false, "message" => $errorMessage]);
    }
} else {
    // Log invalid request method
    $errorMessage = "Invalid request method.";
    logActivity("Error: $errorMessage for customer ID: $customerId");

    // Return the error response
    echo json_encode(["success" => false, "message" => $errorMessage]);
}
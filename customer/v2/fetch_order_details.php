<?php
include 'config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['order_id'])) {
        echo json_encode(["success" => false, "message" => "Order ID is missing."]);
        exit();
    }

    $orderId = $input['order_id'];
    $customerId = $_SESSION['customer_id'];

    // $stmt = $conn->prepare("SELECT * FROM order_details WHERE order_id = ?");
    $query = "
    SELECT 
      order_details.food_id, 
      order_details.quantity, 
      order_details.price_per_unit, 
      order_details.total_price, 
      food.food_name, 
      order_details.order_date, 
      order_details.status,
      orders.service_fee,
      orders.delivery_fee,
      orders.total_order,
      orders.total_amount,
      orders.delivery_pin,
      orders.delivery_status,
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
    $stmt->bind_param("ii", $orderId , $customerId);
    $stmt->execute();
    $result = $stmt->get_result();

    $orderDetails = [];
    while ($row = $result->fetch_assoc()) {
        $orderDetails[] = $row;
    }
    $stmt->close();

    echo json_encode(["success" => true, "order_details" => $orderDetails]);
} else {
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
}

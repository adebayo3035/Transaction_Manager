<?php
// include 'config.php';
// session_start();

// if (!isset($_SESSION['customer_id'])) {
//     echo json_encode(["success" => false, "message" => "Not logged in."]);
//     exit();
// }
// if (!isset($input['order_id'])) {
//     echo json_encode(["success" => false, "message" => "Order ID is missing."]);
//     exit();
// }
// $orderId = $_POST['order_id'];
// $customerId = $_SESSION['customer_id'];

// $query = "SELECT order_details.food_id, order_details.quantity, order_details.price_per_unit, 
//                  order_details.total_price, food.food_name
//           FROM order_details
//           JOIN food ON order_details.food_id = food.food_id
//           WHERE order_details.order_id = ? AND EXISTS (SELECT 1 FROM orders WHERE orders.order_id = order_details.order_id AND orders.customer_id = ?)";
// $stmt = $conn->prepare($query);
// $stmt->bind_param("ii", $orderId, $customerId);
// $stmt->execute();
// $result = $stmt->get_result();

// $orderDetails = [];
// while ($row = $result->fetch_assoc()) {
//     $orderDetails[] = $row;
// }

// $stmt->close();
// $conn->close();

// echo json_encode(["success" => true, "order_details" => $orderDetails]);
?>



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
    $query = "SELECT order_details.food_id, order_details.quantity, order_details.price_per_unit, 
                 order_details.total_price, food.food_name
           FROM order_details
          JOIN food ON order_details.food_id = food.food_id
          
          WHERE order_details.order_id = ? AND EXISTS (SELECT 1 FROM orders WHERE orders.order_id = order_details.order_id AND orders.customer_id = ?)";
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

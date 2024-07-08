<?php
// include 'config.php';
// session_start();

// if ($_SERVER['REQUEST_METHOD'] == 'POST') {
//     $input = json_decode(file_get_contents('php://input'), true);
    
//     if (!isset($input['order_id'])) {
//         echo json_encode(["success" => false, "message" => "Order ID is missing."]);
//         exit();
//     }

//     $orderId = $input['order_id'];
//     // $customerId = $_SESSION['customer_id'];

//     // $stmt = $conn->prepare("SELECT * FROM order_details WHERE order_id = ?");
//     $query = "SELECT order_details.food_id, order_details.quantity, order_details.price_per_unit, 
//                  order_details.total_price, food.food_name
//            FROM order_details
//           JOIN food ON order_details.food_id = food.food_id
          
//           WHERE order_details.order_id = ? AND EXISTS (SELECT 1 FROM orders WHERE orders.order_id = order_details.order_id)";
//  $stmt = $conn->prepare($query);
//     $stmt->bind_param("i", $orderId );
//     $stmt->execute();
//     $result = $stmt->get_result();

//     $orderDetails = [];
//     while ($row = $result->fetch_assoc()) {
//         $orderDetails[] = $row;
//     }
//     $stmt->close();

//     echo json_encode(["success" => true, "order_details" => $orderDetails]);
// } else {
//     echo json_encode(["success" => false, "message" => "Invalid request method."]);
// }





header('Content-Type: application/json');

// Include database connection file
include('config.php');
session_start();

// Get the request data
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['order_id'])) {
    $order_id = $data['order_id'];

    // Fetch order details
    $query = "SELECT od.*, f.food_name FROM order_details od JOIN food f ON od.food_id = f.food_id WHERE od.order_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $order_details = [];
    while ($row = $result->fetch_assoc()) {
        $order_details[] = $row;
    }

    if (!empty($order_details)) {
        echo json_encode(['success' => true, 'order_details' => $order_details]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No order details found.']);
    }

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
}

$conn->close();


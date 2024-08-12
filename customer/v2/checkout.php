<?php
session_start();
include('config.php');
header('Content-Type: application/json');

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$orderItems = $data['order_items'] ?? [];
$totalAmount = $data['total_amount'] ?? 0;

// Validate data
if (empty($orderItems) || $totalAmount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit();
}

// Calculate service fee (10% of total amount)
$serviceFee = $totalAmount * 0.10;

// Calculate delivery fee (20% of total amount)
$deliveryFee = $totalAmount * 0.20;

// Calculate total order amount
$totalOrderAmount = $totalAmount + $serviceFee + $deliveryFee;

// Example of processing payment and order insertion
// Ensure to add proper validation and error handling

// Process payment (pseudo-code)
// $paymentResult = processPayment($totalOrderAmount);

// if (!$paymentResult) {
//     echo json_encode(['success' => false, 'message' => 'Payment failed']);
//     exit();
// }

// Insert into orders table
// $stmt = $conn->prepare("INSERT INTO orders (customer_id, total_amount, service_fee, delivery_fee, total_order_amount, date_created) VALUES (?, ?, ?, ?, NOW())");
// $stmt->bind_param("iddd", $_SESSION['customer_id'], $totalAmount, $serviceFee, $deliveryFee, $totalOrderAmount);
// $stmt->execute();
// $orderId = $stmt->insert_id;
// $stmt->close();

// Insert order items
// foreach ($orderItems as $item) {
//     $stmt = $conn->prepare("INSERT INTO order_items (order_id, item_id, quantity, total_price) VALUES (?, ?, ?, ?)");
//     $stmt->bind_param("iiid", $orderId, $item['item_id'], $item['quantity'], $item['total_price']);
//     $stmt->execute();
//     $stmt->close();
// }

// Optionally, update stock or perform other actions

// Send success response with calculated values
echo json_encode([
    'success' => true,
    'message' => 'Order placed successfully',
    'total_amount' => $totalAmount,
    'service_fee' => $serviceFee,
    'delivery_fee' => $deliveryFee,
    'total_order_amount' => $totalOrderAmount
]);

$conn->close();


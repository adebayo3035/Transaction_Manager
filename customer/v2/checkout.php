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


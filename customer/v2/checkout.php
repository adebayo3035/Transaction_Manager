<?php
session_start();
include('config.php');
header('Content-Type: application/json');

// Log the start of the script
logActivity("Order placement script started.");

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
logActivity("Incoming request data: " . json_encode($data));

$orderItems = $data['order_items'] ?? [];
$totalAmount = $data['total_amount'] ?? 0;

// Validate data
if (empty($orderItems) || $totalAmount <= 0) {
    logActivity("Invalid data received: Order items are empty or total amount is invalid.");
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit();
}

logActivity("Order items and total amount validated successfully.");

// Calculate service fee (10% of total amount)
$serviceFee = $totalAmount * 0.10;
logActivity("Service fee calculated: $serviceFee");

// Calculate delivery fee (20% of total amount)
$deliveryFee = $totalAmount * 0.20;
logActivity("Delivery fee calculated: $deliveryFee");

// Calculate total order amount
$totalOrderAmount = $totalAmount + $serviceFee + $deliveryFee;
logActivity("Total order amount calculated: $totalOrderAmount");

// Send success response with calculated values
$response = [
    'success' => true,
    'message' => 'Order placed successfully',
    'total_amount' => $totalAmount,
    'service_fee' => $serviceFee,
    'delivery_fee' => $deliveryFee,
    'total_order_amount' => $totalOrderAmount
];

logActivity("Sending response: " . json_encode($response));
echo json_encode($response);

// Close the database connection
$conn->close();
logActivity("Database connection closed.");
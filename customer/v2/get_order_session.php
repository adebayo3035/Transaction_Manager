<?php
// get_order_session.php
session_start();
header('Content-Type: application/json');

// Check if session data exists
if (!isset($_SESSION['order_items']) || !isset($_SESSION['total_order']) || !isset($_SESSION['service_fee']) || !isset($_SESSION['delivery_fee'])) {
    echo json_encode(['success' => false, 'message' => 'No order data found in session.']);
    exit;
}

// Retrieve session data
$orderData = [
    'order_items' => $_SESSION['order_items'],
    'total_order' => $_SESSION['total_order'],
    'service_fee' => $_SESSION['service_fee'],
    'delivery_fee' => $_SESSION['delivery_fee']
];

echo json_encode(['success' => true, 'data' => $orderData]);
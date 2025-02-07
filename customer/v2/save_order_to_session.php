<?php
session_start();
header('Content-Type: application/json');

$customerId = $_SESSION["customer_id"] ?? null;
logActivity("Session started. Customer ID: " . ($customerId ?? 'Not set'));
checkSession($customerId);

// Retrieve data from the request
$data = json_decode(file_get_contents('php://input'), true);
logActivity("Received JSON input: " . json_encode($data));

if (isset($data['order_items']) && isset($data['total_amount']) && isset($data['service_fee']) && isset($data['delivery_fee'])) {
    $_SESSION['order_items'] = $data['order_items'];
    $_SESSION['total_amount'] = $data['total_amount'];
    $_SESSION['service_fee'] = $data['service_fee'];
    $_SESSION['delivery_fee'] = $data['delivery_fee'];
    
    logActivity("Order data stored in session: " . json_encode($_SESSION));
    echo json_encode(['success' => true]);
} else {
    logActivity("Invalid data received: " . json_encode($data));
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
}

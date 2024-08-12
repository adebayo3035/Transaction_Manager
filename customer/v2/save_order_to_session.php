<?php
session_start();
header('Content-Type: application/json');

// Retrieve data from the request
$data = json_decode(file_get_contents('php://input'), true);
if (isset($data['order_items']) && isset($data['total_amount']) && isset($data['service_fee']) && isset($data['delivery_fee'])) {
    $_SESSION['order_items'] = $data['order_items'];
    $_SESSION['total_amount'] = $data['total_amount'];
    $_SESSION['service_fee'] = $data['service_fee'];
    $_SESSION['delivery_fee'] = $data['delivery_fee'];
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
}


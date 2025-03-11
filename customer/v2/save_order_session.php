<?php
include 'config.php';
session_start();
header('Content-Type: application/json');

// Debugging: Log the received data
error_log("Received data: " . print_r($_POST, true));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Decode the JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Debugging: Log the decoded data
    error_log("Decoded data: " . print_r($data, true));

    // Validate the input data
    if (empty($data['order_items']) || !is_array($data['order_items'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid order items data.']);
        exit;
    }

    // Clear existing order-related session data
    unset($_SESSION['order_items']);
    unset($_SESSION['total_order']);
    unset($_SESSION['service_fee']);
    unset($_SESSION['delivery_fee']);

    // Store order data in the server-side session
    $_SESSION['order_items'] = $data['order_items'];
    $_SESSION['total_order'] = $data['total_order'];
    $_SESSION['service_fee'] = $data['service_fee'];
    $_SESSION['delivery_fee'] = $data['delivery_fee'];

    // Debugging: Log the session data
    error_log("Session data after saving: " . print_r($_SESSION, true));

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
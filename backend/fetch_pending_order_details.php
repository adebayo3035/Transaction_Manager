<?php

header('Content-Type: application/json');

// Include database connection file
include('config.php');
session_start();

// Get the request data
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['order_id'])) {
    $order_id = $data['order_id'];

    // Fetch order details
    $query = "
    SELECT od.*, f.food_name, o.is_credit 
    FROM order_details od
    JOIN food f ON od.food_id = f.food_id
    JOIN orders o ON od.order_id = o.order_id
    WHERE od.order_id = ?";

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


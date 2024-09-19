<?php
header('Content-Type: application/json');

// Include database connection file
include('config.php');
session_start();

// Get the request data
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['customer_id'])) {
    $customer_id = $data['customer_id'];

    // Fetch customer details along with group and unit
    $query = "
        SELECT 
            c.*, 
            g.group_name, 
            u.unit_name 
        FROM customers c
        LEFT JOIN groups g ON c.group_id = g.group_id
        LEFT JOIN unit u ON c.unit_id = u.unit_id
        WHERE c.customer_id = ?
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'customer_details' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No Record found for this Customer.']);
    }

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
}

$conn->close();

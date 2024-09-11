<?php

header('Content-Type: application/json');

// Include database connection file
include('config.php');
session_start();

// Get the request data
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['driver_id'])) {
    $driver_id = $data['driver_id'];

    // Fetch order details
    $query = "SELECT * from driver WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $driver_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'driver_details' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No Record found for this Driver.']);
    }

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
}

$conn->close();


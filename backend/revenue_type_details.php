<?php

header('Content-Type: application/json');

// Include database connection file
include('config.php');
session_start();
if (!isset($_SESSION['unique_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}
$loggedInUserRole = $_SESSION['role'];

// Get the request data
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['revenue_id'])) {
    $revenue_id = $data['revenue_id'];

    // Fetch order details
    $query = "SELECT * from revenue_types WHERE revenue_type_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $revenue_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'success' => true,
            'revenueType_details' => $row
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No Record found for this Revenue Type.']);
    }

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
}

$conn->close();


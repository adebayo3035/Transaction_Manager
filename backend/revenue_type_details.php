<?php

header('Content-Type: application/json');

// Include database connection file
include('config.php');
session_start();

// Check login session
if (!isset($_SESSION['unique_id'])) {
    logActivity("Access Denied: Not logged in.");
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}

$loggedInUserRole = $_SESSION['role'];
$loggedInUserId = $_SESSION['unique_id'];

// Get the request data
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['revenue_id'])) {
    $revenue_id = $data['revenue_id'];
    logActivity("Fetching Revenue Type Details for revenue_id: $revenue_id by user_id: $loggedInUserId");

    // Fetch order details
    $query = "SELECT * from revenue_types WHERE revenue_type_id = ?";
    $stmt = $conn->prepare($query);

    if (!$stmt) {
        logActivity("Database Error: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Internal server error.']);
        exit();
    }

    $stmt->bind_param("i", $revenue_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        logActivity("Revenue Type Found: ID $revenue_id fetched successfully by user_id $loggedInUserId");
        echo json_encode([
            'success' => true,
            'revenueType_details' => $row
        ]);
    } else {
        logActivity("Revenue Type Not Found: No record for ID $revenue_id requested by user_id $loggedInUserId");
        echo json_encode(['success' => false, 'message' => 'No Record found for this Revenue Type.']);
    }

    $stmt->close();
} else {
    logActivity("Invalid Request: revenue_id not set by user_id $loggedInUserId");
    echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
}

$conn->close();

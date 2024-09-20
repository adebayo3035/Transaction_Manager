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

if (isset($data['staff_id'])) {
    $staff_id = $data['staff_id'];

    // Fetch order details
    $query = "SELECT * from admin_tbl WHERE unique_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'success' => true,
            'staff_details' => $row,
            'logged_in_user_role' => $loggedInUserRole // Include logged-in user's role in the response
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No Record found for this Staff.']);
    }

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
}

$conn->close();


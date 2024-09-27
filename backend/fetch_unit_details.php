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

if (isset($data['unit_id'])) {
    $unit_id = $data['unit_id'];

    // Fetch group details along with the function name
    $query = "
        SELECT u.*, g.group_name 
        FROM unit u
        JOIN groups g ON u.group_id = g.group_id
        WHERE u.unit_id = ?
    "; 
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $unit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'success' => true,
            'unit_details' => $row,
            'logged_in_user_role' => $loggedInUserRole // Include logged-in user's role in the response
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No Record found for this Unit.']);
    }

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
}

$conn->close();

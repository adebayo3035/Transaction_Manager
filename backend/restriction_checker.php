<?php
// Start the session or get the user ID from the request data
session_start();
$user_id = $_SESSION['unique_id'] ?? null; // Assuming user ID is stored in session

// Get the request data
$data = json_decode(file_get_contents('php://input'), true);

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated.']);
    exit;
}

// Retrieve the restriction_id for the user
$query = "SELECT restriction_id FROM admin_tbl WHERE unique_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($restriction_id);
$stmt->fetch();
$stmt->close();

if ($restriction_id == 1) {
    echo json_encode(['success' => false, 'message' => 'There is a restriction on this staff account.']);
    exit;
}
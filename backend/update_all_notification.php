<?php
include 'config.php';
session_start();

if (!isset($_SESSION['unique_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}

$userId = $_SESSION['unique_id'];
$role = $_SESSION['role'] ?? '';

$query = '';
$params = [];
$paramTypes = '';

// Determine the query based on the user role
if ($role === 'Super Admin') {
    $query = "UPDATE admin_notifications SET super_admin_read = ? WHERE is_read = 'No'";
    $params = [$userId];
    $paramTypes = 'i';
} elseif ($role === 'Admin') {
    $query = "UPDATE admin_notifications SET is_read = 'Yes' WHERE user_id = ? AND is_read = ?";
    $params = [$userId, 'No'];
    $paramTypes = 'is';
} else {
    echo json_encode(["success" => false, "message" => "Invalid role."]);
    exit();
}

// Execute the query
if ($query) {
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param($paramTypes, ...$params);
        $success = $stmt->execute();
        $stmt->close();
        
        echo json_encode([
            "success" => $success,
            "message" => $success ? "All notifications have been marked as read." : "Failed to mark notifications as read."
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "Database error: Failed to prepare query."]);
    }
} else {
    echo json_encode(["success" => false, "message" => "No valid action to perform."]);
}
$conn->close();


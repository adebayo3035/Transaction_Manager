<?php
include 'config.php';
session_start();

if (!isset($_SESSION['unique_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}

if(($_SESSION['role']) == 'Super Admin'){
    echo json_encode(["success" => true, "message" => "You cannot mark notification as read."]);
    exit();
}

$userId = $_SESSION['unique_id'];
$input = json_decode(file_get_contents('php://input'), true);


// Update the notification's is_read column to 'Yes'
$status = 'No';
$query = "UPDATE admin_notifications SET is_read = 'Yes' WHERE user_id = ? AND is_read = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('is', $userId, $status);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "All Notifications has marked as read."]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to mark notification as read."]);
}

$stmt->close();
$conn->close();

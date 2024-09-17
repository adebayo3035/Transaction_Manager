<?php
include 'config.php';
session_start();

if (!isset($_SESSION['unique_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}

$userId = $_SESSION['unique_id'];
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['notification_id'])) {
    echo json_encode(["success" => false, "message" => "Notification ID is required."]);
    exit();
}

$notificationId = $input['notification_id'];

// Update the notification's is_read column to 'Yes'
$query = "UPDATE admin_notifications SET is_read = 'Yes' WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $notificationId);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Notification marked as read."]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to mark notification as read."]);
}

$stmt->close();
$conn->close();
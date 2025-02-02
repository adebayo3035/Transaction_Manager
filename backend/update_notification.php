<?php
include 'config.php';
session_start();

if (!isset($_SESSION['unique_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}

$userId = $_SESSION['unique_id'];
$role = $_SESSION['role'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['notification_id'])) {
    echo json_encode(["success" => false, "message" => "Notification ID is required."]);
    exit();
}

$notificationId = (int) $input['notification_id']; // Ensure it's an integer

// Define update query based on role
if ($role === 'Super Admin') {
    $query = "UPDATE admin_notifications SET super_admin_read = ? WHERE is_read = 'No' AND id = ?";
} elseif ($role === 'Admin') {
    $query = "UPDATE admin_notifications SET is_read = 'Yes' WHERE id = ?";
} else {
    echo json_encode(["success" => false, "message" => "Invalid role."]);
    exit();
}

// Prepare and execute the query
$stmt = $conn->prepare($query);
if ($role === 'Super Admin') {
    $stmt->bind_param('ii', $userId, $notificationId);
} else {
    $stmt->bind_param('i', $notificationId);
}

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Notification marked as read."]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to mark notification as read."]);
}

// Close resources
$stmt->close();
$conn->close();


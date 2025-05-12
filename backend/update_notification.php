<?php
include 'config.php';
session_start();

if (!isset($_SESSION['unique_id'])) {
    logActivity("Notification read failed: user not logged in.");
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}

$userId = (int)$_SESSION['unique_id'];
$role = $_SESSION['role'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['notification_id']) || !is_numeric($input['notification_id']) || (int)$input['notification_id'] <= 0) {
    logActivity("Invalid notification_id input from user $userId.");
    echo json_encode(["success" => false, "message" => "Valid Notification ID is required."]);
    exit();
}

$notificationId = (int)$input['notification_id'];
logActivity("User $userId ($role) attempting to mark notification $notificationId as read.");

if ($role === 'Super Admin') {
    $query = "UPDATE admin_notifications SET super_admin_read = ? WHERE is_read = 'No' AND id = ?";
} elseif ($role === 'Admin') {
    $query = "UPDATE admin_notifications SET is_read = 'Yes' WHERE id = ?";
} else {
    logActivity("User $userId has invalid role '$role' during notification read.");
    echo json_encode(["success" => false, "message" => "Invalid role."]);
    exit();
}

$stmt = $conn->prepare($query);
if (!$stmt) {
    logActivity("Database prepare error: " . $conn->error);
    echo json_encode(["success" => false, "message" => "Database error."]);
    exit();
}

if ($role === 'Super Admin') {
    $stmt->bind_param('ii', $userId, $notificationId);
} else {
    $stmt->bind_param('i', $notificationId);
}

if ($stmt->execute()) {
    logActivity("Notification $notificationId marked as read by user $userId ($role).");
    echo json_encode(["success" => true, "message" => "Notification marked as read."]);
} else {
    logActivity("Failed to update notification $notificationId by user $userId. Error: " . $stmt->error);
    echo json_encode(["success" => false, "message" => "Failed to mark notification as read."]);
}

$stmt->close();
$conn->close();

<?php
session_start();
include 'config.php'; 
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

$adminId = $_SESSION['unique_id'] ?? null;
if (!$adminId) {
    echo json_encode(['success' => false, 'message' => 'User Not Logged In.']);
    exit;
}

// Fetch notifications
$sql = "SELECT * FROM admin_notifications";
$result = $conn->query($sql);

$notifications = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
}

// Fetch total notifications count
$sqlCountNotification = "SELECT COUNT(*) AS total_notifications FROM admin_notifications";
$resultCountNotification = $conn->query($sqlCountNotification);
$totalNotifications = $resultCountNotification->fetch_assoc()['total_notifications'];

// Prepare final response
$response = [
    'success' => true,
    'notifications' => $notifications,
    'totalNotifications' => $totalNotifications
];

echo json_encode($response);

$conn->close();


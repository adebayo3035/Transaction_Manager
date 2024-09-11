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

if ($adminId) {
    // Fetch notifications for the specific user where is_read = 'No'
    $sql = "SELECT * FROM admin_notifications WHERE is_read = 'No' AND user_id = ? ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $adminId); // Bind the user_id dynamically
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
    }

    // Fetch the count of unread notifications for the specific user
    $sqlCountNotification = "SELECT COUNT(*) AS total_notifications FROM admin_notifications WHERE is_read = 'No' AND user_id = ?";
    $stmtCount = $conn->prepare($sqlCountNotification);
    $stmtCount->bind_param("i", $adminId); // Bind the user_id dynamically
    $stmtCount->execute();
    $resultCountNotification = $stmtCount->get_result();
    $totalNotifications = $resultCountNotification->fetch_assoc()['total_notifications'];

    // Prepare final response
    $response = [
        'success' => true,
        'notifications' => $notifications,
        'totalNotifications' => $totalNotifications
    ];

    echo json_encode($response);

    // Close the statement and connection
    $stmt->close();
    $stmtCount->close();
    $conn->close();
} else {
    // Handle case where user_id is not available
    $response = [
        'success' => false,
        'message' => 'User ID not provided'
    ];
    echo json_encode($response);
}



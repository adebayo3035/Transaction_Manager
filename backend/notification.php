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

// Pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1; // Default page is 1
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5; // Default limit is 5 notifications per page
$offset = ($page - 1) * $limit;

// Check if the logged-in user is a Super Admin
$sqlRole = "SELECT role FROM admin_tbl WHERE unique_id = ?";
$stmtRole = $conn->prepare($sqlRole);
$stmtRole->bind_param("i", $adminId);
$stmtRole->execute();
$resultRole = $stmtRole->get_result();
$userRole = $resultRole->fetch_assoc()['role'];
$stmtRole->close();

if ($adminId) {
    if ($userRole === 'Super Admin') {
        // Fetch unread notifications for Super Admins with pagination
        $sql = "SELECT * FROM admin_notifications WHERE is_read = 'No' ORDER BY created_at DESC LIMIT ?, ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $offset, $limit); // Bind offset and limit
    } else {
        // Fetch unread notifications for the specific Admin with pagination
        $sql = "SELECT * FROM admin_notifications WHERE is_read = 'No' AND user_id = ? ORDER BY created_at DESC LIMIT ?, ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $adminId, $offset, $limit); // Bind adminId, offset, and limit
    }

    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
    }

    // Fetch total unread notification count (for pagination)
    if ($userRole === 'Super Admin') {
        $sqlCountNotification = "SELECT COUNT(*) AS total_notifications FROM admin_notifications WHERE is_read = 'No'";
        $stmtCount = $conn->prepare($sqlCountNotification);
    } else {
        $sqlCountNotification = "SELECT COUNT(*) AS total_notifications FROM admin_notifications WHERE is_read = 'No' AND user_id = ?";
        $stmtCount = $conn->prepare($sqlCountNotification);
        $stmtCount->bind_param("i", $adminId); // Bind the user_id dynamically for regular admin
    }

    $stmtCount->execute();
    $resultCountNotification = $stmtCount->get_result();
    $totalNotifications = $resultCountNotification->fetch_assoc()['total_notifications'];

    // Calculate total pages
    $totalPages = ceil($totalNotifications / $limit);

    // Prepare final response
    $response = [
        'success' => true,
        'notifications' => $notifications,
        'totalNotifications' => $totalNotifications,
        'totalPages' => $totalPages,
        'currentPage' => $page,
        'role' => $userRole
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

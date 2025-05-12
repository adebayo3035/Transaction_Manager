<?php
header('Content-Type: application/json');
include 'config.php';
session_start();

// Constants for notification statuses
const NOTIFICATION_READ = 'Yes';
const NOTIFICATION_UNREAD = 'No';

try {
    logActivity("Notification mark-as-read process initiated");

    // Validate session
    if (empty($_SESSION['unique_id'])) {
        logActivity("Unauthorized attempt - User not logged in");
        throw new Exception('Not logged in.', 401);
    }

    $userId = $_SESSION['unique_id'];
    $role = $_SESSION['role'] ?? '';
    logActivity("Processing request for user ID: $userId with role: $role");

    // Validate role and prepare appropriate query
    switch ($role) {
        case 'Super Admin':
            logActivity("Super Admin workflow initiated");
            $query = "UPDATE admin_notifications SET super_admin_read = ? WHERE is_read = ?";
            $params = [$userId, NOTIFICATION_UNREAD];
            $paramTypes = 'is';
            break;
            
        case 'Admin':
            logActivity("Admin workflow initiated");
            $query = "UPDATE admin_notifications SET is_read = ? WHERE user_id = ? AND is_read = ?";
            $params = [NOTIFICATION_READ, $userId, NOTIFICATION_UNREAD];
            $paramTypes = 'sis';
            break;
            
        default:
            logActivity("Invalid role detected: $role");
            throw new Exception('Invalid role.', 403);
    }

    // Execute the update
    logActivity("Preparing query: $query");
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        logActivity("Database error - Failed to prepare query");
        throw new Exception('Database error: Failed to prepare query.', 500);
    }

    logActivity("Binding parameters: " . json_encode($params));
    $stmt->bind_param($paramTypes, ...$params);
    
    logActivity("Executing query");
    $success = $stmt->execute();
    $affectedRows = $conn->affected_rows;
    $stmt->close();

    if (!$success) {
        logActivity("Query execution failed");
        throw new Exception('Failed to mark notifications as read.', 500);
    }

    logActivity("Successfully marked $affectedRows notifications as read");
    $response = [
        'success' => true,
        'message' => 'Notifications marked as read successfully.',
        'affected_rows' => $affectedRows
    ];

} catch (Exception $e) {
    logActivity("Error occurred: " . $e->getMessage());
    http_response_code($e->getCode() ?: 500);
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
} finally {
    if (isset($conn)) {
        $conn->close();
    }
    logActivity("Process completed with response: " . json_encode($response));
    echo json_encode($response);
}
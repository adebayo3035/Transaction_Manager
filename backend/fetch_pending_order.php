<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

// Initialize variables for cleanup
$stmt = null;

// Initialize logging
logActivity("Pending orders fetch process started");

try {
    // Check authentication
    if (!isset($_SESSION['unique_id'])) {
        $errorMsg = "Unauthenticated access attempt to pending orders";
        logActivity($errorMsg);
        echo json_encode(["success" => false, "message" => "Not logged in."]);
        exit();
    }

    $adminId = $_SESSION['unique_id'];
    $userRole = $_SESSION['role'] ?? null;
    logActivity("Request initiated by admin ID: $adminId (Role: $userRole)");

    try {
        // Start transaction for consistent data view
        $conn->begin_transaction();
        logActivity("Database transaction started");

        // Build query based on role
        if ($userRole == "Admin") {
            $query = "SELECT o.order_id, o.order_date, o.total_amount, o.status, 
                     a.firstname AS assigned_admin_firstname, a.lastname AS assigned_admin_lastname
                     FROM orders o
                     INNER JOIN admin_tbl a ON o.assigned_to = a.unique_id
                     WHERE o.status = 'Pending' AND o.assigned_to = ?
                     ORDER BY o.order_date DESC";
            
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Prepare failed for admin query: " . $conn->error);
            }
            $stmt->bind_param("i", $adminId);
        } 
        elseif ($userRole == "Super Admin") {
            $query = "SELECT o.order_id, o.order_date, o.total_amount, o.status, 
                     o.assigned_to, a.firstname AS assigned_admin_firstname, 
                     a.lastname AS assigned_admin_lastname
                     FROM orders o
                     LEFT JOIN admin_tbl a ON o.assigned_to = a.unique_id
                     WHERE o.status = 'Pending'
                     ORDER BY o.order_date DESC";
            
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Prepare failed for super admin query: " . $conn->error);
            }
        } 
        else {
            throw new Exception("Unauthorized role: " . $userRole);
        }

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $result = $stmt->get_result();
        $orders = [];

        while ($row = $result->fetch_assoc()) {
            // Format numeric values
            $row['total_amount'] = number_format($row['total_amount'], 2);
            $orders[] = $row;
        }

        $conn->commit();
        logActivity("Successfully retrieved " . count($orders) . " pending orders");

        // Prepare response
        $response = [
            'success' => true,
            'orders' => $orders,
            'count' => count($orders),
            'requested_by' => $adminId,
            'user_role' => $userRole,
            'timestamp' => date('c')
        ];

        echo json_encode($response);
        logActivity("Pending orders fetch completed successfully");

    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
        }
        throw $e;
    }
} catch (Exception $e) {
    $errorMsg = "Error fetching pending orders: " . $e->getMessage();
    logActivity($errorMsg);
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to fetch pending orders',
        'error' => $e->getMessage()
    ]);
} finally {
    // Clean up resources
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
    logActivity("Pending orders fetch process completed");
}
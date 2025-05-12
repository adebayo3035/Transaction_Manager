<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

// Initialize logging
logActivity("Unit listing fetch process started");

try {
    // Check authentication
    if (!isset($_SESSION['unique_id'])) {
        $errorMsg = "Unauthenticated access attempt to unit listing";
        logActivity($errorMsg);
        echo json_encode(["success" => false, "message" => "Not logged in."]);
        exit();
    }

    $userId = $_SESSION['unique_id'];
    $userRole = $_SESSION['role'] ?? null;
    logActivity("Request initiated by user ID: $userId (Role: $userRole)");

    try {
        // Start transaction for consistent data view
        $conn->begin_transaction();
        logActivity("Database transaction started");

        // Fetch all units with their group information
        $query = "SELECT u.unit_id, u.unit_name, u.group_id, g.group_name 
                 FROM unit u
                 JOIN groups g ON u.group_id = g.group_id
                 ORDER BY g.group_name ASC, u.unit_name ASC";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed for units query: " . $conn->error);
        }

        if (!$stmt->execute()) {
            throw new Exception("Execute failed for units query: " . $stmt->error);
        }

        $result = $stmt->get_result();
        $units = [];

        while ($row = $result->fetch_assoc()) {
            $units[] = $row;
        }

        // $stmt->close();
        $conn->commit();

        logActivity("Successfully retrieved " . count($units) . " units");

        // Prepare response
        $response = [
            'success' => true,
            'units' => $units,
            'count' => count($units),
            'requested_by' => $userId,
            'user_role' => $userRole,
            'timestamp' => date('c')
        ];

        echo json_encode($response);
        logActivity("Unit listing fetch completed successfully");

    } catch (Exception $e) {
        $conn->rollback();
        throw $e; // Re-throw to outer catch block
    }
} catch (Exception $e) {
    $errorMsg = "Error fetching unit listing: " . $e->getMessage();
    logActivity($errorMsg);
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to fetch units',
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    $conn->close();
    logActivity("Unit listing fetch process completed");
}
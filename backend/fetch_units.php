<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

// Initialize logging
logActivity("Units by group fetch process started");

// Declare variables that might be used in finally block
$stmt = null;
$groupCheck = null;


try {
    // Check authentication
    if (!isset($_SESSION['unique_id'])) {
        $errorMsg = "Unauthenticated access attempt to units by group";
        logActivity($errorMsg);
        echo json_encode(["success" => false, "message" => "Not logged in."]);
        exit();
    }

    $userId = $_SESSION['unique_id'];
    $userRole = $_SESSION['role'] ?? null;
    logActivity("Request initiated by user ID: $userId (Role: $userRole)");

    // Get and validate input
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON input: " . json_last_error_msg());
    }

    if (!isset($input['group_id'])) {
        $errorMsg = "Missing group_id in request data";
        logActivity($errorMsg);
        echo json_encode(['success' => false, 'message' => 'Group ID is required']);
        exit();
    }

    $group_id = filter_var($input['group_id'], FILTER_VALIDATE_INT);
    if ($group_id === false || $group_id < 1) {
        $errorMsg = "Invalid group_id format: " . $input['group_id'];
        logActivity($errorMsg);
        echo json_encode(['success' => false, 'message' => 'Invalid Group ID format']);
        exit();
    }

    logActivity("Fetching units for group ID: " . $group_id);

    try {
        // Start transaction for consistent data view
        $conn->begin_transaction();
        logActivity("Database transaction started");

        // Check if group exists
        $groupCheck = $conn->prepare("SELECT group_id FROM groups WHERE group_id = ?");
        $groupCheck->bind_param("i", $group_id);
        $groupCheck->execute();
        
        if ($groupCheck->get_result()->num_rows === 0) {
            throw new Exception("Group not found with ID: " . $group_id);
        }

        // Fetch units for the group
        $query = "SELECT unit_id, unit_name FROM unit WHERE group_id = ? ORDER BY unit_name ASC";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Prepare failed for units query: " . $conn->error);
        }

        $stmt->bind_param("i", $group_id);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed for units query: " . $stmt->error);
        }

        $result = $stmt->get_result();
        $units = [];

        while ($row = $result->fetch_assoc()) {
            $units[] = $row;
        }

        $conn->commit();
        logActivity("Successfully retrieved " . count($units) . " units for group ID: " . $group_id);

        // Prepare response
        $response = [
            'success' => true,
            'units' => $units,
            'group_id' => $group_id,
            'count' => count($units),
            'requested_by' => $userId,
            'user_role' => $userRole,
            'timestamp' => date('c')
        ];

        echo json_encode($response);
        logActivity("Units by group fetch completed successfully");

    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
        }
        throw $e;
    }
} catch (Exception $e) {
    $errorMsg = "Error fetching units by group: " . $e->getMessage();
    logActivity($errorMsg);
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to fetch units',
        'error' => $e->getMessage(),
        'group_id' => $group_id ?? null
    ]);
} finally {
    // Close statements if they exist
    if (isset($groupCheck) && $groupCheck instanceof mysqli_stmt) {
        $groupCheck->close();
    }
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
    logActivity("Units by group fetch process completed");
}
<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

// Initialize variables for cleanup
$stmt = null;
// $conn = null;

// Initialize logging
logActivity("Group details fetch process started");

try {
    // Check authentication
    if (!isset($_SESSION['unique_id'])) {
        $errorMsg = "Unauthenticated access attempt to group details";
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
        $errorMsg = "Invalid group_id format: " . ($input['group_id'] ?? 'null');
        logActivity($errorMsg);
        echo json_encode(['success' => false, 'message' => 'Invalid Group ID format']);
        exit();
    }

    logActivity("Processing group details for ID: " . $group_id);

    try {
        // Start transaction for consistent data view
        $conn->begin_transaction();
        logActivity("Database transaction started");

        // Fetch group details
        $query = "SELECT * FROM groups WHERE group_id = ?";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Prepare failed for group details: " . $conn->error);
        }

        $stmt->bind_param("i", $group_id);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed for group details: " . $stmt->error);
        }

        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            logActivity("No group found with ID: " . $group_id);
            echo json_encode([
                'success' => false, 
                'message' => 'Group not found',
                'group_id' => $group_id
            ]);
            exit();
        }

        $groupDetails = $result->fetch_assoc();
        $conn->commit();

        logActivity("Successfully retrieved group details for ID: " . $group_id);

        // Prepare response
        $response = [
            'success' => true,
            'group_details' => $groupDetails,
            'logged_in_user_role' => $userRole,
            'requested_by' => $userId,
            'timestamp' => date('c')
        ];

        echo json_encode($response);
        logActivity("Group details fetch completed successfully");

    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
        }
        throw $e;
    }
} catch (Exception $e) {
    $errorMsg = "Error fetching group details: " . $e->getMessage();
    logActivity($errorMsg);
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to fetch group details',
        'error' => $e->getMessage(),
        'group_id' => $group_id ?? null
    ]);
} finally {
    // Clean up resources
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
    logActivity("Group details fetch process completed");
}
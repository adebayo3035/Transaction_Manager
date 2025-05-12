<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

// Initialize logging
logActivity("Unit details fetch process started");

try {
    // Check authentication
    if (!isset($_SESSION['unique_id'])) {
        $errorMsg = "Unauthenticated access attempt to unit details";
        logActivity($errorMsg);
        echo json_encode(["success" => false, "message" => "Not logged in."]);
        exit();
    }

    $adminId = $_SESSION['unique_id'];
    $loggedInUserRole = $_SESSION['role'] ?? null;
    logActivity("Request initiated by user ID: $adminId (Role: $loggedInUserRole)");

    // Get and validate input
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON input: " . json_last_error_msg());
    }

    if (!isset($input['unit_id'])) {
        $errorMsg = "Missing unit_id in request data";
        logActivity($errorMsg);
        echo json_encode(['success' => false, 'message' => 'Unit ID is required']);
        exit();
    }

    $unit_id = $input['unit_id'];
    if (!is_numeric($unit_id)) {
        $errorMsg = "Invalid unit_id format: " . $unit_id;
        logActivity($errorMsg);
        echo json_encode(['success' => false, 'message' => 'Invalid Unit ID format']);
        exit();
    }

    logActivity("Processing unit details for ID: " . $unit_id);

    try {
        // Start transaction for consistent data view
        $conn->begin_transaction();
        logActivity("Database transaction started");

        // Fetch unit details with group information
        $query = "SELECT u.*, g.group_name 
                 FROM unit u
                 JOIN groups g ON u.group_id = g.group_id
                 WHERE u.unit_id = ?";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed for unit details: " . $conn->error);
        }

        $stmt->bind_param("i", $unit_id);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed for unit details: " . $stmt->error);
        }

        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            logActivity("No unit found with ID: " . $unit_id);
            echo json_encode([
                'success' => false, 
                'message' => 'Unit not found',
                'unit_id' => $unit_id
            ]);
            exit();
        }

        $unitDetails = $result->fetch_assoc();
        // $stmt->close();
        $conn->commit();

        logActivity("Successfully retrieved unit details for ID: " . $unit_id);

        // Prepare response
        $response = [
            'success' => true,
            'unit_details' => $unitDetails,
            'logged_in_user_role' => $loggedInUserRole,
            'requested_by' => $adminId,
            'timestamp' => date('c')
        ];

        echo json_encode($response);
        logActivity("Unit details fetch completed successfully");

    } catch (Exception $e) {
        $conn->rollback();
        throw $e; // Re-throw to outer catch block
    }
} catch (Exception $e) {
    $errorMsg = "Error fetching unit details: " . $e->getMessage();
    logActivity($errorMsg);
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to fetch unit details',
        'error' => $e->getMessage(),
        'unit_id' => $unit_id ?? null
    ]);
} finally {
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    $conn->close();
    logActivity("Unit details fetch process completed");
}
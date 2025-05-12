<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

// Initialize logging
logActivity("Revenue types fetch process started");

try {
    // Check authentication (if needed)
    if (!isset($_SESSION['unique_id'])) {
        $errorMsg = "Unauthenticated access attempt to revenue types";
        logActivity($errorMsg);
        echo json_encode(["success" => false, "message" => "Not logged in."]);
        exit();
    }

    $adminId = $_SESSION['unique_id'];
    logActivity("Revenue types request initiated by admin ID: " . $adminId);

    try {
        // Prepare and execute query
        $query = "SELECT * FROM revenue_types ORDER BY revenue_type_name ASC";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Prepare failed for revenue types: " . $conn->error);
        }

        if (!$stmt->execute()) {
            throw new Exception("Execute failed for revenue types: " . $stmt->error);
        }

        $result = $stmt->get_result();
        $revenueTypes = [];

        while ($row = $result->fetch_assoc()) {
            $revenueTypes[] = $row;
        }

        // $stmt->close();

        logActivity("Retrieved " . count($revenueTypes) . " revenue types");

        // Prepare response
        $response = [
            'success' => true,
            'revenueTypes' => $revenueTypes,
            'count' => count($revenueTypes),
            'requested_by' => $adminId,
            'timestamp' => date('c')
        ];

        echo json_encode($response);
        logActivity("Revenue types fetch completed successfully");

    } catch (Exception $e) {
        throw $e; // Re-throw to outer catch block
    }
} catch (Exception $e) {
    $errorMsg = "Error fetching revenue types: " . $e->getMessage();
    logActivity($errorMsg);
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to fetch revenue types',
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    $conn->close();
    logActivity("Revenue types fetch process completed");
}
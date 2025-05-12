<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

// Initialize variables for cleanup
$stmt = null;
// $conn = null;

// Initialize logging
logActivity("Food listing fetch process started");

try {
    // Check authentication
    if (!isset($_SESSION['unique_id'])) {
        $errorMsg = "Unauthenticated access attempt to food listing";
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

        // Fetch all food items with optional filtering/sorting
        $query = "SELECT * FROM food ORDER BY food_name ASC";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Prepare failed for food listing: " . $conn->error);
        }

        if (!$stmt->execute()) {
            throw new Exception("Execute failed for food listing: " . $stmt->error);
        }

        $result = $stmt->get_result();
        $foods = [];

        while ($row = $result->fetch_assoc()) {
            // Format numeric fields
            if (isset($row['price'])) {
                $row['price_formatted'] = number_format($row['price'], 2);
            }
            $foods[] = $row;
        }

        $conn->commit();
        logActivity("Successfully retrieved " . count($foods) . " food items");

        // Prepare response
        $response = [
            'success' => true,
            'foods' => $foods,
            'count' => count($foods),
            'requested_by' => $userId,
            'user_role' => $userRole,
            'timestamp' => date('c')
        ];

        echo json_encode($response);
        logActivity("Food listing fetch completed successfully");

    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
        }
        throw $e;
    }
} catch (Exception $e) {
    $errorMsg = "Error fetching food listing: " . $e->getMessage();
    logActivity($errorMsg);
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to fetch food listing',
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
    logActivity("Food listing fetch process completed");
}
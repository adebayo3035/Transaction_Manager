<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

// Initialize variables for cleanup
$stmt = null;
// $conn = null;

// Initialize logging
logActivity("Food details fetch process started");

try {
    // Check authentication
    if (!isset($_SESSION['unique_id'])) {
        $errorMsg = "Unauthenticated access attempt to food details";
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

    if (!isset($input['food_id'])) {
        $errorMsg = "Missing food_id in request data";
        logActivity($errorMsg);
        echo json_encode(['success' => false, 'message' => 'Food ID is required']);
        exit();
    }

    $food_id = filter_var($input['food_id'], FILTER_VALIDATE_INT);
    if ($food_id === false || $food_id < 1) {
        $errorMsg = "Invalid food_id format: " . ($input['food_id'] ?? 'null');
        logActivity($errorMsg);
        echo json_encode(['success' => false, 'message' => 'Invalid Food ID format']);
        exit();
    }

    logActivity("Processing food details for ID: " . $food_id);

    try {
        // Start transaction for consistent data view
        $conn->begin_transaction();
        logActivity("Database transaction started");

        // Fetch food details
        $query = "SELECT * FROM food WHERE food_id = ?";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Prepare failed for food details: " . $conn->error);
        }

        $stmt->bind_param("i", $food_id);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed for food details: " . $stmt->error);
        }

        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            logActivity("No food item found with ID: " . $food_id);
            echo json_encode([
                'success' => false, 
                'message' => 'Food item not found',
                'food_id' => $food_id
            ]);
            exit();
        }

        $foodDetails = $result->fetch_assoc();
        $conn->commit();

        // Format numeric fields if needed
        if (isset($foodDetails['price'])) {
            $foodDetails['price_formatted'] = number_format($foodDetails['price'], 2);
        }

        logActivity("Successfully retrieved food details for ID: " . $food_id);

        // Prepare response
        $response = [
            'success' => true,
            'food_details' => $foodDetails,
            'logged_in_user_role' => $userRole,
            'requested_by' => $userId,
            'timestamp' => date('c')
        ];

        echo json_encode($response);
        logActivity("Food details fetch completed successfully");

    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
        }
        throw $e;
    }
} catch (Exception $e) {
    $errorMsg = "Error fetching food details: " . $e->getMessage();
    logActivity($errorMsg);
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to fetch food details',
        'error' => $e->getMessage(),
        'food_id' => $food_id ?? null
    ]);
} finally {
    // Clean up resources
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
    logActivity("Food details fetch process completed");
}
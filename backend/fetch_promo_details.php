<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

// Initialize variables for cleanup
$stmt = null;

// Initialize logging
logActivity("Promo details fetch process started");

try {
    // Check authentication
    if (!isset($_SESSION['unique_id'])) {
        $errorMsg = "Unauthenticated access attempt to promo details";
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

    if (!isset($input['promo_id'])) {
        $errorMsg = "Missing promo_id in request data";
        logActivity($errorMsg);
        echo json_encode(['success' => false, 'message' => 'Promo ID is required']);
        exit();
    }

    $promo_id = filter_var($input['promo_id'], FILTER_VALIDATE_INT);
    if ($promo_id === false || $promo_id < 1) {
        $errorMsg = "Invalid promo_id format: " . ($input['promo_id'] ?? 'null');
        logActivity($errorMsg);
        echo json_encode(['success' => false, 'message' => 'Invalid Promo ID format']);
        exit();
    }

    logActivity("Processing promo details for ID: " . $promo_id);

    try {
        // Start transaction for consistent data view
        $conn->begin_transaction();
        logActivity("Database transaction started");

        // Fetch promo details
        $query = "SELECT * FROM promo WHERE promo_id = ?";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Prepare failed for promo details: " . $conn->error);
        }

        $stmt->bind_param("i", $promo_id);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed for promo details: " . $stmt->error);
        }

        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            logActivity("No promo found with ID: " . $promo_id);
            echo json_encode([
                'success' => false, 
                'message' => 'Promo not found',
                'promo_id' => $promo_id
            ]);
            exit();
        }

        $promoDetails = $result->fetch_assoc();
        $conn->commit();

        // Format dates if they exist
        if (isset($promoDetails['start_date'])) {
            $promoDetails['start_date_formatted'] = date('M d, Y', strtotime($promoDetails['start_date']));
        }
        if (isset($promoDetails['end_date'])) {
            $promoDetails['end_date_formatted'] = date('M d, Y', strtotime($promoDetails['end_date']));
        }

        logActivity("Successfully retrieved promo details for ID: " . $promo_id);

        // Prepare response
        $response = [
            'success' => true,
            'promo_details' => $promoDetails,
            'logged_in_user_role' => $userRole,
            'requested_by' => $userId,
            'timestamp' => date('c')
        ];

        echo json_encode($response);
        logActivity("Promo details fetch completed successfully");

    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
        }
        throw $e;
    }
} catch (Exception $e) {
    $errorMsg = "Error fetching promo details: " . $e->getMessage();
    logActivity($errorMsg);
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to fetch promo details',
        'error' => $e->getMessage(),
        'promo_id' => $promo_id ?? null
    ]);
} finally {
    // Clean up resources
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
    logActivity("Promo details fetch process completed");
}
<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

// Initialize variables for cleanup
$stmt = null;


// Initialize logging
logActivity("Driver details fetch process started");

try {
    // Check authentication
    if (!isset($_SESSION['unique_id'])) {
        $errorMsg = "Unauthenticated access attempt to driver details";
        logActivity($errorMsg);
        echo json_encode(["success" => false, "message" => "Not logged in."]);
        exit();
    }

    $adminId = $_SESSION['unique_id'];
    $userRole = $_SESSION['role'] ?? null;
    logActivity("Request initiated by user ID: $adminId (Role: $userRole)");

    // Get and validate input
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON input: " . json_last_error_msg());
    }

    if (!isset($input['driver_id'])) {
        $errorMsg = "Missing driver_id in request data";
        logActivity($errorMsg);
        echo json_encode(['success' => false, 'message' => 'Driver ID is required']);
        exit();
    }

    $driver_id = filter_var($input['driver_id'], FILTER_VALIDATE_INT);
    if ($driver_id === false || $driver_id < 1) {
        $errorMsg = "Invalid driver_id format: " . ($input['driver_id'] ?? 'null');
        logActivity($errorMsg);
        echo json_encode(['success' => false, 'message' => 'Invalid Driver ID format']);
        exit();
    }

    logActivity("Processing driver details for ID: " . $driver_id);

    try {
        // Start transaction for consistent data view
        $conn->begin_transaction();
        logActivity("Database transaction started");

        // Fetch driver details
        $query = "SELECT * FROM driver WHERE id = ?";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Prepare failed for driver details: " . $conn->error);
        }

        $stmt->bind_param("i", $driver_id);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed for driver details: " . $stmt->error);
        }

        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            logActivity("No driver found with ID: " . $driver_id);
            echo json_encode([
                'success' => false, 
                'message' => 'Driver not found',
                'driver_id' => $driver_id
            ]);
            exit();
        }

        $driverDetails = $result->fetch_assoc();
        $conn->commit();

        // Mask sensitive information if needed
        if (isset($driverDetails['license_number'])) {
            $driverDetails['license_masked'] = maskString($driverDetails['license_number']);
            $driverDetails['masked_question'] = maskString($driverDetails['secret_question']);
        }

        logActivity("Successfully retrieved driver details for ID: " . $driver_id);

        // Prepare response
        $response = [
            'success' => true,
            'driver_details' => $driverDetails,
            'requested_by' => $adminId,
            'user_role' => $userRole,
            'timestamp' => date('c')
        ];

        echo json_encode($response);
        logActivity("Driver details fetch completed successfully");

    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
        }
        throw $e;
    }
} catch (Exception $e) {
    $errorMsg = "Error fetching driver details: " . $e->getMessage();
    logActivity($errorMsg);
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to fetch driver details',
        'error' => $e->getMessage(),
        'driver_id' => $driver_id ?? null
    ]);
} finally {
    // Clean up resources
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
    logActivity("Driver details fetch process completed");
}

// Helper function to mask sensitive data (example)
function maskString($string, $visibleChars = 4) {
    $length = strlen($string);
    if ($length <= $visibleChars * 2) {
        return substr($string, 0, $visibleChars) . str_repeat('*', $length - $visibleChars);
    }
    return substr($string, 0, $visibleChars) . str_repeat('*', $length - ($visibleChars * 2)) . substr($string, -$visibleChars);
}
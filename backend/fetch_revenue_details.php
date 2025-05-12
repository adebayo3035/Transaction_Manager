<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

// Initialize logging
logActivity("Revenue details fetch process started");

try {
    // Check authentication
    if (!isset($_SESSION['unique_id'])) {
        $errorMsg = "Unauthenticated access attempt";
        logActivity($errorMsg);
        echo json_encode(["success" => false, "message" => "Not logged in."]);
        exit();
    }

    $adminId = $_SESSION['unique_id'];
    logActivity("Request initiated by admin ID: " . $adminId);

    // Get and validate input
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON input: " . json_last_error_msg());
    }

    $revenue_id = $input['revenueID'] ?? null;
    if (!$revenue_id) {
        $errorMsg = "Missing revenue ID in request";
        logActivity($errorMsg);
        echo json_encode(['success' => false, 'message' => 'Revenue ID is required']);
        exit();
    }

    logActivity("Processing revenue details for ID: " . $revenue_id);

    // Start transaction for consistent data view
    $conn->begin_transaction();
    logActivity("Transaction started for revenue details fetch");

    try {
        // Fetch revenue details with customer and type information
        $stmt = $conn->prepare("SELECT  c.firstname, c.lastname, c.email, r.*, rt.revenue_type_name, rt.revenue_type_description, o.delivery_status
          FROM revenue r
          LEFT JOIN customers c ON r.customer_id = c.customer_id
          LEFT JOIN revenue_types rt ON r.revenue_type_id = rt.revenue_type_id 
          LEFT JOIN orders o ON r.order_id = o.order_id
          WHERE r.order_id = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed for revenue details: " . $conn->error);
        }

        $stmt->bind_param("s", $revenue_id);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed for revenue details: " . $stmt->error);
        }

        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            throw new Exception("Revenue record not found with ID: " . $revenue_id);
        }

        $revenueDetails = $result->fetch_assoc();
        // $stmt->close();

        logActivity("Revenue details retrieved for ID: " . $revenue_id . 
                   ", Type: " . $revenueDetails['revenue_type_name'] . 
                   ", Amount: " . $revenueDetails['total_amount']);

        $conn->commit();

        // Prepare response
        $response = [
            'success' => true,
            'revenue_details' => $revenueDetails,
            'requested_by' => $adminId,
            'timestamp' => date('c')
        ];

        echo json_encode($response);
        logActivity("Revenue details fetch completed successfully");

    } catch (Exception $e) {
        $conn->rollback();
        throw $e; // Re-throw to outer catch block
    }
} catch (Exception $e) {
    $errorMsg = "Error fetching revenue details: " . $e->getMessage();
    logActivity($errorMsg);
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to fetch revenue details',
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    $conn->close();
    logActivity("Revenue details fetch process completed");
}
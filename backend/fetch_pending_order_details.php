<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

// Initialize variables for cleanup
$stmt = null;

// Initialize logging
logActivity("Order details fetch process started");

try {
    // Check authentication
    if (!isset($_SESSION['unique_id'])) {
        $errorMsg = "Unauthenticated access attempt to order details";
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

    if (!isset($input['order_id'])) {
        $errorMsg = "Missing order_id in request data";
        logActivity($errorMsg);
        echo json_encode(['success' => false, 'message' => 'Order ID is required']);
        exit();
    }

    $order_id = filter_var($input['order_id'], FILTER_VALIDATE_INT);
    if ($order_id === false || $order_id < 1) {
        $errorMsg = "Invalid order_id format: " . ($input['order_id'] ?? 'null');
        logActivity($errorMsg);
        echo json_encode(['success' => false, 'message' => 'Invalid Order ID format']);
        exit();
    }

    logActivity("Processing order details for ID: " . $order_id);

    try {
        // Start transaction for consistent data view
        $conn->begin_transaction();
        logActivity("Database transaction started");

        // Fetch order details with food information
        $query = "SELECT od.*, f.food_name, o.is_credit 
                 FROM order_details od
                 JOIN food f ON od.food_id = f.food_id
                 JOIN orders o ON od.order_id = o.order_id
                 WHERE od.order_id = ?";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed for order details: " . $conn->error);
        }

        $stmt->bind_param("i", $order_id);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed for order details: " . $stmt->error);
        }

        $result = $stmt->get_result();
        $order_details = [];

        while ($row = $result->fetch_assoc()) {
            // Format numeric values
            $row['price_per_unit'] = number_format($row['price_per_unit'], 2);
            $row['total_price'] = number_format($row['total_price'], 2);
            $order_details[] = $row;
        }

        if (empty($order_details)) {
            logActivity("No order details found for ID: " . $order_id);
            echo json_encode([
                'success' => false, 
                'message' => 'No order details found',
                'order_id' => $order_id
            ]);
            exit();
        }

        $conn->commit();
        logActivity("Successfully retrieved " . count($order_details) . " order items");

        // Prepare response
        $response = [
            'success' => true,
            'order_details' => $order_details,
            'count' => count($order_details),
            'requested_by' => $userId,
            'user_role' => $userRole,
            'timestamp' => date('c')
        ];

        echo json_encode($response);
        logActivity("Order details fetch completed successfully");

    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
        }
        throw $e;
    }
} catch (Exception $e) {
    $errorMsg = "Error fetching order details: " . $e->getMessage();
    logActivity($errorMsg);
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to fetch order details',
        'error' => $e->getMessage(),
        'order_id' => $order_id ?? null
    ]);
} finally {
    // Clean up resources
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
    logActivity("Order details fetch process completed");
}
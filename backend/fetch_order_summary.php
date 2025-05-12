<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

// Initialize variables for cleanup
$stmt = null;
$countStmt = null;

// Initialize logging
logActivity("Order listing fetch process started");

try {
    // Check authentication
    if (!isset($_SESSION['unique_id'])) {
        $errorMsg = "Unauthenticated access attempt to order listing";
        logActivity($errorMsg);
        echo json_encode(["success" => false, "message" => "Not logged in."]);
        exit();
    }

    $userId = $_SESSION['unique_id'];
    $userRole = $_SESSION['role'] ?? null;
    logActivity("Request initiated by user ID: $userId (Role: $userRole)");

    // Validate and sanitize pagination parameters
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
    
    if ($page < 1 || $limit < 1 || $limit > 100) {
        $errorMsg = "Invalid pagination parameters - Page: $page, Limit: $limit";
        logActivity($errorMsg);
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid pagination parameters. Page must be ≥ 1 and limit between 1-100.'
        ]);
        exit();
    }

    $offset = ($page - 1) * $limit;
    logActivity("Fetching orders - Page: $page, Limit: $limit, Offset: $offset");

    try {
        // Start transaction for consistent data view
        $conn->begin_transaction();
        logActivity("Database transaction started");

        // Build queries based on role
        $baseQuery = "SELECT order_id, order_date, customer_id, total_amount, delivery_status FROM orders";
        $countQuery = "SELECT COUNT(*) as total FROM orders";
        $condition = "";
        $params = [];

        if ($userRole == "Admin") {
            $condition = " WHERE assigned_to = ?";
            $params[] = $userId;
        } elseif ($userRole != "Super Admin") {
            throw new Exception("Unauthorized role: " . $userRole);
        }

        // Execute count query
        $countQuery .= $condition;
        $countStmt = $conn->prepare($countQuery);
        
        if (!$countStmt) {
            throw new Exception("Prepare failed for count query: " . $conn->error);
        }

        if ($userRole == "Admin") {
            $countStmt->bind_param("i", $userId);
        }

        if (!$countStmt->execute()) {
            throw new Exception("Execute failed for count query: " . $countStmt->error);
        }

        $totalResult = $countStmt->get_result();
        $totalOrders = $totalResult->fetch_assoc()['total'];
        $totalPages = ceil($totalOrders / $limit);
        logActivity("Total orders found: $totalOrders, Total pages: $totalPages");

        // Execute data query
        $dataQuery = $baseQuery . $condition . " ORDER BY updated_at DESC LIMIT ? OFFSET ?";
        $stmt = $conn->prepare($dataQuery);
        
        if (!$stmt) {
            throw new Exception("Prepare failed for data query: " . $conn->error);
        }

        if ($userRole == "Admin") {
            $stmt->bind_param("iii", $userId, $limit, $offset);
        } else {
            $stmt->bind_param("ii", $limit, $offset);
        }

        if (!$stmt->execute()) {
            throw new Exception("Execute failed for data query: " . $stmt->error);
        }

        $result = $stmt->get_result();
        $orders = [];

        while ($row = $result->fetch_assoc()) {
            // Format numeric values
            $row['total_amount'] = number_format($row['total_amount'], 2);
            $orders[] = $row;
        }

        $conn->commit();
        logActivity("Successfully retrieved " . count($orders) . " orders");

        // Prepare response
        $response = [
            'success' => true,
            'orders' => $orders,
            'pagination' => [
                'total' => $totalOrders,
                'page' => $page,
                'limit' => $limit,
                'totalPages' => $totalPages,
                'hasNext' => $page < $totalPages,
                'hasPrev' => $page > 1
            ],
            'requested_by' => $userId,
            'user_role' => $userRole,
            'timestamp' => date('c')
        ];

        echo json_encode($response);
        logActivity("Order listing fetch completed successfully");

    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
        }
        throw $e;
    }
} catch (Exception $e) {
    $errorMsg = "Error fetching order listing: " . $e->getMessage();
    logActivity($errorMsg);
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to fetch orders',
        'error' => $e->getMessage()
    ]);
} finally {
    // Clean up resources
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    if (isset($countStmt) && $countStmt instanceof mysqli_stmt) {
        $countStmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
    logActivity("Order listing fetch process completed");
}
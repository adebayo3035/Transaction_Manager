<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

// Initialize variables for cleanup
$stmt = null;
$countStmt = null;

logActivity("=== Order listing fetch process started ===");

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

    // Delivery status filter
    $validStatuses = [
        'Pending','Assigned','In Transit','Delivered',
        'Cancelled','Declined','Cancelled on Delivery'
    ];
    $deliveryStatus = isset($_GET['delivery_status']) ? trim($_GET['delivery_status']) : null;

    if ($deliveryStatus !== null && !in_array($deliveryStatus, $validStatuses, true)) {
        $errorMsg = "Invalid delivery_status filter: $deliveryStatus";
        logActivity($errorMsg);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid delivery status filter provided.'
        ]);
        exit();
    }

    logActivity("Delivery status filter: " . ($deliveryStatus ?: "none"));

    try {
        // Start transaction for consistent data view
        $conn->begin_transaction();
        logActivity("Database transaction started");

        // Base queries
        $baseQuery = "SELECT order_id, order_date, customer_id, total_amount, delivery_status FROM orders";
        $countQuery = "SELECT COUNT(*) as total FROM orders";

        $where = [];
        $params = [];
        $types = "";

        // Role-based filtering
        if ($userRole == "Admin") {
            $where[] = "assigned_to = ?";
            $params[] = $userId;
            $types .= "i";
        } elseif ($userRole != "Super Admin") {
            throw new Exception("Unauthorized role: " . $userRole);
        }

        // Delivery status filter
        if ($deliveryStatus !== null) {
            $where[] = "delivery_status = ?";
            $params[] = $deliveryStatus;
            $types .= "s";
        }

        $condition = count($where) ? " WHERE " . implode(" AND ", $where) : "";
        logActivity("WHERE clause built: " . ($condition ?: "none"));

        // Count query
        $countQuery .= $condition;
        $countStmt = $conn->prepare($countQuery);
        if (!$countStmt) throw new Exception("Prepare failed for count query: " . $conn->error);

        if ($params) {
            $countStmt->bind_param($types, ...$params);
        }

        if (!$countStmt->execute()) {
            throw new Exception("Execute failed for count query: " . $countStmt->error);
        }

        $countResult = $countStmt->get_result();
        $totalOrders = $countResult->fetch_assoc()['total'] ?? 0;
        $countStmt->close();

        $totalPages = ceil($totalOrders / $limit);
        logActivity("Total orders found: $totalOrders, Total pages: $totalPages");

        // Data query
        $orderBy = " ORDER BY order_id DESC, delivery_status";
        $dataQuery = $baseQuery . $condition . $orderBy . " LIMIT ? OFFSET ?";
        $stmt = $conn->prepare($dataQuery);
        if (!$stmt) throw new Exception("Prepare failed for data query: " . $conn->error);

        // Bind parameters
        $paramsMain = $params;
        $typesMain = $types . "ii";
        $paramsMain[] = $limit;
        $paramsMain[] = $offset;

        $stmt->bind_param($typesMain, ...$paramsMain);
        logActivity("Executing main query with params -> Types: $typesMain, Values: " . json_encode($paramsMain));

        if (!$stmt->execute()) {
            throw new Exception("Execute failed for data query: " . $stmt->error);
        }

        $result = $stmt->get_result();
        $orders = [];
        while ($row = $result->fetch_assoc()) {
            $row['total_amount'] = number_format($row['total_amount'], 2);
            $orders[] = $row;
        }

        $conn->commit();
        logActivity("Successfully retrieved " . count($orders) . " orders");

        // Response
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
            'filters' => [
                'delivery_status' => $deliveryStatus
            ],
            'timestamp' => date('c')
        ];

        echo json_encode($response);
        logActivity("Order listing fetch completed successfully");

    } catch (Exception $e) {
        if (isset($conn)) $conn->rollback();
        $errorMsg = "Error fetching order listing (inner): " . $e->getMessage();
        logActivity($errorMsg);
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to fetch orders',
            'error' => $e->getMessage()
        ]);
    } 
} catch (Exception $e) {
    $errorMsg = "Error fetching order listing (outer): " . $e->getMessage();
    logActivity($errorMsg);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch orders',
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($stmt) && $stmt instanceof mysqli_stmt) $stmt->close();
    if (isset($conn)) $conn->close();
    logActivity("=== Order listing fetch process completed ===");
}

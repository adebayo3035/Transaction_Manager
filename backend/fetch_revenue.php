<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

// Initialize logging
logActivity("Revenue listing fetch process started");

try {
    // Check authentication
    if (!isset($_SESSION['unique_id'])) {
        $errorMsg = "Unauthenticated access attempt to revenue listing";
        logActivity($errorMsg);
        echo json_encode(["success" => false, "message" => "Not logged in."]);
        exit();
    }

    $adminId = $_SESSION['unique_id'];
    logActivity("Revenue listing request initiated by admin ID: " . $adminId);

    // Get and validate pagination parameters
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    
    if ($page < 1 || $limit < 1) {
        $errorMsg = "Invalid pagination parameters - Page: $page, Limit: $limit";
        logActivity($errorMsg);
        echo json_encode(['success' => false, 'message' => 'Invalid pagination parameters']);
        exit();
    }

    $offset = ($page - 1) * $limit;
    logActivity("Fetching revenue listing - Page: $page, Limit: $limit, Offset: $offset");

    // Get and validate status filter
    $status = isset($_GET['status']) ? trim($_GET['status']) : "";
    $validStatuses = ["Cancelled", "Completed"];
    $statusFilter = "";

    if ($status !== "") {
        if (!in_array($status, $validStatuses)) {
            $errorMsg = "Invalid status filter provided: $status";
            logActivity($errorMsg);
            echo json_encode(['success' => false, 'message' => 'Invalid status filter']);
            exit();
        }
        $statusFilter = $status;
        logActivity("Status filter applied: $statusFilter");
    } else {
        logActivity("No status filter applied, fetching all revenue records");
    }

    try {
        // Start transaction for consistent data view
        $conn->begin_transaction();
        logActivity("Transaction started for revenue listing fetch");

        // Build base queries
        $baseTotalQuery = "SELECT COUNT(*) as total FROM revenue";
        $baseQuery = "SELECT revenue_id, order_id, customer_id, retained_amount, 
                             status, transaction_date, updated_at 
                      FROM revenue";

        $whereClause = "";
        $params = [];
        $types = "";

        if ($statusFilter !== "") {
            $whereClause = " WHERE status = ?";
            $params[] = $statusFilter;
            $types .= "s";
        }

        // Get total count
        $totalQuery = $baseTotalQuery . $whereClause;
        $stmtTotal = $conn->prepare($totalQuery);
        if (!$stmtTotal) {
            throw new Exception("Prepare failed for total count: " . $conn->error);
        }

        if ($statusFilter !== "") {
            $stmtTotal->bind_param($types, ...$params);
        }

        if (!$stmtTotal->execute()) {
            throw new Exception("Execute failed for total count: " . $stmtTotal->error);
        }

        $totalResult = $stmtTotal->get_result();
        $totalRow = $totalResult->fetch_assoc();
        $total = $totalRow['total'];
        logActivity("Total revenue records found (with filter): " . $total);
        $stmtTotal->close();

        // Fetch paginated revenue records
        $query = $baseQuery . $whereClause . " ORDER BY updated_at DESC LIMIT ?, ?";
        $stmt = $conn->prepare($query);

        if (!$stmt) {
            throw new Exception("Prepare failed for revenue listing: " . $conn->error);
        }

        if ($statusFilter !== "") {
            $params[] = $offset;
            $params[] = $limit;
            $types .= "ii";
            $stmt->bind_param($types, ...$params);
        } else {
            $stmt->bind_param("ii", $offset, $limit);
        }

        if (!$stmt->execute()) {
            throw new Exception("Execute failed for revenue listing: " . $stmt->error);
        }

        $result = $stmt->get_result();
        $revenues = [];

        while ($row = $result->fetch_assoc()) {
            $revenues[] = $row;
        }

        $conn->commit();
        logActivity("Successfully retrieved " . count($revenues) . " revenue records");

        // Prepare response
        $response = [
            'success' => true,
            'revenues' => $revenues,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => ceil($total / $limit),
            'requested_by' => $adminId,
            'timestamp' => date('c'),
            'status_filter' => $statusFilter
        ];

        echo json_encode($response);
        logActivity("Revenue listing fetch completed successfully");

    } catch (Exception $e) {
        $conn->rollback();
        throw $e; // Re-throw to outer catch block
    }
} catch (Exception $e) {
    $errorMsg = "Error fetching revenue listing: " . $e->getMessage();
    logActivity($errorMsg);
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to fetch revenue listing',
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    if (isset($totalResult)) {
        $totalResult->free();
    }
    $conn->close();
    logActivity("Revenue listing fetch process completed");
}

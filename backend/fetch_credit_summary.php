<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

// Initialize logging
logActivity("Credit orders listing process started");

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

    // Validate and sanitize pagination parameters
    $page  = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) && is_numeric($_GET['limit']) && $_GET['limit'] > 0 && $_GET['limit'] <= 100 ? (int)$_GET['limit'] : 10;
    $offset = ($page - 1) * $limit;

    // Filters
    $whereClauses = [];
    $types = "";
    $params = [];

    // repayment_status filter
    if (!empty($_GET['repayment_status'])) {
        $repaymentStatus = $_GET['repayment_status'];
        if (in_array($repaymentStatus, ['Paid', 'Pending', 'Void'], true)) {
            $whereClauses[] = "co.repayment_status = ?";
            $types .= "s";
            $params[] = $repaymentStatus;
            logActivity("Filter applied: repayment_status = $repaymentStatus");
        }
    }

    // due status filter (due | overdue)
    if (!empty($_GET['due_status'])) {
        $dueStatus = $_GET['due_status'];
        if ($dueStatus === 'Due') {
            $whereClauses[] = "(co.due_date >= CURDATE() OR co.repayment_status = 'Paid')";
            logActivity("Filter applied: due_status = Due");
        } elseif ($dueStatus === 'Overdue') {
            $whereClauses[] = "(co.due_date < CURDATE() AND co.repayment_status != 'Paid')";
            logActivity("Filter applied: due_status = Overdue");
        }
    }

    $whereSql = count($whereClauses) > 0 ? "WHERE " . implode(" AND ", $whereClauses) : "";

    // Start transaction for consistent data view
    $conn->begin_transaction();
    logActivity("Transaction started for credit orders listing");

    try {
        // Main query
        $query = "SELECT co.*, 
                        CONCAT(c.firstname, ' ', c.lastname) AS customer_name,
                        (SELECT SUM(amount_paid) FROM repayment_history 
                         WHERE credit_order_id = co.credit_order_id) AS total_paid
                  FROM credit_orders co
                  JOIN customers c ON co.customer_id = c.customer_id
                  $whereSql
                  ORDER BY co.created_at DESC
                  LIMIT ? OFFSET ?";

        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed for credit orders: " . $conn->error);
        }

        // Bind filters + pagination
        $typesWithPagination = $types . "ii";
        $paramsWithPagination = array_merge($params, [$limit, $offset]);

        $stmt->bind_param($typesWithPagination, ...$paramsWithPagination);

        if (!$stmt->execute()) {
            throw new Exception("Execute failed for credit orders: " . $stmt->error);
        }

        $result = $stmt->get_result();
        $credits = [];

        while ($row = $result->fetch_assoc()) {
            $currentDate = new DateTime();
            $dueDate     = new DateTime($row['due_date']);

            $row['is_overdue'] = ($currentDate > $dueDate && $row['repayment_status'] !== 'Paid');
            $row['days_overdue'] = $row['is_overdue']
                ? $currentDate->diff($dueDate)->days
                : 0;
            $row['remaining_balance'] = max(0, $row['total_credit_amount'] - $row['total_paid']);

            $credits[] = $row;
        }

        logActivity("Fetched " . count($credits) . " credit orders");

        // Count query
        $countQuery = "SELECT COUNT(*) as total FROM credit_orders co $whereSql";
        $countStmt = $conn->prepare($countQuery);
        if (!$countStmt) {
            throw new Exception("Prepare failed for count: " . $conn->error);
        }

        if ($types !== "") {
            $countStmt->bind_param($types, ...$params);
        }

        if (!$countStmt->execute()) {
            throw new Exception("Execute failed for count: " . $countStmt->error);
        }

        $countResult = $countStmt->get_result();
        $totalCount = $countResult->fetch_assoc()['total'];

        $conn->commit();

        // Response
        $response = [
            'success' => true,
            'data' => [
                'credits' => $credits,
                'pagination' => [
                    'total' => (int)$totalCount,
                    'page' => $page,
                    'limit' => $limit,
                    'total_pages' => ceil($totalCount / $limit)
                ]
            ],
            'meta' => [
                'requested_by' => $adminId,
                'timestamp' => date('c'),
                'version' => '1.1'
            ]
        ];

        echo json_encode($response);
        logActivity("Credit orders listing completed successfully");

    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
} catch (Exception $e) {
    $errorMsg = "Error fetching credit orders: " . $e->getMessage();
    logActivity($errorMsg);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch credit orders',
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    if (isset($countStmt) && $countStmt instanceof mysqli_stmt) {
        $countStmt->close();
    }
    $conn->close();
    logActivity("Credit orders listing process completed");
}

<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

// Initialize logging
logActivity("Transaction listing fetch process started");

try {
    // Check authentication
    if (!isset($_SESSION['unique_id'])) {
        $errorMsg = "Unauthenticated access attempt to transaction listing";
        logActivity($errorMsg);
        echo json_encode(["success" => false, "message" => "Not logged in."]);
        exit();
    }

    $adminId = $_SESSION['unique_id'];
    $userRole = $_SESSION['role'] ?? null;
    logActivity("Request initiated by user ID: $adminId (Role: $userRole)");

    // Validate and sanitize pagination parameters
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;

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
    logActivity("Pagination validated - Page: $page, Limit: $limit, Offset: $offset");

    // ---- FILTERS ----
    $filters = [];
    $params = [];
    $types = "";

    // Allowed values
    $allowedTypes = ["Credit", "Debit", "Others"];
    $allowedStatuses = ["Pending", "Completed", "Failed", "Declined"];

    if (isset($_GET['transaction_type']) && in_array($_GET['transaction_type'], $allowedTypes)) {
        $filters[] = "transaction_type = ?";
        $params[] = $_GET['transaction_type'];
        $types .= "s";
        logActivity("Filter applied: transaction_type = {$_GET['transaction_type']}");
    }

    if (isset($_GET['status']) && in_array($_GET['status'], $allowedStatuses)) {
        $filters[] = "status = ?";
        $params[] = $_GET['status'];
        $types .= "s";
        logActivity("Filter applied: status = {$_GET['status']}");
    }

    $whereSql = count($filters) > 0 ? "WHERE " . implode(" AND ", $filters) : "";

    try {
        // Start transaction for consistent data view
        $conn->begin_transaction();
        logActivity("Database transaction started");

        // ---- COUNT QUERY ----
        $countSql = "SELECT COUNT(*) as total FROM transactions $whereSql";
        $countStmt = $conn->prepare($countSql);
        if (!$countStmt) throw new Exception("Count prepare failed: " . $conn->error);

        if (!empty($filters)) {
            $countStmt->bind_param($types, ...$params);
        }

        if (!$countStmt->execute()) {
            throw new Exception("Count execute failed: " . $countStmt->error);
        }

        $countResult = $countStmt->get_result();
        $totalRow = $countResult->fetch_assoc();
        $total = $totalRow['total'];
        $totalPages = ceil($total / $limit);

        logActivity("Total filtered transactions: $total, Total pages: $totalPages");

        // ---- DATA QUERY ----
        $query = "SELECT transaction_ref, transaction_type, amount, 
                         payment_method, status, transaction_date 
                  FROM transactions 
                  $whereSql
                  ORDER BY transaction_date DESC 
                  LIMIT ?, ?";

        $stmt = $conn->prepare($query);
        if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);

        if (!empty($filters)) {
            $bindTypes = $types . "ii"; // add ints for limit/offset
            $bindParams = array_merge($params, [$offset, $limit]);
            $stmt->bind_param($bindTypes, ...$bindParams);
        } else {
            $stmt->bind_param("ii", $offset, $limit);
        }

        if (!$stmt->execute()) throw new Exception("Execute failed: " . $stmt->error);

        $result = $stmt->get_result();
        $transactions = [];

        while ($row = $result->fetch_assoc()) {
            $row['amount'] = number_format($row['amount'], 2);
            $transactions[] = $row;
        }

        $conn->commit();
        logActivity("Retrieved " . count($transactions) . " transactions for page $page");

        echo json_encode([
            'success' => true,
            'transactions' => $transactions,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'totalPages' => $totalPages,
                'hasNext' => $page < $totalPages,
                'hasPrev' => $page > 1
            ],
            'filters' => [
                'transaction_type' => $_GET['transaction_type'] ?? null,
                'status' => $_GET['status'] ?? null
            ],
            'requested_by' => $adminId,
            'user_role' => $userRole,
            'timestamp' => date('c')
        ]);

        logActivity("Transaction listing fetch completed successfully");

    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
} catch (Exception $e) {
    $errorMsg = "Error fetching transaction listing: " . $e->getMessage();
    logActivity($errorMsg);
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to fetch transactions',
        'error' => $e->getMessage(),
        'timestamp' => date('c')
    ]);
} finally {
    if (isset($stmt) && $stmt instanceof mysqli_stmt) $stmt->close();
    if (isset($countStmt) && $countStmt instanceof mysqli_stmt) $countStmt->close();
    if (isset($countResult)) $countResult->free();
    $conn->close();
    logActivity("Transaction listing fetch process completed");
}

<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

try {
    // Validate session
    $customerId = $_SESSION["customer_id"] ?? null;
    logActivity("Starting credit orders retrieval process");

    if (!$customerId) {
        throw new Exception("No customer ID found in session");
    }

    checkSession($customerId);
    logActivity("Session validated successfully");

    // Pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

    if ($page < 1 || $limit < 1) {
        throw new Exception("Invalid pagination values");
    }

    $offset = ($page - 1) * $limit;
    logActivity("Pagination: Page=$page, Limit=$limit, Offset=$offset");

    // Incoming filters
    $repaymentStatus = trim($_GET['repayment_status'] ?? '');
    $dueStatus = trim($_GET['due_status'] ?? '');

    logActivity("Filters applied - repayment_status='$repaymentStatus', due_status='$dueStatus'");

    // ---------------------------------------
    // BUILD FILTER CONDITIONS DYNAMICALLY
    // ---------------------------------------
    $conditions = ["customer_id = ?"];
    $params = [$customerId];
    $types = "i";

     // repayment_status filter
    if (!empty($repaymentStatus)) {
        if (in_array($repaymentStatus, ['Paid', 'Pending', 'Void'], true)) {
            $conditions[] = "repayment_status = ?";
            $types .= "s";
            $params[] = $repaymentStatus;
            logActivity("Filter applied: repayment_status = $repaymentStatus");
        }
    }

    // due status filter (due | overdue)
    if (!empty($dueStatus)) {
        if ($dueStatus === 'Due') {
            $conditions[] = "(due_date >= CURDATE() OR repayment_status = 'Paid')";
            logActivity("Filter applied: due_status = Due");
        } elseif ($dueStatus === 'Overdue') {
            $conditions[] = "(due_date < CURDATE() AND repayment_status != 'Paid')";
            logActivity("Filter applied: due_status = Overdue");
        }
    }

    $whereClause = implode(" AND ", $conditions);

    // ---------------------------------------
    // MAIN QUERY WITH FILTERS
    // ---------------------------------------
    $query = "
        SELECT credit_order_id, order_id, created_at, status, remaining_balance, repayment_status, due_date
        FROM credit_orders
        WHERE $whereClause
        ORDER BY credit_order_id DESC
        LIMIT ? OFFSET ?
    ";

    logActivity("Executing filtered query: " . str_replace("\n", " ", $query));

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    // Add limit & offset to params
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";

    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    $result = $stmt->get_result();
    $credits = [];

    while ($row = $result->fetch_assoc()) {
        $credits[] = $row;
    }

    logActivity("Fetched " . count($credits) . " filtered credit orders");

    // ---------------------------------------
    // TOTAL COUNT QUERY WITH SAME FILTERS
    // ---------------------------------------
    $countQuery = "
        SELECT COUNT(*) AS total
        FROM credit_orders
        WHERE $whereClause
    ";

    $countStmt = $conn->prepare($countQuery);
    if (!$countStmt) {
        throw new Exception("Count prepare failed: " . $conn->error);
    }

    // Use same filters but without limit/offset
    $countStmt->bind_param(substr($types, 0, strlen($types) - 2), ...array_slice($params, 0, -2));
    $countStmt->execute();

    $countResult = $countStmt->get_result();
    $totalCredits = $countResult->fetch_assoc()['total'];

    logActivity("Total filtered credit orders: $totalCredits");

    // ---------------------------------------
    // RESPONSE
    // ---------------------------------------
    echo json_encode([
        'success' => true,
        'credits' => $credits,
        'total' => $totalCredits,
        'page' => $page,
        'limit' => $limit,
    ]);

} catch (Exception $e) {
    logActivity("EXCEPTION: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred',
        'details' => $e->getMessage()
    ]);
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($countStmt)) $countStmt->close();
    logActivity("Script execution completed");
}

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

    // Input validation and sanitization
    logActivity("Validating pagination parameters" );
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

    if ($page < 1 || $limit < 1) {
        $errorMessage = "Invalid pagination values - Page: $page, Limit: $limit";
        logActivity($errorMessage);
        throw new Exception('Invalid page or limit value');
    }

    $offset = ($page - 1) * $limit;
    logActivity("Pagination calculated - Page: $page, Limit: $limit, Offset: $offset" );

    // Database operations
    logActivity("Preparing to fetch credit orders from database");
    $query = "SELECT credit_order_id, order_id, created_at, remaining_balance, repayment_status, due_date
              FROM credit_orders 
              WHERE customer_id = ? 
              ORDER BY credit_order_id DESC
              LIMIT ? OFFSET ?";
    
    logActivity("Executing query: " . str_replace(["\n", "\r", "\t"], " ", $query));
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        $dbError = "Prepare failed: " . $conn->error;
        logActivity($dbError, );
        throw new Exception("Database error");
    }

    $stmt->bind_param("iii", $customerId, $limit, $offset);
    if (!$stmt->execute()) {
        $execError = "Execute failed: " . $stmt->error;
        logActivity($execError);
        throw new Exception("Database error");
    }

    $result = $stmt->get_result();
    $credits = [];
    $recordCount = 0;

    while ($row = $result->fetch_assoc()) {
        $recordCount++;
        $credits[] = $row;
    }
    //$stmt->close();
    logActivity("Fetched $recordCount credit orders");

    // Get total count for pagination
    logActivity("Fetching total credit order count");
    $countQuery = "SELECT COUNT(*) as total FROM credit_orders WHERE customer_id = ?";
    $countStmt = $conn->prepare($countQuery);
    
    if (!$countStmt) {
        $countError = "Count prepare failed: " . $conn->error;
        logActivity($countError);
        throw new Exception("Database error");
    }

    $countStmt->bind_param("i", $customerId);
    if (!$countStmt->execute()) {
        $countExecError = "Count execute failed: " . $countStmt->error;
        logActivity($countExecError);
        throw new Exception("Database error");
    }

    $countResult = $countStmt->get_result();
    $totalCredits = $countResult->fetch_assoc()['total'];
    //$countStmt->close();
    logActivity("Total credit orders count: $totalCredits");

    // Prepare response
    $response = [
        'success' => true,
        'credits' => $credits,
        'total' => $totalCredits,
        'page' => $page,
        'limit' => $limit,
        'record_count' => $recordCount
    ];

    logActivity("Successfully processed request. Returning $recordCount records of $totalCredits total");
    echo json_encode($response);

} catch (Exception $e) {
    $errorDetails = [
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ];
    
    logActivity("EXCEPTION: " . json_encode($errorDetails));
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred',
        'error_code' => $e->getCode()
    ]);
} finally {
    // Ensure resources are cleaned up
    if (isset($stmt) && $stmt) {
        $stmt->close();
        logActivity("Closed main query statement");
    }
    
    if (isset($countStmt) && $countStmt) {
        $countStmt->close();
        logActivity("Closed count query statement");
    }
    
    if (isset($conn) && $conn) {
        //$conn->close();
        logActivity("Closed database connection");
    }
    
    logActivity("Script execution completed");
}
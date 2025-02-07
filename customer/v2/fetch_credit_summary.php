<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

$customerId = $_SESSION["customer_id"];
checkSession($customerId);

// Log script execution start
logActivity("Script started for customer ID: $customerId");

// Validate and sanitize inputs
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

if ($page < 1 || $limit < 1) {
    $errorMessage = 'Invalid page or limit value';
    logActivity("Error: $errorMessage for customer ID: $customerId");
    echo json_encode(['success' => false, 'message' => $errorMessage]);
    exit;
}

$offset = ($page - 1) * $limit;

try {
    // Fetch credits with pagination
    $stmt = $conn->prepare("SELECT credit_order_id, order_id, created_at, remaining_balance, repayment_status, due_date
                            FROM credit_orders WHERE customer_id = ? ORDER BY credit_order_id DESC
                            LIMIT ? OFFSET ?");
    $stmt->bind_param("iii", $customerId, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $credits = [];
    while ($row = $result->fetch_assoc()) {
        $credits[] = $row;
    }

    // Fetch total credits count for pagination
    $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM credit_orders WHERE customer_id = ?");
    $countStmt->bind_param("i", $customerId);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalCredits = $countResult->fetch_assoc()['total'];

    // Log successful data fetch
    logActivity("Successfully fetched credit orders for customer ID: $customerId. Page: $page, Limit: $limit");

    echo json_encode([
        'success' => true,
        'credits' => $credits,
        'total' => $totalCredits,
        'page' => $page,
        'limit' => $limit
    ]);
} catch (Exception $e) {
    // Log the error
    $errorMessage = $e->getMessage();
    logActivity("Error fetching credit orders for customer ID: $customerId. Error: $errorMessage");

    echo json_encode(['success' => false, 'message' => $errorMessage]);
}
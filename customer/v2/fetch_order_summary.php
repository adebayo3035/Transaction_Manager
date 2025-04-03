<?php
include 'config.php';
session_start();

$customerId = $_SESSION["customer_id"] ?? null;

// Log script execution start
logActivity("SCRIPT START: Customer order history fetch initiated for customer ID: $customerId");

// Validate session
if (!$customerId) {
    logActivity("ERROR: No valid session found. Customer ID missing.");
    echo json_encode(["success" => false, "message" => "Session expired. Please log in again."]);
    exit();
}

// Validate and sanitize inputs
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
logActivity("PAGINATION: Page number set to $page");

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
logActivity("PAGINATION: Limit set to $limit records per page");

if ($page < 1 || $limit < 1) {
    $errorMessage = "Invalid page ($page) or limit ($limit). Must be positive integers.";
    logActivity("VALIDATION FAILED: $errorMessage");
    echo json_encode(["success" => false, "message" => $errorMessage]);
    exit();
}

$offset = ($page - 1) * $limit;
logActivity("PAGINATION: Calculated offset: $offset");

try {
    // Fetch total count of orders
    $totalQuery = "SELECT COUNT(*) as total FROM orders WHERE customer_id = ?";
    logActivity("DATABASE: Executing total orders query: $totalQuery");
    
    $stmt = $conn->prepare($totalQuery);
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $totalResult = $stmt->get_result();
    $totalOrders = $totalResult->fetch_assoc()['total'];
    $stmt->close();

    logActivity("DATABASE RESULT: Customer $customerId has $totalOrders total orders");

    // Fetch paginated orders
    $query = "
        SELECT order_id, order_date, total_amount, discount, delivery_status 
        FROM orders 
        WHERE customer_id = ? 
        ORDER BY order_date DESC, delivery_status ASC 
        LIMIT ? OFFSET ?";
    
    logActivity("DATABASE: Fetching orders for customer $customerId (Page: $page, Limit: $limit)");
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $customerId, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    $stmt->close();

    $ordersCount = count($orders);
    logActivity("DATABASE RESULT: Retrieved $ordersCount orders for customer $customerId");

    // Return the response
    logActivity("SUCCESS: Returning order data to client");
    echo json_encode([
        "success" => true,
        "orders" => $orders,
        "total" => $totalOrders,
        "page" => $page,
        "limit" => $limit
    ]);

} catch (Exception $e) {
    $errorMessage = $e->getMessage();
    logActivity("ERROR: Exception occurred - " . $errorMessage);
    echo json_encode(["success" => false, "message" => "Server error. Please try again later."]);

} finally {
    if (isset($conn)) {
        $conn->close();
        logActivity("DATABASE: Connection closed");
    }
    logActivity("SCRIPT END: Order fetch process completed");
}
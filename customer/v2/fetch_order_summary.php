<?php
include 'config.php';
session_start();

$customerId = $_SESSION["customer_id"];
checkSession($customerId);

// Log script execution start
logActivity("Script started for customer ID: $customerId");

// Validate and sanitize inputs
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
logActivity("Getting the number of Pages set from the Front end");
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
logActivity("Getting and setting the limit for the number of records per page");

if ($page < 1 || $limit < 1) {
    $errorMessage = "Invalid page or limit value. Page: $page, Limit: $limit";
    logActivity("Error: $errorMessage for customer ID: $customerId");
    echo json_encode(["success" => false, "message" => $errorMessage]);
    exit();
}

$offset = ($page - 1) * $limit;

try {
    // Fetch total count of orders for the customer
    $totalQuery = "SELECT COUNT(*) as total FROM orders WHERE customer_id = ?";
    logActivity("Checking the total number of orders for customer ID $customerId");
    $stmt = $conn->prepare($totalQuery);
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $totalResult = $stmt->get_result();
    $totalOrders = $totalResult->fetch_assoc()['total'];
    $stmt->close();

    // Log total orders count
    logActivity("Total orders found for customer ID: $customerId: $totalOrders");

    // Fetch paginated orders
    logActivity("fetching Order records for customer ID $customerId");
    $query = "
        SELECT order_id, order_date, total_amount, discount, delivery_status 
        FROM orders 
        WHERE customer_id = ? 
        ORDER BY order_date DESC, delivery_status ASC 
        LIMIT ? OFFSET ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $customerId, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    $stmt->close();

    // Log successful data fetch
    logActivity("Successfully fetched orders for customer ID: $customerId. Page: $page, Limit: $limit");

    // Return the response
    echo json_encode([
        "success" => true,
        "orders" => $orders,
        "total" => $totalOrders,
        "page" => $page,
        "limit" => $limit
    ]);
} catch (Exception $e) {
    // Log the error
    $errorMessage = $e->getMessage();
    logActivity("Error fetching orders for customer ID: $customerId. Error: $errorMessage");

    // Return the error response
    echo json_encode(["success" => false, "message" => $errorMessage]);
} finally {
    // Close the database connection
    if (isset($conn)) {
        $conn->close();
    }
}
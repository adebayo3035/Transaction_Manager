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
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$deliveryStatus = isset($_GET['delivery_status']) ? trim($_GET['delivery_status']) : '';

logActivity("INPUT: Page = $page, Limit = $limit, Delivery Status Filter = '$deliveryStatus'");

if ($page < 1 || $limit < 1) {
    $errorMessage = "Invalid page ($page) or limit ($limit). Must be positive integers.";
    logActivity("VALIDATION FAILED: $errorMessage");
    echo json_encode(["success" => false, "message" => $errorMessage]);
    exit();
}

$offset = ($page - 1) * $limit;
logActivity("PAGINATION: Calculated offset = $offset");

try {
    // ---------------------------------------
    // 1️⃣ Fetch total count (with filter)
    // ---------------------------------------

    if ($deliveryStatus !== '') {
        $totalQuery = "SELECT COUNT(*) AS total FROM orders WHERE customer_id = ? AND delivery_status = ?";
        logActivity("DATABASE: Running filtered total count query (delivery_status = '$deliveryStatus')");
        $stmt = $conn->prepare($totalQuery);
        $stmt->bind_param("is", $customerId, $deliveryStatus);
    } else {
        $totalQuery = "SELECT COUNT(*) AS total FROM orders WHERE customer_id = ?";
        logActivity("DATABASE: Running unfiltered total count query");
        $stmt = $conn->prepare($totalQuery);
        $stmt->bind_param("i", $customerId);
    }

    $stmt->execute();
    $totalResult = $stmt->get_result();
    $totalOrders = $totalResult->fetch_assoc()['total'];
    $stmt->close();

    logActivity("DATABASE RESULT: Total orders after filtering = $totalOrders");

    // ---------------------------------------
    // 2️⃣ Fetch paginated records (with filter)
    // ---------------------------------------

    if ($deliveryStatus !== '') {
        $query = "
            SELECT order_id, order_date, total_amount, discount, delivery_status
            FROM orders
            WHERE customer_id = ? AND delivery_status = ?
            ORDER BY order_date DESC, delivery_status ASC
            LIMIT ? OFFSET ?
        ";

        logActivity("DATABASE: Fetching filtered orders (delivery_status = '$deliveryStatus')");
        $stmt = $conn->prepare($query);
        $stmt->bind_param("isii", $customerId, $deliveryStatus, $limit, $offset);

    } else {
        $query = "
            SELECT order_id, order_date, total_amount, discount, delivery_status
            FROM orders
            WHERE customer_id = ?
            ORDER BY order_date DESC, delivery_status ASC
            LIMIT ? OFFSET ?
        ";

        logActivity("DATABASE: Fetching unfiltered orders");
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iii", $customerId, $limit, $offset);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    $stmt->close();

    logActivity("DATABASE RESULT: Retrieved " . count($orders) . " orders for customer $customerId");

    // ---------------------------------------
    // 3️⃣ Return the response
    // ---------------------------------------
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

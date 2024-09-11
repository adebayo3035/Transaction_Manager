<?php
include 'config.php';
session_start();

if (!isset($_SESSION['unique_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}

$userId = $_SESSION['unique_id'];
$role = $_SESSION['role'];
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
$offset = ($page - 1) * $limit;

// Initialize the base query
$baseQuery = "SELECT order_id, order_date, customer_id, total_amount, delivery_status FROM orders";
$countQuery = "SELECT COUNT(*) as total FROM orders";

// Conditional logic for role-based queries
if ($role == "Admin") {
    $condition = " WHERE assigned_to = ?";
} else if ($role == "Super Admin") {
    $condition = "";
} else {
    echo json_encode(["success" => false, "message" => "Unauthorized role."]);
    exit();
}

// Combine the condition with the base query
$countQuery .= $condition;
$dataQuery = $baseQuery . $condition . " ORDER BY updated_at DESC LIMIT ? OFFSET ?";

// Prepare and execute the count query
$stmt = $conn->prepare($countQuery);
if ($role == "Admin") {
    $stmt->bind_param("i", $userId);
}
$stmt->execute();
$totalResult = $stmt->get_result();
$totalOrders = $totalResult->fetch_assoc()['total'];
$stmt->close();

// Prepare and execute the data query
$stmt = $conn->prepare($dataQuery);
if ($role == "Admin") {
    $stmt->bind_param("iii", $userId, $limit, $offset);
} else {
    $stmt->bind_param("ii", $limit, $offset);
}
$stmt->execute();
$result = $stmt->get_result();

// Fetch the results
$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

// Clean up
$stmt->close();
$conn->close();

// Return the result as JSON
echo json_encode([
    "success" => true,
    "orders" => $orders,
    "total" => $totalOrders,
    "page" => $page,
    "limit" => $limit
]);

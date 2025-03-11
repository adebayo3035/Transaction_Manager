<?php
include 'config.php';
session_start();

$driverId = $_SESSION['driver_id'];
checkDriverSession($driverId);
logActivity("Session validated successfully for Driver ID: $driverId.");
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
$offset = ($page - 1) * $limit;
logActivity("Driver ID: $driverId requested orders. Page: $page, Limit: $limit.");

// Fetch total count of orders for the customer
$totalQuery = "SELECT COUNT(*) as total FROM orders WHERE driver_id = ?";
$stmt = $conn->prepare($totalQuery);
if ($stmt === false) {
    logActivity("Failed to prepare the total orders query.");
    echo json_encode(["success" => false, "message" => "Database error: Failed to prepare the total orders query."]);
    exit();
}
$stmt->bind_param("i", $driverId);
if (!$stmt->execute()) {
    logActivity("Failed to execute the total orders query.");
    echo json_encode(["success" => false, "message" => "Database error."]);
    exit();
}

$totalResult = $stmt->get_result();
$totalOrders = $totalResult->fetch_assoc()['total'];
logActivity("Total orders found for driver ID: $driverId: $totalOrders.");

// Fetch paginated orders
$query = "SELECT order_id, order_date, delivery_fee, delivery_status FROM orders WHERE driver_id = ? ORDER BY order_date DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);
if ($stmt === false) {
    logActivity("Failed to prepare the paginated orders query.");
    echo json_encode(["success" => false, "message" => "Database error."]);
    exit();
}

$stmt->bind_param("iii", $driverId, $limit, $offset);
if (!$stmt->execute()) {
    logActivity("Failed to execute the paginated orders query.");
    echo json_encode(["success" => false, "message" => "Database error."]);
    exit();
}

$result = $stmt->get_result();
$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

logActivity("Successfully fetched " . count($orders) . " orders for driver ID: $driverId.");

$stmt->close();
$conn->close();

echo json_encode([
    "success" => true,
    "orders" => $orders,
    "total" => $totalOrders,
    "page" => $page,
    "limit" => $limit
]);
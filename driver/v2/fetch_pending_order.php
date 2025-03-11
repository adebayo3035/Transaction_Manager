<?php
session_start();
include 'config.php';
$driverId = $_SESSION['driver_id'];
checkDriverSession($driverId);
logActivity("Session validated successfully for Driver ID: $driverId.");

$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
$offset = ($page - 1) * $limit;

logActivity("Driver ID: $driverId requested orders. Page: $page, Limit: $limit.");

// Fetch total count of orders for the driver with delivery_status 'Assigned' or 'In Transit'
$totalQuery = "SELECT COUNT(*) as total FROM orders WHERE driver_id = ? AND delivery_status IN ('Assigned', 'In Transit')";
$stmt = $conn->prepare($totalQuery);
if ($stmt === false) {
    logActivity("Failed to prepare the total orders query.");
    echo json_encode(["success" => false, "message" => "Database error."]);
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

// Fetch paginated orders for the driver with delivery_status 'Assigned' or 'In Transit'
$query = "SELECT order_id, order_date, delivery_fee, delivery_status FROM orders 
          WHERE driver_id = ? AND delivery_status IN ('Assigned', 'In Transit') 
          ORDER BY order_date DESC LIMIT ? OFFSET ?";
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

logActivity("Successfully fetched " . count($orders) . " paginated orders for driver ID: $driverId.");

// Fetch pending orders for the driver with delivery_status 'Assigned' or 'In Transit'
$statuses = ["Assigned", "In Transit"];
$placeholders = implode(',', array_fill(0, count($statuses), '?'));
$stmt = $conn->prepare("SELECT * FROM orders WHERE driver_id = ? AND delivery_status IN ($placeholders)");
if ($stmt === false) {
    logActivity("Failed to prepare the pending orders query.");
    echo json_encode(["success" => false, "message" => "Database error."]);
    exit();
}

// Bind parameters dynamically
$params = array_merge([$driverId], $statuses);
$types = str_repeat('s', count($params));
$stmt->bind_param($types, ...$params);
if (!$stmt->execute()) {
    logActivity("Failed to execute the pending orders query.");
    echo json_encode(["success" => false, "message" => "Database error."]);
    exit();
}

$result = $stmt->get_result();
$pending_orders = [];
while ($row2 = $result->fetch_assoc()) {
    $pending_orders[] = $row2;
}

logActivity("Successfully fetched " . count($pending_orders) . " pending orders for driver ID: $driverId.");

$stmt->close();
$conn->close();

echo json_encode([
    "success" => true,
    "orders" => $orders,
    "total" => $totalOrders,
    "page" => $page,
    "limit" => $limit,
    "pending_orders" => $pending_orders
]);
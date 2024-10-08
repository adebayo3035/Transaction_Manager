<?php
include 'config.php';
session_start();

if (!isset($_SESSION['driver_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}

$driverId = $_SESSION['driver_id'];
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
$offset = ($page - 1) * $limit;

// Fetch total count of orders for the customer
// Fetch total orders for a specific driver with delivery_status 'Assigned' or 'In Transit'
$totalQuery = "SELECT COUNT(*) as total FROM orders WHERE driver_id = ? AND delivery_status IN ('Assigned', 'In Transit')";
$stmt = $conn->prepare($totalQuery);
$stmt->bind_param("i", $driverId);
$stmt->execute();
$totalResult = $stmt->get_result();
$totalOrders = $totalResult->fetch_assoc()['total'];

// Fetch paginated orders for a specific driver with delivery_status 'Assigned' or 'In Transit'
$query = "SELECT order_id, order_date, delivery_fee, delivery_status FROM orders 
          WHERE driver_id = ? AND delivery_status IN ('Assigned', 'In Transit') 
          ORDER BY order_date DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("iii", $driverId, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

$statuses = ["Assigned", "In Transit"];
$placeholders = implode(',', array_fill(0, count($statuses), '?'));
$stmt = $conn->prepare("SELECT * FROM orders WHERE driver_id = ? AND delivery_status IN ($placeholders)");

// Bind parameters dynamically
$params = array_merge([$driverId], $statuses);
$types = str_repeat('s', count($params));
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$pending_orders = [];
while ($row2 = $result->fetch_assoc()) {
    $pending_orders[] = $row2;
}

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



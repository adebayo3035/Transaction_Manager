<?php
include 'config.php';
session_start();

if (!isset($_SESSION['driver_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}

$driverId = $_SESSION['driver_id'];
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = ($page - 1) * $limit;

// Fetch total count of orders for the customer
$totalQuery = "SELECT COUNT(*) as total FROM orders WHERE driver_id = ?";
$stmt = $conn->prepare($totalQuery);
$stmt->bind_param("i", $driverId);
$stmt->execute();
$totalResult = $stmt->get_result();
$totalOrders = $totalResult->fetch_assoc()['total'];

// Fetch paginated orders
$query = "SELECT order_id, order_date, delivery_fee, delivery_status FROM orders WHERE driver_id = ? ORDER BY order_date DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("iii", $driverId, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode([
    "success" => true,
    "orders" => $orders,
    "total" => $totalOrders,
    "page" => $page,
    "limit" => $limit
]);


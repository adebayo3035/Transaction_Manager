<?php
include 'config.php';
session_start();

if (!isset($_SESSION['unique_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}

// $customerId = $_SESSION['customer_id'];

$query = "SELECT order_id, order_date, total_amount, status FROM orders WHERE status = 'Pending' ORDER BY order_date DESC";
$stmt = $conn->prepare($query);
// $stmt->bind_param("i", $customerId);
$stmt->execute();
$result = $stmt->get_result();

$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode(["success" => true, "orders" => $orders]);


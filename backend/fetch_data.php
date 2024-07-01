<?php
include 'config.php';

// Fetch total orders
$sqlOrders = "SELECT COUNT(*) AS total_orders FROM orders";
$resultOrders = $conn->query($sqlOrders);
$totalOrders = $resultOrders->fetch_assoc()['total_orders'];

// Fetch total customers
$sqlCustomers = "SELECT COUNT(*) AS total_customers FROM customers";
$resultCustomers = $conn->query($sqlCustomers);
$totalCustomers = $resultCustomers->fetch_assoc()['total_customers'];

// Fetch recent 5 orders
$sqlRecentOrders = "SELECT * FROM orders ORDER BY order_date DESC LIMIT 5";
$resultRecentOrders = $conn->query($sqlRecentOrders);
$recentOrders = [];
while ($row = $resultRecentOrders->fetch_assoc()) {
    $recentOrders[] = $row;
}

// Fetch top 5 menu items
$sqlTopMenuItems = "SELECT food_id, food_name FROM food WHERE availability_status != 0 and available_quantity != 0 ORDER BY food_name DESC LIMIT 5"; // Assuming you have a column named 'popularity'
$resultTopMenuItems = $conn->query($sqlTopMenuItems);
$topMenuItems = [];
while ($row = $resultTopMenuItems->fetch_assoc()) {
    $topMenuItems[] = $row;
}

// Calculate total inflow from previous day
$sqlTotalInflow = "SELECT SUM(total_amount) AS total_inflow FROM revenue WHERE DATE(transaction_date) = CURDATE()";
// $sqlTotalInflow = "SELECT SUM(total_amount) AS total_inflow FROM revenue WHERE DATE(transaction_date) = CURDATE() - INTERVAL 1 DAY";
$resultTotalInflow = $conn->query($sqlTotalInflow);
$totalInflow = $resultTotalInflow->fetch_assoc()['total_inflow'];

$data = [
    "totalOrders" => $totalOrders,
    "totalCustomers" => $totalCustomers,
    "recentOrders" => $recentOrders,
    "topMenuItems" => $topMenuItems,
    "totalInflow" => $totalInflow
];

echo json_encode($data);

$conn->close();


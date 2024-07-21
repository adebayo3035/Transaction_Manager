<?php
include 'config.php';

// Fetch total orders
$sqlOrders = "SELECT COUNT(*) AS total_orders FROM orders where order_date = DATE(order_date) = CURDATE()";
$resultOrders = $conn->query($sqlOrders);
$totalOrders = $resultOrders->fetch_assoc()['total_orders'];

// Fetch total Pending orders today
$sqlPendingOrders = "SELECT COUNT(*) AS pending_orders FROM orders where status = 'Pending'";
$resultPendingOrders = $conn->query($sqlPendingOrders);
$totalPendingOrders = $resultPendingOrders->fetch_assoc()['pending_orders'];

// Fetch total customers
$sqlCustomers = "SELECT COUNT(*) AS total_customers FROM customers";
$resultCustomers = $conn->query($sqlCustomers);
$totalCustomers = $resultCustomers->fetch_assoc()['total_customers'];

// Fetch recent 5 orders
$sqlRecentOrders = "SELECT * FROM orders ORDER BY updated_at DESC LIMIT 5 ";
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

// Fetch Active Customers Online
// SQL query to join the tables and fetch customer names where status is Active
$sqlActiveCustomers = "SELECT customers.customer_id, customers.firstname, customers.lastname
FROM customer_active_sessions
JOIN customers ON customer_active_sessions.customer_id = customers.customer_id
WHERE customer_active_sessions.status = 'Active';
";

$resultActiveCustomers = $conn->query($sqlActiveCustomers);
$activeCustomers = [];
    if ($resultActiveCustomers->num_rows > 0) {
        // Output data of each row
        while($row = $resultActiveCustomers->fetch_assoc()) {
            $activeCustomers[] = $row;
        }
    } else {
       $activeCustomers[] = "No Active Customer";
    }
    // Output the result for debugging


// Calculate total inflow from previous day
$sqlTotalInflow = "SELECT SUM(total_amount) AS total_inflow FROM revenue WHERE DATE(updated_at) = CURDATE() AND status = 'Approved'";
// $sqlTotalInflow = "SELECT SUM(total_amount) AS total_inflow FROM revenue WHERE DATE(transaction_date) = CURDATE() - INTERVAL 1 DAY";
$resultTotalInflow = $conn->query($sqlTotalInflow);
$totalInflow = $resultTotalInflow->fetch_assoc()['total_inflow'];

$data = [
    "totalOrders" => $totalOrders,
    "totalCustomers" => $totalCustomers,
    "recentOrders" => $recentOrders,
    "topMenuItems" => $topMenuItems,
    "totalInflow" => $totalInflow,
    "pendingOrders" => $totalPendingOrders,
    "activeCustomers" => $activeCustomers
];

echo json_encode($data);

$conn->close();




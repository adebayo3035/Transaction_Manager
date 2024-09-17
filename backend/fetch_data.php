<?php
include 'config.php';
session_start();
// Check if the user is logged in and retrieve their role
if (isset($_SESSION['role']) && isset($_SESSION['unique_id'])) {
    $role = $_SESSION['role'];
    $userId = $_SESSION['unique_id'];
} else {
    // Redirect to login or handle unauthenticated users
    header('Location: admin_login.php');
    exit();
}

// Fetch total pending orders for today based on role
if ($role === 'Super Admin') {
    // Super Admin sees all pending orders for today
    $sqlPendingOrders = "SELECT COUNT(*) AS pending_orders FROM orders WHERE status = 'Pending'";
} else if ($role === 'Admin') {
    // Admin sees only their assigned pending orders for today
    $sqlPendingOrders = "SELECT COUNT(*) AS pending_orders FROM orders WHERE status = 'Pending' AND assigned_to = ?";
}

$stmt = $conn->prepare($sqlPendingOrders);

if ($role === 'Admin') {
    // Bind the admin's unique ID if the role is Admin
    $stmt->bind_param("i", $userId);
}

// Execute the query
$stmt->execute();
$resultOrders = $stmt->get_result();
$orderData = $resultOrders->fetch_assoc();
$totalPendingOrders = $orderData['pending_orders'];

// // Fetch total orders based on role
if ($role === 'Super Admin') {
    // Super Admin sees all orders
    $sqlOrders = "SELECT COUNT(*) AS total_orders FROM orders WHERE DATE(order_date) = CURDATE()";
} else if ($role === 'Admin') {
    // Admin sees only orders assigned to them
    $sqlOrders = "SELECT COUNT(*) AS total_orders FROM orders WHERE DATE(order_date) = CURDATE() AND assigned_to = ?";
}

$stmt = $conn->prepare($sqlOrders);

if ($role === 'Admin') {
    // Bind the admin's unique ID if the role is Admin
    $stmt->bind_param("i", $userId);
}

$stmt->execute();
$resultOrders = $stmt->get_result();
$totalOrders = $resultOrders->fetch_assoc()['total_orders'];


$sqlDrivers = "SELECT COUNT(*) AS total_drivers FROM driver";
$resultDrivers = $conn->query($sqlDrivers);
$totalDrivers = $resultDrivers->fetch_assoc()['total_drivers'];

// Fetch total customers
$sqlCustomers = "SELECT COUNT(*) AS total_customers FROM customers";
$resultCustomers = $conn->query($sqlCustomers);
$totalCustomers = $resultCustomers->fetch_assoc()['total_customers'];

// Base query to select recent 5 orders
$sqlRecentOrders = "
    SELECT 
        orders.order_id,
        orders.assigned_to,
        orders.approved_by,
        orders.order_date, 
        orders.total_amount, 
        orders.status, 
        orders.delivery_status, 
        orders.driver_id, 
        driver.firstname AS driver_firstname, 
        driver.lastname AS driver_lastname, 
        admin_tbl.firstname AS admin_firstname, 
        admin_tbl.lastname AS admin_lastname,
        admin_approver.firstname AS approver_firstname,
        admin_approver.lastname AS approver_lastname 
    FROM orders 
    LEFT JOIN driver ON orders.driver_id = id 
    LEFT JOIN admin_tbl ON orders.assigned_to = admin_tbl.unique_id
    LEFT JOIN admin_tbl AS admin_approver ON orders.approved_by = admin_approver.unique_id
    ";

// Modify query based on user role
if ($role === 'Admin') {
    // If Admin, filter based on 'assigned_to' column
    $sqlRecentOrders .= "WHERE orders.assigned_to = ? ";
}

$sqlRecentOrders .= "ORDER BY orders.updated_at DESC LIMIT 5";

// Prepare and execute the query
if ($role === 'Admin') {
    $stmt = $conn->prepare($sqlRecentOrders);
    $stmt->bind_param("i", $userId); // Use the admin's user ID
} else {
    // For Super Admin, no need for WHERE clause, so just execute the query
    $stmt = $conn->prepare($sqlRecentOrders);
}

$stmt->execute();
$resultRecentOrders = $stmt->get_result();

$recentOrders = [];

// Fetch the orders and store them in the $recentOrders array
while ($row = $resultRecentOrders->fetch_assoc()) {
    $recentOrders[] = $row;
}

    // Output the JSON data
    // echo json_encode($recentOrders);

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
       $activeCustomers = [];
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
    "activeCustomers" => $activeCustomers,
    "totalDrivers" => $totalDrivers 
];

echo json_encode($data);

$conn->close();




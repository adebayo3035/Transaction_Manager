<?php
include 'config.php';
session_start();

if (!isset($_SESSION['unique_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}

// $customerId = $_SESSION['customer_id'];
$adminId = $_SESSION['unique_id'];
$role = $_SESSION['role'];
if($role == "Admin"){
    // Updated query to join the orders table with the admin_tbl
    $query = "SELECT o.order_id, o.order_date, o.total_amount, o.status, a.firstname AS assigned_admin_firstname, a.lastname AS assigned_admin_lastname
        FROM orders o
        INNER JOIN admin_tbl a ON o.assigned_to = a.unique_id
        WHERE o.status = 'Pending' AND o.assigned_to = ?
        ORDER BY o.order_date DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $adminId);
}
else if($role = "Super Admin"){
    $query = "SELECT 
    orders.order_id, 
    orders.order_date, 
    orders.total_amount, 
    orders.status, 
    orders.assigned_to, 
    admin_tbl.firstname AS assigned_admin_firstname, 
    admin_tbl.lastname AS assigned_admin_lastname
FROM orders
LEFT JOIN admin_tbl ON orders.assigned_to = admin_tbl.unique_id
WHERE orders.status = 'Pending'
ORDER BY orders.order_date DESC;
";
    $stmt = $conn->prepare($query);
// $stmt->bind_param("i", $adminId);
}
$stmt->execute();
$result = $stmt->get_result();

$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode(["success" => true, "orders" => $orders]);


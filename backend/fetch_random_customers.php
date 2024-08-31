<?php
header('Content-Type: application/json');

// Include database connection file
include('config.php');

// Fetch random customer names
$query = "SELECT firstname, lastname FROM customers ORDER BY RAND() LIMIT 3";
$result = $conn->query($query);

$customer_names = [];
while ($row = $result->fetch_assoc()) {
    $customer_names[] = $row;
}

echo json_encode(["success" => true, "customer_names" => $customer_names]);


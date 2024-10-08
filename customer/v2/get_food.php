<?php
// get_food_items.php
include 'config.php';
session_start();

if (!isset($_SESSION['customer_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}
$sql = "SELECT food_id, food_name, food_price FROM food WHERE availability_status != 0 AND available_quantity != 0;";
$result = mysqli_query($conn, $sql);

$foodItems = [];
while ($row = mysqli_fetch_assoc($result)) {
    $foodItems[] = $row;
}

mysqli_close($conn);

header('Content-Type: application/json');
echo json_encode($foodItems);


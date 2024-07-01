<?php
header('Content-Type: application/json');
include ('config.php');

$data = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die(json_encode(["success" => false, "message" => "Invalid JSON input"]));
}

$id = $data['food_id'];
$name = $data['name'];
$description = $data['description'];
$price = $data['price'];
$quantity = $data['quantity'];
$available = $data['available'];

// Check for duplicate food names
$sql = "SELECT COUNT(*) as count FROM food WHERE food_name = ? AND food_id != ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $name, $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if ($row['count'] > 0) {
    die(json_encode(["success" => false, "message" => "Food with the same name already exists."]));
}

$stmt->close();

// Update the food item
$sql = "UPDATE food SET food_name = ?, food_description = ?, food_price = ?, available_quantity = ?, availability_status = ? WHERE food_id = ?";
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    die(json_encode(["success" => false, "message" => "Prepare statement failed: " . $conn->error]));
}

$stmt->bind_param("ssdisi", $name, $description, $price, $quantity, $available, $id);

if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to update food item: " . $stmt->error]);
}

$stmt->close();
$conn->close();


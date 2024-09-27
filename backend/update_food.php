<?php
header('Content-Type: application/json');
include ('config.php');

$data = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die(json_encode(["success" => false, "message" => "Invalid JSON input"]));
}

$id = $data['food_id'];
$name = $data['food_name'];
$description = $data['food_description'];
$price = $data['food_price'];
$quantity = $data['food_quantity'];
$available = $data['available_status'];

// check for empty data
if (
    empty($id) || empty($name) || empty($description) ) {
   
    echo json_encode(["success" => false, "message" => 'Please fill in all required fields.']);
    exit();
}
if ((!isset($quantity)) || (!isset($price)) || (!isset($available))) {
    echo json_encode(["success" => false, "message" => 'Please fill in all required fields.']);
    exit();
}
// ensure price only contains number and decimals only
if (!preg_match('/^\d+(\.\d{1,2})?$/', $price)) {
    echo json_encode(["success" => false, "message" => 'Invalid Price amount']);
    exit();
} 
// ensure quantity contains number only
if (!preg_match('/^\d+$/', $quantity)) {
    echo json_encode(["success" => false, "message" => 'Invalid Quantity']);
    exit();
} 
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


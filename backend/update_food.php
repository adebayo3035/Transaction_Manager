<?php
header('Content-Type: application/json');
include ('config.php');

// Decode the JSON input
$data = json_decode(file_get_contents('php://input'), true);

// Check for invalid JSON input
if (json_last_error() !== JSON_ERROR_NONE) {
    die(json_encode(["success" => false, "message" => "Invalid JSON input"]));
}

// Extract and sanitize input data
$id = $data['food_id'];
$name = $data['food_name'];
$description = $data['food_description'];
$price = $data['food_price'];
$quantity = $data['food_quantity'];
$available = $data['available_status'];

// Check for required fields
if (empty($id) || empty($name) || empty($description)) {
    echo json_encode(["success" => false, "message" => 'Please fill in all required fields.']);
    exit();
}
if (!isset($quantity) || !isset($price) || !isset($available)) {
    echo json_encode(["success" => false, "message" => 'Please fill in all required fields.']);
    exit();
}

// Ensure price is valid (number with up to two decimals)
if (!preg_match('/^\d+(\.\d{1,2})?$/', $price)) {
    echo json_encode(["success" => false, "message" => 'Invalid Price amount']);
    exit();
}

// Ensure quantity is a whole number
if (!preg_match('/^\d+$/', $quantity)) {
    echo json_encode(["success" => false, "message" => 'Invalid Quantity']);
    exit();
}

// Check if food with the given food_id exists
$sql = "SELECT * FROM food WHERE food_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

// If no record found, return an error
if ($result->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "Food with the given ID does not exist."]);
    exit();
}
$stmt->close();

// Check for duplicate food names (for other food items with the same name)
$sql = "SELECT COUNT(*) as count FROM food WHERE food_name = ? AND food_id != ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $name, $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if ($row['count'] > 0) {
    echo json_encode(["success" => false, "message" => "Food with the same name already exists."]);
    exit();
}

$stmt->close();

// Update the food item
$sql = "UPDATE food SET food_name = ?, food_description = ?, food_price = ?, available_quantity = ?, availability_status = ? WHERE food_id = ?";
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    echo json_encode(["success" => false, "message" => "Prepare statement failed: " . $conn->error]);
    exit();
}

$stmt->bind_param("ssdisi", $name, $description, $price, $quantity, $available, $id);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Food Record has been successfully updated"]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to update food item: " . $stmt->error]);
}

$stmt->close();
$conn->close();

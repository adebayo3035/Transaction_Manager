<?php
header('Content-Type: application/json');
include('config.php');

// Decode the JSON input
$data = json_decode(file_get_contents('php://input'), true);

// Check for invalid JSON input
if (json_last_error() !== JSON_ERROR_NONE) {
    logActivity("Invalid JSON input received: " . file_get_contents('php://input'));
    die(json_encode(["success" => false, "message" => "Invalid JSON input"]));
}

logActivity("Received food update request: " . json_encode($data));

// Extract and sanitize input data
$id = $data['food_id'];
$name = $data['food_name'];
$description = $data['food_description'];
$price = $data['food_price'];
$quantity = $data['food_quantity'];
$available = $data['available_status'];

// Check for required fields
if (empty($id) || empty($name) || empty($description)) {
    logActivity("Validation failed: Required fields missing. ID: $id, Name: $name, Description: $description");
    echo json_encode(["success" => false, "message" => 'Please fill in all required fields.']);
    exit();
}
if (!isset($quantity) || !isset($price) || !isset($available)) {
    logActivity("Validation failed: Required numeric fields missing.");
    echo json_encode(["success" => false, "message" => 'Please fill in all required fields.']);
    exit();
}

// Validate price
if (!preg_match('/^\d+(\.\d{1,2})?$/', $price)) {
    logActivity("Validation failed: Invalid price format ($price) for food ID: $id");
    echo json_encode(["success" => false, "message" => 'Invalid Price amount']);
    exit();
}

// Validate quantity
if (!preg_match('/^\d+$/', $quantity)) {
    logActivity("Validation failed: Invalid quantity format ($quantity) for food ID: $id");
    echo json_encode(["success" => false, "message" => 'Invalid Quantity']);
    exit();
}

// Check if food item exists
$sql = "SELECT * FROM food WHERE food_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    logActivity("Food update failed: Food ID $id does not exist.");
    echo json_encode(["success" => false, "message" => "Food with the given ID does not exist."]);
    exit();
}
logActivity("Food item exists. Proceeding to check for duplicate name.");
$stmt->close();

// Check for duplicate name
$sql = "SELECT COUNT(*) as count FROM food WHERE food_name = ? AND food_id != ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $name, $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if ($row['count'] > 0) {
    logActivity("Duplicate food name detected: '$name' already exists for another item.");
    echo json_encode(["success" => false, "message" => "Food with the same name already exists."]);
    exit();
}
logActivity("No duplicate name found. Proceeding to update food item.");
$stmt->close();

// Update the food item
$sql = "UPDATE food SET food_name = ?, food_description = ?, food_price = ?, available_quantity = ?, availability_status = ? WHERE food_id = ?";
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    logActivity("Prepare statement failed: " . $conn->error);
    echo json_encode(["success" => false, "message" => "Prepare statement failed: " . $conn->error]);
    exit();
}

$stmt->bind_param("ssdisi", $name, $description, $price, $quantity, $available, $id);

if ($stmt->execute()) {
    logActivity("Food item (ID: $id) updated successfully. New values - Name: $name, Price: $price, Quantity: $quantity, Available: $available");
    echo json_encode(["success" => true, "message" => "Food Record has been successfully updated"]);
} else {
    logActivity("Failed to update food item (ID: $id). Error: " . $stmt->error);
    echo json_encode(["success" => false, "message" => "Failed to update food item: " . $stmt->error]);
}

$stmt->close();
$conn->close();
logActivity("Database connection closed after update process.");

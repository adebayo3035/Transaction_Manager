<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

include("config.php");
include('restriction_checker.php');

// Get and sanitize input data
$data = json_decode(file_get_contents('php://input'), true);
$food_name = trim($data['food_name'] ?? '');
$food_description = trim($data['food_description'] ?? '');
$food_price = trim($data['food_price'] ?? '');
$food_quantity = trim($data['food_quantity'] ?? '');
$available = trim($data['available_status'] ?? '');

// Validate required fields
if (empty($food_name) || empty($food_description) || !isset($food_quantity) || !isset($food_price) || !isset($available)) {
    echo json_encode(["success" => false, "message" => 'Please fill in all required fields.']);
    exit();
}

// Validate price (ensure it only contains numbers and up to 2 decimal places)
if (!preg_match('/^\d+(\.\d{1,2})?$/', $food_price)) {
    echo json_encode(["success" => false, "message" => 'Invalid price amount.']);
    exit();
}

// Validate quantity (ensure it is a whole number)
if (!preg_match('/^\d+$/', $food_quantity)) {
    echo json_encode(["success" => false, "message" => 'Invalid quantity.']);
    exit();
}

// Function to calculate the similarity percentage between two strings
function levenshteinPercentage($str1, $str2) {
    $lev = levenshtein($str1, $str2);
    $maxLen = max(strlen($str1), strlen($str2));
    return ($maxLen == 0) ? 100 : (1 - $lev / $maxLen) * 100;
}

// Function to check if a similar food name exists
function isSimilarFoodExists($conn, $newFoodName, $threshold = 50) {
    $stmt = $conn->prepare("SELECT food_name FROM food WHERE food_name LIKE ?");
    $searchTerm = '%' . $newFoodName . '%';
    $stmt->bind_param("s", $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        if (levenshteinPercentage($row['food_name'], $newFoodName) >= $threshold) {
            $stmt->close();
            return true;
        }
    }
    
    $stmt->close();
    return false;
}

// Check for similar food name
if (isSimilarFoodExists($conn, $food_name)) {
    echo json_encode(["success" => false, "message" => "A similar food name exists. Please try another name."]);
    exit();
}

// Prepare the SQL statement for inserting the new food item
$stmt = $conn->prepare("INSERT INTO food (food_name, food_description, food_price, available_quantity, availability_status) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("ssdis", $food_name, $food_description, $food_price, $food_quantity, $available);

// Execute the statement and return the result
if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Food item added successfully."]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to add food item: " . $stmt->error]);
}

// Clean up
$stmt->close();
$conn->close();

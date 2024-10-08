<?php
include_once('config.php');
session_start();
header('Content-Type: application/json');
// Check if the user is authenticated
$user_id = $_SESSION['unique_id'] ?? null; // Assuming user ID is stored in session
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated.']);
    exit;
}

// Check if the user has sufficient permissions (Super Admin)
$staff_role = $_SESSION['role'] ?? null;
if ($staff_role !== "Super Admin") {
    echo json_encode(["success" => false, "message" => "You do not have permission to delete this item."]);
    exit();
}

// Get the JSON data from the request body
$data = json_decode(file_get_contents("php://input"), true);

// Validate the JSON input
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(["success" => false, "message" => "Invalid JSON input."]);
    exit();
}

// Check if food_id is provided
if (!isset($data['food_id']) || empty($data['food_id'])) {
    echo json_encode(["success" => false, "message" => "Food ID is required."]);
    exit();
}

$foodId = $data['food_id'];

// First, check if the food ID exists in the database
$checkSql = "SELECT * FROM food WHERE food_id = ?";
$stmtCheck = $conn->prepare($checkSql);
$stmtCheck->bind_param("i", $foodId);
$stmtCheck->execute();
$resultCheck = $stmtCheck->get_result();

// If no matching food ID is found
if ($resultCheck->num_rows == 0) {
    echo json_encode(["success" => false, "message" => "Food item not found."]);
    $stmtCheck->close();
    $conn->close();
    exit();
}

// Proceed with deletion if the food ID exists
$stmtCheck->close();

// Prepare SQL query to delete the food item
$deleteSql = "DELETE FROM food WHERE food_id = ?";
$stmtDelete = $conn->prepare($deleteSql);

// Check if the prepared statement was successful
if ($stmtDelete === false) {
    echo json_encode(["success" => false, "message" => "Failed to prepare the statement."]);
    exit();
}

// Bind the parameter and execute the query
$stmtDelete->bind_param("i", $foodId);
if ($stmtDelete->execute()) {
    // Success response after deletion
    echo json_encode(["success" => true, "message" => "Food has been successfully deleted."]);
} else {
    // Error handling for failed execution
    echo json_encode(["success" => false, "message" => "Failed to delete food item."]);
}

// Close the statement and connection
$stmtDelete->close();
$conn->close();

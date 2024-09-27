<?php
include_once ('config.php');
session_start();
$user_id = $_SESSION['unique_id'] ?? null; // Assuming user ID is stored in session
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated.']);
    exit;
}

// Get the JSON data from the request body
$data = json_decode(file_get_contents("php://input"), true);

$staff_role = $_SESSION['role'];
if($staff_role !== "Super Admin"){
    echo json_encode(["success" => false, "message" => "You do not have permission to Delete."]);
    exit();
}
if (isset($data['food_id'])) {
    $foodId = $data['food_id'];

    // Prepare SQL query to delete the driver
    $deleteSql = "DELETE FROM food WHERE food_id = ?";
    $stmt = $conn->prepare($deleteSql);
    $stmt->bind_param("i", $foodId);
    
    if ($stmt->execute()) {
        // Return success response
        echo json_encode(["success" => true, "message" => "Food has been Successfully Deleted."]);
    } else {
        // Return error response
        echo json_encode(["success" => false, "message" => "Failed to delete Food Item."]);
    }

    // Close the statement
    $stmt->close();
} else {
    // Return error response if the ID is not provided
    echo json_encode(["success" => false, "message" => "Food ID is required."]);
}

// Close the database connection
$conn->close();






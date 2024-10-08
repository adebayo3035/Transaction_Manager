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
if (isset($data['revenue_id'])) {
    $revenueId = $data['revenue_id'];

    // Prepare SQL query to delete the driver
    $deleteSql = "DELETE FROM revenue_types WHERE revenue_type_id = ?";
    $stmt = $conn->prepare($deleteSql);
    $stmt->bind_param("i", $revenueId);
    
    if ($stmt->execute()) {
        // Return success response
        echo json_encode(["success" => true, "message" => "Revenue Type has been Successfully Deleted."]);
    } else {
        // Return error response
        echo json_encode(["success" => false, "message" => "Failed to delete Revenue Type."]);
        exit();
    }

    // Close the statement
    $stmt->close();
} else {
    // Return error response if the ID is not provided
    echo json_encode(["success" => false, "message" => "Revenue ID is required."]);
    exit();
}

// Close the database connection
$conn->close();






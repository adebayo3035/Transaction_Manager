<?php
// Include database connection
include('config.php'); // Replace with your actual database connection file
session_start();
if (!isset($_SESSION['unique_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}
$loggedInUserRole = $_SESSION['role'];
$logged_in_user = $_SESSION['unique_id'];
if($loggedInUserRole !== "Super Admin"){
    echo json_encode(["success" => false, "message" => "Access Denied."]);
    exit();
}

// Get the JSON data from the request body
$data = json_decode(file_get_contents("php://input"), true);

if (isset($data['unit_id'])) {
    $unitId = $data['unit_id'];
    $unit_name = $data['unit_name'];
    $group_id = $data['group_id'];

    // Validate required fields
    if (empty($unitId) || empty($group_id) || empty($unit_name)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit;
    }
   

    // Check for Name Duplicates
    $selectRest = "SELECT COUNT(*) AS unit_count FROM unit WHERE unit_name = ? and unit_id != ?";
    $stmt = $conn->prepare($selectRest);
    $stmt->bind_param("si", $unit_name, $unitId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row['unit_count'] > 0) {
        die(json_encode(["success" => false, "message" => "Unit with the same name already exists."]));
    }
    $stmt->close();
    // Prepare SQL query to update the driver
    $sql = "UPDATE unit SET unit_name = ?, group_id = ? WHERE unit_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sii", $unit_name, $group_id, $unitId);

    // Execute the statement
    if ($stmt->execute()) {
        // Return success response
        echo json_encode(["success" => true, "message" => "Unit Record has been Successfully Updated."]);
    } else {
        // Return error response
        echo json_encode(["success" => false, "message" => "Failed to update Unit Details."]);
    }

    // Close the statement
    $stmt->close();
} else {
    // Return error response if the ID is not provided
    echo json_encode(["success" => false, "message" => "Unit ID is missing."]);
}

// Close the database connection
$conn->close();

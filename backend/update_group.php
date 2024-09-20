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

if (isset($data['group_id'])) {
    $groupId = $data['group_id'];
    $group_name = $data['group_name'];

    // Validate required fields
    if (empty($groupId) || empty($group_name)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit;
    }
   

    // Check for Name Duplicates
    $selectRest = "SELECT COUNT(*) AS group_count FROM groups WHERE group_name = ? and group_id != ?";
    $stmt = $conn->prepare($selectRest);
    $stmt->bind_param("si", $group_name, $groupId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row['group_count'] > 0) {
        die(json_encode(["success" => false, "message" => "Group with the same name already exists."]));
    }
    $stmt->close();
    // Prepare SQL query to update the driver
    $sql = "UPDATE groups SET group_name = ? WHERE group_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $group_name, $groupId);

    // Execute the statement
    if ($stmt->execute()) {
        // Return success response
        echo json_encode(["success" => true, "message" => "Group Record has been Successfully Updated."]);
    } else {
        // Return error response
        echo json_encode(["success" => false, "message" => "Failed to update Group Details."]);
    }

    // Close the statement
    $stmt->close();
} else {
    // Return error response if the ID is not provided
    echo json_encode(["success" => false, "message" => "Group ID is missing."]);
}

// Close the database connection
$conn->close();

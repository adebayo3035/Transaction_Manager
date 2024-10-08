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

if (isset($data['revenue_id'])) {
    $revenueId = $data['revenue_id'];
    $revenue_name = $data['revenue_name'];
    $revenue_description = $data['revenue_description'];

    // Validate required fields
    if (empty($revenueId) || empty($revenue_name)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit;
    }
    // Check for Name Duplicates
    $selectRest = "SELECT COUNT(*) AS revenue_count FROM revenue_types WHERE revenue_type_name = ? and revenue_type_id != ?";
    $stmt = $conn->prepare($selectRest);
    $stmt->bind_param("si", $revenue_name, $revenueId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row['revenue_count'] > 0) {
        die(json_encode(["success" => false, "message" => "Revenue with the same name already exists."]));
    }
    $stmt->close();
    // Prepare SQL query to update the driver
    $sql = "UPDATE revenue_types SET revenue_type_name = ?, revenue_type_description = ? WHERE revenue_type_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $revenue_name, $revenue_description, $revenueId);

    // Execute the statement
    if ($stmt->execute()) {
        // Return success response
        echo json_encode(["success" => true, "message" => "Revenue Record has been Successfully Updated."]);
    } else {
        // Return error response
        echo json_encode(["success" => false, "message" => "Failed to update Revenue Details."]);
    }

    // Close the statement
    $stmt->close();
} else {
    // Return error response if the ID is not provided
    echo json_encode(["success" => false, "message" => "Revenue ID is missing."]);
}

// Close the database connection
$conn->close();

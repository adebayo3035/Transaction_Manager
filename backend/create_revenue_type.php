<?php
header('Content-Type: application/json');
include_once "config.php";
session_start();
if (!isset($_SESSION['unique_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}
if($_SESSION['role'] !== "Super Admin"){
    echo json_encode(["success" => false, "message" => "Unauthorized access. Contact your Superior."]);
    exit();
}
$revenue_type = mysqli_real_escape_string($conn, $_POST['revenue_type_name']);
$revenue_description = mysqli_real_escape_string($conn, $_POST['revenue_description']);
// Prepare the SQL statement
$sql = "SELECT * FROM revenue_types WHERE revenue_type_name LIKE ?";
$stmt = $conn->prepare($sql);

// Check if the statement was prepared successfully
if ($stmt === false) {
    die("Error preparing the statement: " . $conn->error);
}

// Add wildcards to search for partial matches (if needed) and bind the parameter
$revenue_type_like = '%' . $revenue_type . '%';
$stmt->bind_param("s", $revenue_type_like);

// Execute the query
$stmt->execute();

// Get the result
$result = $stmt->get_result();

// Check if any rows were returned
if ($result->num_rows > 0) {
    // Record exists
    echo json_encode(['success' => false, 'message' => "A record with the revenue type name '{$revenue_type}' already exists."]);
        exit();
} 
// SQL to insert new revenue type
$sql = "INSERT INTO revenue_types (revenue_type_name, revenue_type_description) VALUES (?, ?)";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("ss", $revenue_type, $revenue_description);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => "Revenue type added Successfully."]);
    } else {
        // echo "Error: " . $conn->error;
        echo json_encode(['success' => false, 'message' => 'Something went wrong, please try again.'. $conn->error]);
        exit();
    }

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Something went wrong, please try again.'. $conn->error]);
    exit();
}

$conn->close();


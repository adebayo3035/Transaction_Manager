<?php
include_once ('config.php');
include('restriction_checker.php');

// Get the JSON data from the request body
$data = json_decode(file_get_contents("php://input"), true);

$staff_role = $_SESSION['role'];
if($staff_role !== "Super Admin"){
    echo json_encode(["success" => false, "message" => "You do not have permission to Delete."]);
    exit();
}
if (isset($data['customer_id'])) {
    $customerId = $data['customer_id'];

    // Prepare SQL query to get the photo filename
    $selectSql = "SELECT photo FROM customers WHERE customer_id = ?";
    $stmt = $conn->prepare($selectSql);
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $stmt->bind_result($photoFilename);
    $stmt->fetch();
    $stmt->close();

    // Prepare SQL query to delete the driver
    $deleteSql = "DELETE FROM customers WHERE customer_id = ?";
    $stmt = $conn->prepare($deleteSql);
    $stmt->bind_param("i", $customerId);
    
    if ($stmt->execute()) {
        // If there's a photo, delete it from the folder
        if ($photoFilename) {
            $photoPath = '../backend/customer_photos/' . $photoFilename;
            if (file_exists($photoPath)) {
                unlink($photoPath); // Delete the file
            }
        }
        // Return success response
        echo json_encode(["success" => true, "message" => "Customer has been Successfully Deleted."]);
    } else {
        // Return error response
        echo json_encode(["success" => false, "message" => "Failed to delete Customer."]);
    }

    // Close the statement
    $stmt->close();
} else {
    // Return error response if the ID is not provided
    echo json_encode(["success" => false, "message" => "Customer ID is required."]);
}

// Close the database connection
$conn->close();






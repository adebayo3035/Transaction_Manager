<?php
// Include database connection
include('config.php'); // Replace with your actual database connection file

// Get the JSON data from the request body
$data = json_decode(file_get_contents("php://input"), true);

if (isset($data['id'])) {
    $driverId = $data['id'];

    // Prepare SQL query to get the photo filename
    $selectSql = "SELECT photo FROM driver WHERE id = ?";
    $stmt = $conn->prepare($selectSql);
    $stmt->bind_param("i", $driverId);
    $stmt->execute();
    $stmt->bind_result($photoFilename);
    $stmt->fetch();
    $stmt->close();

    // Prepare SQL query to delete the driver
    $deleteSql = "DELETE FROM driver WHERE id = ?";
    $stmt = $conn->prepare($deleteSql);
    $stmt->bind_param("i", $driverId);
    
    if ($stmt->execute()) {
        // If there's a photo, delete it from the folder
        if ($photoFilename) {
            $photoPath = '../backend/driver/driver_photos/' . $photoFilename;
            if (file_exists($photoPath)) {
                unlink($photoPath); // Delete the file
            }
        }
        // Return success response
        echo json_encode(["success" => true, "message" => "Driver deleted successfully."]);
    } else {
        // Return error response
        echo json_encode(["success" => false, "message" => "Failed to delete driver."]);
    }

    // Close the statement
    $stmt->close();
} else {
    // Return error response if the ID is not provided
    echo json_encode(["success" => false, "message" => "Driver ID is required."]);
}

// Close the database connection
$conn->close();

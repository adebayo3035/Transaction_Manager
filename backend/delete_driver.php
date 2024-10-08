<?php
// Include database connection
include('config.php'); // Replace with your actual database connection file
session_start();
// Get the JSON data from the request body
$data = json_decode(file_get_contents("php://input"), true);

$staff_role = $_SESSION['role'];

if ($staff_role !== "Super Admin") {
    echo json_encode(["success" => false, "message" => "You do not have permission to Delete."]);
    exit();
}

if (isset($data['id'])) {
    $driverId = $data['id'];

    // Check Driver's Status before Deleting: Delete only when Driver is available
    $sql = "SELECT status FROM driver WHERE id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $driverId);
        $stmt->execute();
        $stmt->bind_result($driver_status);
        $stmt->fetch();
        $stmt->close();

        if ($driver_status !== "Available") {
            echo json_encode(["success" => false, "message" => "Driver is Currently Unavailable. Please try again."]);
            exit();
        }
    } else {
        echo json_encode(["success" => false, "message" => "Prepare failed: " . $conn->error]);
        exit();
    }

    // Reset the session of the driver from the system
    // Step 1: Retrieve the session_id using $driver_id
    $stmt = $conn->prepare("SELECT session_id FROM driver_active_sessions WHERE driver_id = ?");
    $stmt->bind_param("i", $driverId);
    $stmt->execute();
    $stmt->bind_result($session_id);
    $stmt->fetch();
    $stmt->close();

    // Update the status column to Inactive
    if ($session_id) {
        // Step 2: Update the status to 'Inactive'
        $stmt = $conn->prepare("UPDATE driver_active_sessions SET status = 'Inactive' WHERE session_id = ?");
        $stmt->bind_param("s", $session_id);
        $stmt->execute();
        $stmt->close();
    } else {
        echo json_encode(["success" => false, "message" => "Something went wrong, Please try again."]);
        exit();
    }

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

<?php
// Include database connection
include('config.php'); // Replace with your actual database connection file

// Get the JSON data from the request body
$data = json_decode(file_get_contents("php://input"), true);

if (isset($data['id'])) {
    $driverId = $data['id'];
    $firstname = $data['firstname'];
    $lastname = $data['lastname'];
    $email = $data['email'];
    $phone_number = $data['phone_number'];
    $gender = $data['gender'];
    $address = $data['address'];
    $vehicle_type = $data['vehicle_type'];
    $restriction = $data['restriction'];

    // Validate required fields
    if (empty($firstname) || empty($lastname) || empty($email) || empty($phone_number) || empty($gender) || empty($vehicle_type) || empty($address)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit;
    }

    // Email validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid E-mail address.']);
        exit();
    }

    // Phone number validation
    if (!preg_match('/^\d{11}$/', $phone_number)) {
        echo json_encode(['success' => false, 'message' => 'Please input a valid Phone Number.']);
        exit();
    }

    // Restriction validation: Only 0 or 1 are allowed
    if (!in_array($restriction, [0, 1])) {
        echo json_encode(['success' => false, 'message' => 'Invalid restriction value. Must be 0 or 1.']);
        exit();
    }

    // Gender validation: Only 'Male', 'Female', or 'Other' are allowed
    $allowedGenders = ['Male', 'Female', 'Other'];
    if (!in_array($gender, $allowedGenders)) {
        echo json_encode(['success' => false, 'message' => 'Invalid gender. Must be Male, Female, or Other.']);
        exit();
    }

    // Vehicle type validation: Only 'Bicycle', 'Bike', or 'Car' are allowed
    $allowedVehicleTypes = ['Bicycle', 'Bike', 'Car'];
    if (!in_array($vehicle_type, $allowedVehicleTypes)) {
        echo json_encode(['success' => false, 'message' => 'Invalid vehicle type. Must be Bicycle, Bike, or Car.']);
        exit();
    }

    // Check for duplicate phone number or email, excluding the current driver ID
    $checkQuery = "SELECT id, phone_number, email FROM driver WHERE (phone_number = ? OR email = ?) AND id != ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("ssi", $phone_number, $email, $driverId);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $existingPhone, $existingEmail);
        $stmt->fetch();

        if ($existingPhone == $phone_number) {
            echo json_encode(['success' => false, 'message' => 'Phone number already exists for another driver.']);
            exit();
        }

        if ($existingEmail == $email) {
            echo json_encode(['success' => false, 'message' => 'Email address already exists for another driver.']);
            exit();
        }
    }

    // Check driver's current status
    $statusQuery = "SELECT status FROM driver WHERE id = ?";
    $stmt = $conn->prepare($statusQuery);
    $stmt->bind_param("i", $driverId);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($status);
        $stmt->fetch();

        // Fail update if restriction is requested and driver is currently unavailable due to an assigned order
        if ($status == 'Not Available' && $restriction == "1") {
            echo json_encode(['success' => false, 'message' => 'Driver is currently assigned to an Order and cannot be Restricted.']);
            exit();
        }
    }

    // Prepare SQL query to update the driver
    $sql = "UPDATE driver SET 
                firstname = ?, 
                lastname = ?, 
                email = ?, 
                phone_number = ?, 
                gender = ?, 
                address = ?, 
                vehicle_type = ?, 
                restriction = ? 
            WHERE id = ?";

    // Initialize the prepared statement
    $stmt = $conn->prepare($sql);

    // Bind the parameters
    $stmt->bind_param("ssssssssi", $firstname, $lastname, $email, $phone_number, $gender, $address, $vehicle_type, $restriction, $driverId);

    // Execute the statement
    if ($stmt->execute()) {
        // Return success response
        echo json_encode(["success" => true, "message" => "Driver updated successfully."]);
    } else {
        // Return error response with more detailed info
        echo json_encode(["success" => false, "message" => "Failed to update driver. Error: " . $stmt->error]);
    }

    // Close the statement
    $stmt->close();
} else {
    // Return error response if the ID is not provided
    echo json_encode(["success" => false, "message" => "Driver ID is required."]);
}

// Close the database connection
$conn->close();

<?php
// Include database connection
include('config.php'); // Replace with your actual database connection file
session_start();

// Get the JSON data from the request body
$data = json_decode(file_get_contents("php://input"), true);

if (isset($data['id'])) {
    $driverId = $data['id'];
    $email = $data['email'];
    $phone_number = $data['phone_number'];
    $gender = $data['gender'];
    $address = $data['address'];
    $vehicle_type = $data['vehicle_type'];
    $secret_question = $data['secret_question'];
    $secret_answer = $data['secret_answer'];
    $encrypted_answer = md5($secret_answer);

    // // Validate required fields
    // if (empty($firstname) || empty($lastname) || empty($email) || empty($phone_number) || empty($license_number) || empty($gender) || empty($vehicle_type) || empty($address) || empty($restriction) ) {
    //     echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
    //     exit;
    // }

    // Validate Driver Unique Identifier
    if($driverId !== $_SESSION['driver_id']){
        echo json_encode(['success' => false, 'message' => 'Error Validating Driver Identity.']);
        exit();
    }
    // Email validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid E-mail address.']);
        exit();
    }
    if (!preg_match('/^\d{11}$/', $phone_number)) {
        echo json_encode(['success' => false, 'message' => 'Please input a valid Phone Number.']);
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
    // Prepare SQL query to update the driver
    $sql = "UPDATE driver SET 
                email = ?, 
                phone_number = ?, 
                gender = ?, 
                address = ?, 
                vehicle_type = ?, 
                secret_question = ?,
                secret_answer = ? 
            WHERE id = ?";

    // Initialize the prepared statement
    $stmt = $conn->prepare($sql);

    // Bind the parameters
    $stmt->bind_param("sssssssi", $email, $phone_number, $gender, $address, $vehicle_type, $secret_question, $encrypted_answer, $driverId);

    // Execute the statement
    if ($stmt->execute()) {
        // Return success response
        echo json_encode(["success" => true, "message" => "Your Record has been Successfully Updated."]);
    } else {
        // Return error response
        echo json_encode(["success" => false, "message" => "Failed to update driver record."]);
    }

    // Close the statement
    $stmt->close();
} else {
    // Return error response if the ID is not provided
    echo json_encode(["success" => false, "message" => "Driver ID is required."]);
}

// Close the database connection
$conn->close();


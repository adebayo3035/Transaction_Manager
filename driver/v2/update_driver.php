<?php
// Include database connection
include('config.php'); // Replace with your actual database connection file
session_start();

// Set response header
header('Content-Type: application/json');
$driver_Id = $_SESSION['driver_id'];
checkDriverSession($driver_Id);
logActivity("Session validated successfully for Driver ID: $driver_Id.");

// Get the JSON data from the request body
$data = json_decode(file_get_contents("php://input"), true);

if (isset($data['id'])) {
    $driverId = $data['id'];
    $email = $data['email'];
    $phone_number = $data['phone_number'];
    $gender = $data['gender'];
    $address = $data['address'];
    $vehicle_type = $data['vehicle_type'];
    $vehicle_type_others = $data['vehicle_type_others'] ?? NULL;
    $secret_answer = $data['secret_answer'];
    $encrypted_answer = md5($secret_answer);

    logActivity("Driver update request received for ID: $driver_Id");

    logActivity("Processing Driver Update requestfor driver ID: $driverId.");

    // Validate required fields
    if (
        empty($email) || empty($phone_number) || empty($gender) || empty($address) || empty($vehicle_type) ||
        ($vehicle_type === "Others" && empty($vehicle_type_others))
    ) {
        logActivity("Validation failed: Missing required fields for driver ID: $driverId");
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit;
    }

    // Validate Driver Unique Identifier
    if ($driverId !== $_SESSION['driver_id']) {
        logActivity("Driver identity validation failed for ID: $driverId");
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Error Validating Driver Identity.']);
        exit();
    }

    // Email validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        logActivity("Invalid email format provided by driver ID: $driverId");
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid E-mail address.']);
        exit();
    }

    // Phone number validation
    if (!preg_match('/^\d{11}$/', $phone_number)) {
        logActivity("Invalid phone number format provided by driver ID: $driverId");
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please input a valid Phone Number.']);
        exit();
    }

    // Check for duplicate phone number or email, excluding the current driver ID
    $checkQuery = "SELECT id FROM driver WHERE (phone_number = ? OR email = ?) AND id != ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("ssi", $phone_number, $email, $driverId);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        logActivity("Duplicate email or phone number detected for driver ID: $driverId");
        $stmt->close();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Phone number or email already exists for another driver.']);
        exit();
    }

    // Fetch the secret answer for the current driver
    $secretCheckQuery = "SELECT secret_answer FROM driver WHERE id = ?";
    $stmt = $conn->prepare($secretCheckQuery);
    $stmt->bind_param("i", $driverId);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($stored_secret_answer);
        $stmt->fetch();
        $stmt->close();

        // Validate secret answer
        if ($encrypted_answer !== $stored_secret_answer) {
            logActivity("Account validation failed due to incorrect secret answer for driver ID: $driverId");
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Account Validation Failed.']);
            exit();
        }
    }

    // Check if vehicle type is "Others" and ensure $vehicle_type_others is not null
    if ($vehicle_type === "Others") {
        if (empty($vehicle_type_others)) {
            logActivity("Vehicle type validation failed for driver ID: $driverId");
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Please specify other vehicle type.']);
            exit();
        }
        $vehicle_type = $vehicle_type_others;
    }

    // Prepare SQL query to update the driver
    $sql = "UPDATE driver SET 
                email = ?, 
                phone_number = ?, 
                gender = ?, 
                address = ?, 
                vehicle_type = ?, 
                secret_answer = ? 
            WHERE id = ?";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        logActivity("Failed to prepare update statement for driver ID: $driverId");
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to prepare the SQL statement.']);
        exit();
    }

    // Bind parameters and execute the update
    $stmt->bind_param("ssssssi", $email, $phone_number, $gender, $address, $vehicle_type, $encrypted_answer, $driverId);
    if ($stmt->execute()) {
        logActivity("Driver record successfully updated for ID: $driverId");
        http_response_code(200);
        echo json_encode(["success" => true, "message" => "Your Record has been Successfully Updated."]);
    } else {
        logActivity("Failed to update driver record for ID: $driverId");
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Failed to update driver record."]);
    }

    $stmt->close();
} else {
    logActivity("Driver update request received with missing driver ID.");
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Driver ID is required."]);
}

// Close the database connection
$conn->close();

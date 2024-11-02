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
    $vehicle_type_others = $data['vehicle_type_others'] ?? NULL; // Use null coalescing
    $secret_answer = $data['secret_answer'];
    $encrypted_answer = md5($secret_answer);

    // Validate required fields
    if (
        empty($email) || empty($phone_number) || empty($gender) || empty($address) || empty($vehicle_type) ||
        ($vehicle_type === "Others" && empty($vehicle_type_others))
    ) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit;
    }

    // Validate Driver Unique Identifier
    if ($driverId !== $_SESSION['driver_id']) {
        echo json_encode(['success' => false, 'message' => 'Error Validating Driver Identity.']);
        exit();
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

    // Check for duplicate phone number or email, excluding the current driver ID
    $checkQuery = "SELECT id FROM driver WHERE (phone_number = ? OR email = ?) AND id != ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("ssi", $phone_number, $email, $driverId);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->close(); // Close the statement
        echo json_encode(['success' => false, 'message' => 'Phone number or email already exists for another driver.']);
        exit();
    }

    // Fetch the secret answer for the current driver
    // Check if the provided secret answer matches the stored one for this driver
    $secretCheckQuery = "SELECT secret_answer FROM driver WHERE id = ?";
    $stmt = $conn->prepare($secretCheckQuery);
    $stmt->bind_param("i", $driverId);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($stored_secret_answer);
        $stmt->fetch();
        $stmt->close(); // Close after fetching

        // Validate secret answer and confirm password
        if ((md5($secret_answer) !== $stored_secret_answer)) {
            echo json_encode(['success' => false, 'message' => 'Account Validation Failed.']);
            exit();
        }
    }

    // Check if vehicle type is "Others" and ensure $vehicle_type_others is not null
    if ($vehicle_type === "Others") {
        if (empty($vehicle_type_others)) {
            echo json_encode(['success' => false, 'message' => 'Please specify other vehicle type.']);
            exit();
        }
        // Set $vehicle_type to $vehicle_type_others if "Others" is selected
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

    // Initialize the prepared statement for update
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        echo json_encode(['success' => false, 'message' => 'Failed to prepare the SQL statement.']);
        exit();
    }

    // Bind the parameters
    $stmt->bind_param("ssssssi", $email, $phone_number, $gender, $address, $vehicle_type, $encrypted_answer, $driverId);

    // Execute the statement
    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Your Record has been Successfully Updated."]);
    } else {
        echo json_encode(["success" => false, "message" => "Failed to update driver record."]);
    }

    // Close the statement
    $stmt->close();
} else {
    echo json_encode(["success" => false, "message" => "Driver ID is required."]);
}

// Close the database connection
$conn->close();

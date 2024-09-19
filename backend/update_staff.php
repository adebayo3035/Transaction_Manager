<?php
// Include database connection
include('config.php'); // Replace with your actual database connection file
session_start();

// Get the JSON data from the request body
$data = json_decode(file_get_contents("php://input"), true);

if (isset($data['admin_id'])) {
    $adminId = $data['admin_id'];
    $email = $data['email'];
    $phone_number = $data['phone_number'];
    $secret_answer = $data['secret_answer'];
    $encrypted_answer = md5($secret_answer);

    // Validate required fields
    if (empty($email) || empty($phone_number) || empty($secret_answer)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit;
    }

    // Validate Driver Unique Identifier
    if ($adminId !== $_SESSION['unique_id']) {
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
    $checkQuery = "SELECT unique_id, phone, email FROM admin_tbl WHERE (phone = ? OR email = ?) AND unique_id != ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("ssi", $phone_number, $email, $adminId);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        // Fetch the result and check for duplicates
        $stmt->bind_result($existingPhone, $existingEmail);
        $stmt->fetch();

        if ($existingPhone === $phone_number) {
            echo json_encode(['success' => false, 'message' => 'Phone number already exists for another driver.']);
            exit();
        }

        if ($existingEmail === $email) {
            echo json_encode(['success' => false, 'message' => 'Email address already exists for another driver.']);
            exit();
        }
    }

    // Close the duplicate check statement
    $stmt->close();

    // Query to select only the secret_answer using unique_id for validation
    $secretAnswerQuery = "SELECT secret_answer FROM admin_tbl WHERE unique_id = ?";
    $stmt = $conn->prepare($secretAnswerQuery);
    $stmt->bind_param("i", $adminId);  // Bind the unique_id (adminId)
    $stmt->execute();
    $stmt->store_result();

    // Bind the result to a variable
    $stmt->bind_result($stored_secret_answer);

    // Fetch the secret_answer and perform validation
    if ($stmt->num_rows > 0) {
        $stmt->fetch();
        // Compare the encrypted answers
        if ($stored_secret_answer !== $encrypted_answer) {
            echo json_encode(['success' => false, 'message' => 'Error Validating Secret Answer.']);
            exit();
        }
    } else {
        // No record found
        echo json_encode([
            'success' => false,
            'message' => 'No secret answer found for the provided ID.'
        ]);
        exit();
    }

    // Close the secret answer validation statement
    $stmt->close();

    // Prepare SQL query to update the driver
    $sql = "UPDATE admin_tbl SET email = ?, phone = ? WHERE unique_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $email, $phone_number, $adminId);

    // Execute the statement
    if ($stmt->execute()) {
        // Return success response
        echo json_encode(["success" => true, "message" => "Your Record has been Successfully Updated."]);
    } else {
        // Return error response
        echo json_encode(["success" => false, "message" => "Failed to update Staff record."]);
    }

    // Close the statement
    $stmt->close();
} else {
    // Return error response if the ID is not provided
    echo json_encode(["success" => false, "message" => "Driver ID is required."]);
}

// Close the database connection
$conn->close();

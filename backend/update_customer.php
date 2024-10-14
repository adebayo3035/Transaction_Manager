<?php
// session_start();
include_once "config.php";
include('restriction_checker.php');

// Get the JSON data from the request body
$data = json_decode(file_get_contents("php://input"), true);

if (isset($data['customer_id'])) {
    $customerId = $data['customer_id'];
    $firstname = $data['firstname'];
    $lastname = $data['lastname'];
    $email = $data['email'];
    $phone_number = $data['phone_number'];
    $gender = $data['gender'];
    $address = $data['address'];
    $group = $data['group'];
    $unit = $data['unit'];

    // Validate required fields
    if (empty($firstname) || empty($lastname) || empty($email) || empty($phone_number) || empty($group) || empty($gender) || empty($unit) || empty($address) || empty($customerId)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit;
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
    $checkQuery = "SELECT customer_id, mobile_number, email FROM customers WHERE (mobile_number = ? OR email = ?) AND customer_id != ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("ssi", $phone_number, $email, $customerId);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $existingPhone, $existingEmail);
        $stmt->fetch();

        if ($existingPhone == $phone_number) {
            echo json_encode(['success' => false, 'message' => 'Phone number already exists.']);
            exit();
        }

        if ($existingEmail == $email) {
            echo json_encode(['success' => false, 'message' => 'Email address already exists.']);
            exit();
        }
    }
    // Prepare SQL query to update the driver
    $sql = "UPDATE customers SET 
                firstname = ?, 
                lastname = ?, 
                email = ?, 
                mobile_number = ?, 
                gender = ?, 
                address = ?, 
                group_id = ?, 
                unit_id = ? 
            WHERE customer_id = ?";

    // Initialize the prepared statement
    $stmt = $conn->prepare($sql);

    // Bind the parameters
    $stmt->bind_param("ssssssiis", $firstname, $lastname, $email, $phone_number, $gender, $address, $group, $unit, $customerId);

    // Execute the statement
    if ($stmt->execute()) {
        // Return success response
        echo json_encode(["success" => true, "message" => "Customer Information has been successfully Updated."]);
    } else {
        // Return error response
        echo json_encode(["success" => false, "message" => "Failed to update Customer Information."]);
    }

    // Close the statement
    $stmt->close();
} else {
    // Return error response if the ID is not provided
    echo json_encode(["success" => false, "message" => "Customer ID is required."]);
}

// Close the database connection
$conn->close();


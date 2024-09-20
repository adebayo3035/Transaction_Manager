<?php
// Include database connection
include('config.php'); // Replace with your actual database connection file
session_start();
if (!isset($_SESSION['unique_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}
$loggedInUserRole = $_SESSION['role'];
$logged_in_user = $_SESSION['unique_id'];
if($loggedInUserRole !== "Super Admin"){
    echo json_encode(["success" => false, "message" => "Access Denied."]);
    exit();
}

// Get the JSON data from the request body
$data = json_decode(file_get_contents("php://input"), true);

if (isset($data['staff_id'])) {
    $adminId = $data['staff_id'];
    $firstname = $data['firstname'];
    $lastname = $data['lastname'];
    $email = $data['email'];
    $phone_number = $data['phone_number'];
    $role = $data['role'];
    $gender = $data['gender'];
    $address = $data['address'];

    // Validate required fields
    if (empty($email) || empty($phone_number) || empty($firstname) || empty($lastname) || empty($role) || empty($gender) || empty($address)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit;
    }
    if ($adminId == $logged_in_user) {
        echo json_encode(['success' => false, 'message' => 'Action not allowed: You cannot update your own record.']);
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

    // Check if there's a restriction or block AND role on customer's account
    $selectRest = "SELECT restriction_id, block_id, role FROM admin_tbl WHERE unique_id = ?";
    $stmt = $conn->prepare($selectRest);
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $stmt->store_result();

    if($stmt->num_rows > 0){
        $stmt->bind_result($restriction, $block, $staff_role);
        $stmt->fetch();
        if ($restriction !== 0 or $block !== 0) {
            echo json_encode(['success' => false, 'message' => 'This account is restricted. Kindly remove restriction before Updating']);
            exit();
        }
        if ($staff_role === 'Super Admin' && $role !== 'Super Admin') {
            echo json_encode(['success' => false, 'message' => 'You cannot downgrade Super Admin Account']);
            exit();
        }
    }
    $stmt->close();

    // Check for duplicate phone number or email, excluding the current driver ID
    $checkQuery = "SELECT phone, email FROM admin_tbl WHERE (phone = ? OR email = ?) AND unique_id != ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("ssi", $phone_number, $email, $adminId);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        // Fetch the result and check for duplicates
        $stmt->bind_result($existingPhone, $existingEmail);
        $stmt->fetch();

        if ($existingPhone === $phone_number) {
            echo json_encode(['success' => false, 'message' => 'Phone number already exists.']);
            exit();
        }

        if ($existingEmail === $email) {
            echo json_encode(['success' => false, 'message' => 'Email address already exists.']);
            exit();
        }
    }

    // Close the duplicate check statement
    $stmt->close();
    // Prepare SQL query to update the driver
    $sql = "UPDATE admin_tbl SET firstname = ?, lastname = ?, email = ?, phone = ?, address = ?, gender = ?, role =? WHERE unique_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssi", $firstname, $lastname, $email, $phone_number, $address, $gender, $role, $adminId);

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
    echo json_encode(["success" => false, "message" => "Staff ID is missing."]);
}

// Close the database connection
$conn->close();

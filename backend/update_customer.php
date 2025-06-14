<?php

include('restriction_checker.php');

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
    $restriction = $data['restriction'];

    if (empty($firstname) || empty($lastname) || empty($email) || empty($phone_number) || empty($group) || empty($gender) || empty($unit) || empty($address) || empty($customerId) || empty($restriction)) {
        logActivity("Update Failed: Required fields missing for customer ID $customerId.");
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        logActivity("Update Failed: Invalid email address '$email' for customer ID $customerId.");
        echo json_encode(['success' => false, 'message' => 'Invalid E-mail address.']);
        exit();
    }
    if ($restriction !== "1") {
        logActivity("Update Failed: Invalid Restriction Value selected:  '$restriction' for customer ID $customerId.");
        echo json_encode(['success' => false, 'message' => 'Invalid Restriction Status Selected.']);
        exit();
    }
    if (!preg_match('/^\d{11}$/', $phone_number)) {
        logActivity("Update Failed: Invalid phone number '$phone_number' for customer ID $customerId.");
        echo json_encode(['success' => false, 'message' => 'Please input a valid Phone Number.']);
        exit();
    }

    $checkQuery = "SELECT customer_id, mobile_number, email FROM customers WHERE (mobile_number = ? OR email = ?) AND customer_id != ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("ssi", $phone_number, $email, $customerId);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $existingPhone, $existingEmail);
        $stmt->fetch();

        if ($existingPhone == $phone_number) {
            logActivity("Update Failed: Phone number '$phone_number' already exists for a different customer.");
            echo json_encode(['success' => false, 'message' => 'Phone number already exists.']);
            exit();
        }

        if ($existingEmail == $email) {
            logActivity("Update Failed: Email '$email' already exists for a different customer.");
            echo json_encode(['success' => false, 'message' => 'Email address already exists.']);
            exit();
        }
    }
    // Check if the customer is locked
    $lockQuery = "SELECT status FROM customer_lock_history WHERE customer_id = ? ORDER BY id DESC LIMIT 1";
    $lockStmt = $conn->prepare($lockQuery);
    $lockStmt->bind_param("i", $customerId);
    $lockStmt->execute();
    $lockStmt->bind_result($lockStatus);
    $lockStmt->fetch();
    $lockStmt->close();

    if ($lockStatus === 'locked') {
        logActivity("Update Blocked: Customer ID $customerId is currently locked. Attempted by user ID {$_SESSION['unique_id']}.");
        echo json_encode(['success' => false, 'message' => 'This account is currently locked and cannot be updated.']);
        exit;
    }

    $sql = "UPDATE customers SET 
                firstname = ?, 
                lastname = ?, 
                email = ?, 
                mobile_number = ?, 
                gender = ?, 
                address = ?, 
                group_id = ?, 
                unit_id = ?,
                restriction = ?
            WHERE customer_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssiiis", $firstname, $lastname, $email, $phone_number, $gender, $address, $group, $unit,$restriction, $customerId);

    if ($stmt->execute()) {
        logActivity("Customer ID $customerId updated successfully by user ID {$_SESSION['unique_id']}.");
        echo json_encode(["success" => true, "message" => "Customer Information has been successfully Updated."]);
    } else {
        logActivity("Update Failed for customer ID $customerId by user ID {$_SESSION['unique_id']}. DB Error: " . $stmt->error);
        echo json_encode(["success" => false, "message" => "Failed to update Customer Information."]);
    }

    $stmt->close();
} else {
    logActivity("Update Attempt Failed: No customer ID provided.");
    echo json_encode(["success" => false, "message" => "Customer ID is required."]);
}

$conn->close();

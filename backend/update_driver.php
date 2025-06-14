<?php
include('config.php');
session_start();

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
    $statusNew = $data['status'];
    $restriction = $data['restriction'] ?? null; // Make restriction optional

    $adminId = $_SESSION['unique_id'] ?? 'Unknown';

    // Validate required fields (excluding restriction which is now optional)
    if (empty($firstname) || empty($lastname) || empty($email) || empty($phone_number) || empty($gender) || empty($vehicle_type) || empty($address) || empty($statusNew)) {
        logActivity("Update Failed: Required fields missing for Driver ID $driverId by Admin ID $adminId.");
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit;
    }

    // Email validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        logActivity("Update Failed: Invalid email '$email' for Driver ID $driverId by Admin ID $adminId.");
        echo json_encode(['success' => false, 'message' => 'Invalid E-mail address.']);
        exit;
    }

    // Phone number validation
    if (!preg_match('/^\d{11}$/', $phone_number)) {
        logActivity("Update Failed: Invalid phone number '$phone_number' for Driver ID $driverId by Admin ID $adminId.");
        echo json_encode(['success' => false, 'message' => 'Please input a valid Phone Number.']);
        exit;
    }

    // Get current restriction status before any updates
    $currentRestrictionQuery = "SELECT restriction, status FROM driver WHERE id = ?";
    $currentRestrictionStmt = $conn->prepare($currentRestrictionQuery);
    $currentRestrictionStmt->bind_param("i", $driverId);
    $currentRestrictionStmt->execute();
    $currentRestrictionStmt->bind_result($currentRestriction, $currentStatus,);
    $currentRestrictionStmt->fetch();
    $currentRestrictionStmt->close();

    // Prevent restriction removal for already restricted accounts
    if ($currentRestriction == 1 && isset($data['restriction']) && $data['restriction'] == 0) {
        logActivity("Update Blocked: Attempt to unrestrict Driver ID $driverId by Admin ID $adminId.");
        echo json_encode(['success' => false, 'message' => 'Cannot remove restriction from restricted accounts on this page.']);
        exit;
    }
    //Prevent setting Unavailable driver to available
    if ($currentStatus == 'Not Available' && isset($data['status']) && $data['status'] == 'Available') {
        logActivity("Update Blocked: Attempt to change Availability Status for Driver ID $driverId by Admin ID $adminId.");
        echo json_encode(['success' => false, 'message' => 'Cannot change Driver Availability Status.']);
        exit;
    }

    // If restriction is being set (not null), validate it
    if (isset($restriction)) {
        if (!in_array($restriction, [0, 1])) {
            logActivity("Update Failed: Invalid restriction value '$restriction' for Driver ID $driverId by Admin ID $adminId.");
            echo json_encode(['success' => false, 'message' => 'Invalid restriction value. Must be 0 or 1.']);
            exit;
        }
    } else {
        // If restriction wasn't sent (because account is restricted), keep current restriction
        $restriction = $currentRestriction;
    }

    // Gender validation
    $allowedGenders = ['Male', 'Female', 'Other'];
    if (!in_array($gender, $allowedGenders)) {
        logActivity("Update Failed: Invalid gender '$gender' for Driver ID $driverId by Admin ID $adminId.");
        echo json_encode(['success' => false, 'message' => 'Invalid gender. Must be Male, Female, or Other.']);
        exit;
    }

    // Status validation
    $allowedStatus = ['Available', 'Not Available'];
    if (!in_array($statusNew, $allowedStatus)) {
        logActivity("Update Failed: Invalid status '$statusNew' for Driver ID $driverId by Admin ID $adminId.");
        echo json_encode(['success' => false, 'message' => 'Invalid Status. Must be Available or Not Available.']);
        exit;
    }

    // Vehicle type validation
    $allowedVehicleTypes = ['Bicycle', 'Motorcycle', 'Tricycle', 'Bus', 'Car', 'Lorry'];
    if (!in_array($vehicle_type, $allowedVehicleTypes)) {
        logActivity("Update Failed: Invalid vehicle type '$vehicle_type' for Driver ID $driverId by Admin ID $adminId.");
        echo json_encode(['success' => false, 'message' => 'Invalid vehicle type selected.']);
        exit;
    }

    // Check for duplicate phone or email
    $checkQuery = "SELECT id, phone_number, email FROM driver WHERE (phone_number = ? OR email = ?) AND id != ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("ssi", $phone_number, $email, $driverId);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $existingPhone, $existingEmail);
        $stmt->fetch();

        if ($existingPhone == $phone_number) {
            logActivity("Update Failed: Duplicate phone '$phone_number' for Driver ID $driverId by Admin ID $adminId.");
            echo json_encode(['success' => false, 'message' => 'Phone number already exists for another driver.']);
            exit();
        }

        if ($existingEmail == $email) {
            logActivity("Update Failed: Duplicate email '$email' for Driver ID $driverId by Admin ID $adminId.");
            echo json_encode(['success' => false, 'message' => 'Email address already exists for another driver.']);
            exit();
        }
    }

    // Check driver status for restriction conflicts
    if ($statusNew === 'Not Available' && $restriction == 1) {
        logActivity("Update Failed: Driver ID $driverId cannot be Not Available and Restricted at the same time. Admin ID: $adminId.");
        echo json_encode(['success' => false, 'message' => 'Driver cannot be Unavailable and also Restricted.']);
        exit();
    }

    // Check if the driver account is locked
    $lockQuery = "SELECT status FROM driver_lock_history WHERE driver_id = ? ORDER BY id DESC LIMIT 1";
    $lockStmt = $conn->prepare($lockQuery);
    $lockStmt->bind_param("i", $driverId);
    $lockStmt->execute();
    $lockStmt->bind_result($lockStatus);
    $lockStmt->fetch();
    $lockStmt->close();

    if ($lockStatus === 'locked') {
        logActivity("Update Blocked: Driver ID $driverId is currently locked. Attempted by user ID {$_SESSION['unique_id']}.");
        echo json_encode(['success' => false, 'message' => 'This account is currently locked and cannot be updated.']);
        exit;
    }

    // Prepare update query
    $sql = "UPDATE driver SET 
                firstname = ?, 
                lastname = ?, 
                email = ?, 
                phone_number = ?, 
                gender = ?, 
                address = ?, 
                vehicle_type = ?, 
                status = ?, 
                restriction = ? 
            WHERE id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssssi", $firstname, $lastname, $email, $phone_number, $gender, $address, $vehicle_type, $statusNew, $restriction, $driverId);

    if ($stmt->execute()) {
        logActivity("SUCCESS: Driver ID $driverId updated by Admin ID $adminId (Status: $statusNew, Restriction: $restriction).");
        echo json_encode(["success" => true, "message" => "Driver updated successfully."]);
    } else {
        logActivity("Update Failed: Error updating Driver ID $driverId by Admin ID $adminId. Error: " . $stmt->error);
        echo json_encode(["success" => false, "message" => "Failed to update driver."]);
    }

    $stmt->close();
} else {
    logActivity("Update Failed: Driver ID not provided by Admin ID " . ($_SESSION['unique_id'] ?? 'Unknown') . ".");
    echo json_encode(["success" => false, "message" => "Driver ID is required."]);
}

$conn->close();
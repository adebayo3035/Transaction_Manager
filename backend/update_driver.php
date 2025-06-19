<?php
include('config.php');
session_start();

// Decode JSON input
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['id'])) {
    logActivity("Missing Driver ID in request.");
    echo json_encode(['success' => false, 'message' => 'Driver ID not provided.']);
    exit;
}

// Extract & sanitize
$driverId = $data['id'];
$firstname = trim($data['firstname']);
$lastname = trim($data['lastname']);
$email = trim($data['email']);
$phone_number = trim($data['phone_number']);
$gender = $data['gender'];
$address = trim($data['address']);
$vehicle_type = $data['vehicle_type'];
$statusNew = $data['status'];
$restriction = $data['restriction'] ?? null;
$adminId = $_SESSION['unique_id'] ?? 'Unknown';

logActivity("Attempting to update driver ID $driverId by Admin $adminId");

// Validation
if (empty($firstname) || empty($lastname) || empty($email) || empty($phone_number) || empty($gender) || empty($vehicle_type) || empty($address) || empty($statusNew)) {
    logActivity("Update failed: Missing required fields.");
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    logActivity("Update failed: Invalid email '$email'.");
    echo json_encode(['success' => false, 'message' => 'Invalid E-mail address.']);
    exit;
}

if (!preg_match('/^\d{11}$/', $phone_number)) {
    logActivity("Update failed: Invalid phone number '$phone_number'.");
    echo json_encode(['success' => false, 'message' => 'Invalid phone number.']);
    exit;
}

// Enum checks
$allowedGenders = ['Male', 'Female', 'Other'];
$allowedStatus = ['Available', 'Not Available'];
$allowedVehicleTypes = ['Bicycle', 'Motorcycle', 'Tricycle', 'Bus', 'Car', 'Lorry'];

if (!in_array($gender, $allowedGenders) || !in_array($statusNew, $allowedStatus) || !in_array($vehicle_type, $allowedVehicleTypes)) {
    logActivity("Update failed: Invalid selection in gender, status or vehicle type.");
    echo json_encode(['success' => false, 'message' => 'Invalid selection.']);
    exit;
}

// Get current values
$currentRestrictionQuery = "SELECT restriction, status FROM driver WHERE id = ?";
$currentRestrictionStmt = $conn->prepare($currentRestrictionQuery);
$currentRestrictionStmt->bind_param("i", $driverId);
$currentRestrictionStmt->execute();
$currentRestrictionStmt->bind_result($currentRestriction, $currentStatus);
$currentRestrictionStmt->fetch();
$currentRestrictionStmt->close();

if ($currentRestriction == 1 && isset($data['restriction']) && $data['restriction'] == 0) {
    logActivity("Attempt to unrestrict restricted account ID $driverId.");
    echo json_encode(['success' => false, 'message' => 'Cannot remove restriction from restricted accounts.']);
    exit;
}
if ($currentStatus == 'Not Available' && $statusNew == 'Available') {
    logActivity("Attempt to make unavailable driver available for ID $driverId.");
    echo json_encode(['success' => false, 'message' => 'Cannot change status from Not Available to Available.']);
    exit;
}
if ($statusNew === 'Not Available' && $restriction == 1) {
    logActivity("Conflict: Driver ID $driverId is both Not Available and Restricted.");
    echo json_encode(['success' => false, 'message' => 'Driver cannot be both Unavailable and Restricted.']);
    exit;
}
if (!is_null($restriction) && !in_array($restriction, [0, 1])) {
    logActivity("Invalid restriction value '$restriction' for driver ID $driverId.");
    echo json_encode(['success' => false, 'message' => 'Invalid restriction value.']);
    exit;
} else {
    $restriction = $restriction ?? $currentRestriction;
}

// Check duplicates
$checkQuery = "SELECT id FROM driver WHERE (phone_number = ? OR email = ?) AND id != ?";
$checkStmt = $conn->prepare($checkQuery);
$checkStmt->bind_param("ssi", $phone_number, $email, $driverId);
$checkStmt->execute();
$checkStmt->store_result();

if ($checkStmt->num_rows > 0) {
    logActivity("Duplicate phone/email for update on driver ID $driverId.");
    echo json_encode(['success' => false, 'message' => 'Email or phone already in use.']);
    exit;
}
$checkStmt->close();

// Check lock status
$lockQuery = "SELECT status FROM driver_lock_history WHERE driver_id = ? ORDER BY id DESC LIMIT 1";
$lockStmt = $conn->prepare($lockQuery);
$lockStmt->bind_param("i", $driverId);
$lockStmt->execute();
$lockStmt->bind_result($lockStatus);
$lockStmt->fetch();
$lockStmt->close();

if ($lockStatus === 'locked') {
    logActivity("Blocked update: Driver ID $driverId is currently locked.");
    echo json_encode(['success' => false, 'message' => 'Account is locked.']);
    exit;
}

// Begin transaction
$conn->begin_transaction();

try {
    // Restriction logging
    $referenceId = bin2hex(random_bytes(16));
    if ($restriction == 1 && $currentRestriction != 1) {
        $logQuery = "INSERT INTO account_restriction_audit_log (reference_id, account_id, account_type, action_type, initiated_by, initiated_by_role) VALUES (?, ?, 'DRIVER', 'RESTRICT', ?, ?)";
        $logStmt = $conn->prepare($logQuery);
        $adminRole = $_SESSION['role'] ?? 'Unknown';
        $logStmt->bind_param("ssss", $referenceId, $driverId, $adminId, $adminRole);
        if (!$logStmt->execute()) throw new Exception("Restriction log failed.");
        logActivity("Restriction applied to driver ID $driverId by Admin $adminId.");
        $logStmt->close();
    }

    // Main update
    $updateQuery = "UPDATE driver SET firstname=?, lastname=?, email=?, phone_number=?, gender=?, address=?, vehicle_type=?, status=?, restriction=? WHERE id=?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("sssssssssi", $firstname, $lastname, $email, $phone_number, $gender, $address, $vehicle_type, $statusNew, $restriction, $driverId);
    if (!$stmt->execute()) throw new Exception("Update failed: " . $stmt->error);
    $stmt->close();

    $conn->commit();
    logActivity("SUCCESS: Driver ID $driverId updated by Admin $adminId.");
    echo json_encode(['success' => true, 'message' => 'Driver updated successfully.']);
} catch (Exception $e) {
    $conn->rollback();
    logActivity("ROLLBACK: Update failed for driver ID $driverId. Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Transaction failed.']);
}

$conn->close();

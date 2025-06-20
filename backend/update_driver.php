<?php
include('config.php');
session_start();
header('Content-Type: application/json');

// Ensure session is active
if (!isset($_SESSION['unique_id'])) {
    logActivity("Access denied: No active session.");
    echo json_encode(['success' => false, 'message' => 'Access denied. Please log in first.']);
    exit;
}

$adminId = $_SESSION['unique_id'];
$adminRole = $_SESSION['role'] ?? 'Unknown';

// Decode JSON input
$data = json_decode(file_get_contents("php://input"), true);

// === Required Field Check ===
if (empty($data['id'])) {
    logActivity("Missing Driver ID in request.");
    echo json_encode(['success' => false, 'message' => 'Driver ID not provided.']);
    exit;
}

// === Extract and sanitize ===
$driverId = $data['id'];
$firstname = trim($data['firstname'] ?? '');
$lastname = trim($data['lastname'] ?? '');
$email = strtolower(trim($data['email'] ?? ''));
$phone_number = trim($data['phone_number'] ?? '');
$gender = trim($data['gender'] ?? '');
$address = trim($data['address'] ?? '');
$vehicle_type = trim($data['vehicle_type'] ?? '');
$statusNew = trim($data['status'] ?? '');
$restriction = $data['restriction'] ?? null;

// === Validate required fields ===
if (
    empty($firstname) || empty($lastname) || empty($email) || empty($phone_number) ||
    empty($gender) || empty($vehicle_type) || empty($address) || empty($statusNew)
) {
    logActivity("Update failed: Missing required fields for Driver ID $driverId.");
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

$allowedGenders = ['Male', 'Female', 'Other'];
$allowedStatus = ['Available', 'Not Available'];
$allowedVehicleTypes = ['Bicycle', 'Motorcycle', 'Tricycle', 'Bus', 'Car', 'Lorry'];

if (!in_array($gender, $allowedGenders, true)) {
    logActivity("Update failed: Invalid gender '$gender' for Driver ID $driverId.");
    echo json_encode(['success' => false, 'message' => 'Invalid gender selection.']);
    exit;
}

if (!in_array($statusNew, $allowedStatus, true)) {
    logActivity("Update failed: Invalid status '$statusNew' for Driver ID $driverId.");
    echo json_encode(['success' => false, 'message' => 'Invalid status selection.']);
    exit;
}

if (!in_array($vehicle_type, $allowedVehicleTypes, true)) {
    logActivity("Update failed: Invalid vehicle type '$vehicle_type' for Driver ID $driverId.");
    echo json_encode(['success' => false, 'message' => 'Invalid vehicle type selection.']);
    exit;
}

// === Get current restriction and status ===
$currentQuery = "SELECT restriction, status FROM driver WHERE id = ?";
$currentStmt = $conn->prepare($currentQuery);
$currentStmt->bind_param("i", $driverId);
$currentStmt->execute();
$currentStmt->bind_result($currentRestriction, $currentStatus);
$currentStmt->fetch();
$currentStmt->close();

// === Restriction enforcement rules ===
if ((int)$currentRestriction === 1 && isset($restriction) && (int)$restriction === 0) {
    logActivity("Attempt to unrestrict restricted driver ID $driverId.");
    echo json_encode(['success' => false, 'message' => 'Cannot remove restriction from restricted accounts.']);
    exit;
}

if ($currentStatus === 'Not Available' && $statusNew === 'Available') {
    logActivity("Attempt to make unavailable driver available: Driver ID $driverId.");
    echo json_encode(['success' => false, 'message' => 'Cannot make this driver available.']);
    exit;
}

if ($statusNew === 'Not Available' && (int)$restriction === 1) {
    logActivity("Conflict: Driver ID $driverId marked as both Not Available and Restricted.");
    echo json_encode(['success' => false, 'message' => 'Driver cannot be both Unavailable and Restricted.']);
    exit;
}

if (!is_null($restriction) && !in_array((int)$restriction, [0, 1], true)) {
    logActivity("Invalid restriction value '$restriction' for driver ID $driverId.");
    echo json_encode(['success' => false, 'message' => 'Invalid restriction value.']);
    exit;
} else {
    $restriction = $restriction ?? $currentRestriction;
}

// === Check for email/phone duplication ===
$checkQuery = "SELECT id FROM driver WHERE (phone_number = ? OR email = ?) AND id != ?";
$checkStmt = $conn->prepare($checkQuery);
$checkStmt->bind_param("ssi", $phone_number, $email, $driverId);
$checkStmt->execute();
$checkStmt->store_result();

if ($checkStmt->num_rows > 0) {
    logActivity("Duplicate phone/email found during update for Driver ID $driverId.");
    echo json_encode(['success' => false, 'message' => 'Email or phone number already in use.']);
    $checkStmt->close();
    exit;
}
$checkStmt->close();

// === Check lock status ===
$lockQuery = "SELECT status FROM driver_lock_history WHERE driver_id = ? ORDER BY id DESC LIMIT 1";
$lockStmt = $conn->prepare($lockQuery);
$lockStmt->bind_param("i", $driverId);
$lockStmt->execute();
$lockStmt->bind_result($lockStatus);
$lockStmt->fetch();
$lockStmt->close();

if ($lockStatus === 'locked') {
    logActivity("Blocked update: Driver ID $driverId is currently locked.");
    echo json_encode(['success' => false, 'message' => 'This driver account is locked and cannot be updated.']);
    exit;
}

// === Begin transaction ===
$conn->begin_transaction();

try {
    // === Restriction audit log ===
    if ((int)$restriction === 1 && (int)$currentRestriction !== 1) {
        $referenceId = bin2hex(random_bytes(16));
        $logQuery = "INSERT INTO account_restriction_audit_log 
                     (reference_id, account_id, account_type, action_type, initiated_by, initiated_by_role) 
                     VALUES (?, ?, 'DRIVER', 'RESTRICT', ?, ?)";
        $logStmt = $conn->prepare($logQuery);
        $logStmt->bind_param("ssss", $referenceId, $driverId, $adminId, $adminRole);
        if (!$logStmt->execute()) throw new Exception("Restriction audit logging failed.");
        $logStmt->close();

        logActivity("Restriction applied to DRIVER ID: $driverId by Admin ID: $adminId.");
    }

    // === Main update ===
    $updateQuery = "UPDATE driver 
                    SET firstname = ?, lastname = ?, email = ?, phone_number = ?, gender = ?, address = ?, vehicle_type = ?, status = ?, restriction = ? 
                    WHERE id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("sssssssssi", $firstname, $lastname, $email, $phone_number, $gender, $address, $vehicle_type, $statusNew, $restriction, $driverId);
    if (!$stmt->execute()) throw new Exception("Driver update failed: " . $stmt->error);
    $stmt->close();

    $conn->commit();
    logActivity("SUCCESS: Driver ID $driverId updated by Admin ID $adminId.");
    echo json_encode(['success' => true, 'message' => 'Driver updated successfully.']);

} catch (Exception $e) {
    $conn->rollback();
    logActivity("ROLLBACK: Driver update failed for ID $driverId. Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Update failed. Please try again.']);
}

$conn->close();

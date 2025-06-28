<?php
header('Content-Type: application/json');
include('config.php');
session_start();

// Validate session and permissions
if (!isset($_SESSION['unique_id'])) {
    logActivity("Unauthorized access attempt - No active session found");
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}

$loggedInUserRole = $_SESSION['role'];
$logged_in_user = $_SESSION['unique_id'];
logActivity("Staff update process initiated by User ID: $logged_in_user (Role: $loggedInUserRole)");

if ($loggedInUserRole !== "Super Admin") {
    logActivity("Permission denied - User ID: $logged_in_user attempted staff update without Super Admin privileges");
    echo json_encode(["success" => false, "message" => "Access Denied."]);
    exit();
}

// Process request data
$data = json_decode(file_get_contents("php://input"), true);
logActivity("Received update request data: " . json_encode($data));

if (!isset($data['staff_id'])) {
    logActivity("Validation failed - Staff ID missing in request");
    echo json_encode(["success" => false, "message" => "Staff ID is missing."]);
    exit();
}

// Extract and validate input data
$adminId = $data['staff_id'];
$firstname = trim($data['firstname']);
$lastname = trim($data['lastname']);
$email = trim($data['email']);
$phone_number = trim($data['phone_number']);
$role = $data['role'];
$gender = $data['gender'];
$address = trim($data['address']);

logActivity("Processing update for Staff ID: $adminId");

// Validate required fields
$requiredFields = [
    'email' => $email,
    'phone_number' => $phone_number,
    'firstname' => $firstname,
    'lastname' => $lastname,
    'role' => $role,
    'gender' => $gender,
    'address' => $address
];

foreach ($requiredFields as $field => $value) {
    if (empty($value)) {
        logActivity("Validation failed - Missing required field: $field for Staff ID: $adminId");
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit;
    }
}

// Prevent self-update
if ($adminId == $logged_in_user) {
    logActivity("Security violation - User ID: $logged_in_user attempted self-update");
    echo json_encode(['success' => false, 'message' => 'Action not allowed: You cannot update your own record.']);
    exit;
}

// Email validation
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    logActivity("Validation failed - Invalid email format: $email for Staff ID: $adminId");
    echo json_encode(['success' => false, 'message' => 'Invalid E-mail address.']);
    exit();
}

// Phone number validation
if (!preg_match('/^\d{11}$/', $phone_number)) {
    logActivity("Validation failed - Invalid phone format: $phone_number for Staff ID: $adminId");
    echo json_encode(['success' => false, 'message' => 'Please input a valid Phone Number.']);
    exit();
}

// Check account restrictions
logActivity("Checking account restrictions for Staff ID: $adminId");
$selectRest = "SELECT restriction_id, block_id, role FROM admin_tbl WHERE unique_id = ?";
$stmt = $conn->prepare($selectRest);

if (!$stmt) {
    logActivity("Database error - Failed to prepare restriction check query for Staff ID: $adminId");
    echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
    exit();
}

$stmt->bind_param("i", $adminId);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->bind_result($restriction, $block, $staff_role);
    $stmt->fetch();
    
    if ($restriction !== 0 || $block !== 0) {
        logActivity("Update blocked - Staff ID: $adminId has restrictions (Restriction: $restriction, Block: $block)");
        echo json_encode(['success' => false, 'message' => 'This account is restricted. Kindly remove restriction before Updating']);
        $stmt->close();
        exit();
    }
    
    if ($staff_role === 'Super Admin' && $role !== 'Super Admin') {
        logActivity("Security violation - Attempt to downgrade Super Admin account: $adminId");
        echo json_encode(['success' => false, 'message' => 'You cannot downgrade Super Admin Account']);
        $stmt->close();
        exit();
    }
}
$stmt->close();

// Check for duplicates
logActivity("Checking for duplicate phone/email for Staff ID: $adminId");
$checkQuery = "SELECT phone, email FROM admin_tbl WHERE (phone = ? OR email = ?) AND unique_id != ?";
$stmt = $conn->prepare($checkQuery);

if (!$stmt) {
    logActivity("Database error - Failed to prepare duplicate check query for Staff ID: $adminId");
    echo json_encode(["success" => false, "message" => "Database error occurred."]);
    exit();
}

$stmt->bind_param("ssi", $phone_number, $email, $adminId);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->bind_result($existingPhone, $existingEmail);
    $stmt->fetch();

    if ($existingPhone === $phone_number) {
        logActivity("Duplicate detected - Phone number $phone_number already exists (excluding Staff ID: $adminId)");
        echo json_encode(['success' => false, 'message' => 'Phone number already exists.']);
        $stmt->close();
        exit();
    }

    if ($existingEmail === $email) {
        logActivity("Duplicate detected - Email $email already exists (excluding Staff ID: $adminId)");
        echo json_encode(['success' => false, 'message' => 'Email address already exists.']);
        $stmt->close();
        exit();
    }
}
$stmt->close();

// Prepare and execute update
logActivity("Preparing update query for Staff ID: $adminId");
$sql = "UPDATE admin_tbl SET 
            firstname = ?, 
            lastname = ?, 
            email = ?, 
            phone = ?, 
            address = ?, 
            gender = ?, 
            role = ?,
            updated_at = NOW(),
            last_updated_by = ?
        WHERE unique_id = ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    logActivity("Database error - Failed to prepare update query for Staff ID: $adminId");
    echo json_encode(["success" => false, "message" => "Database error occurred."]);
    exit();
}

$stmt->bind_param("sssssssii", $firstname, $lastname, $email, $phone_number, $address, $gender, $role, $logged_in_user, $adminId);
logActivity("Executing update for Staff ID: $adminId");

if ($stmt->execute()) {
    $affectedRows = $conn->affected_rows;
    logActivity("Success - Updated Staff ID: $adminId. Affected rows: $affectedRows");
    echo json_encode([
        "success" => true, 
        "message" => "Your Record has been Successfully Updated.",
        "affected_rows" => $affectedRows
    ]);
} else {
    logActivity("Update failed for Staff ID: $adminId - Error: " . $conn->error);
    echo json_encode([
        "success" => false, 
        "message" => "Failed to update Staff record.",
        "error" => $conn->error
    ]);
}

// Clean up resources
$stmt->close();
$conn->close();
logActivity("Staff update process completed for Staff ID: $adminId");
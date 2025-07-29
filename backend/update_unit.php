<?php
// Include database connection
include('config.php');
session_start();

logActivity("Update Unit request received.");

// Check user session
if (!isset($_SESSION['unique_id'])) {
    logActivity("Access denied: User not logged in.");
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}

$loggedInUserRole = $_SESSION['role'];
$logged_in_user = $_SESSION['unique_id'];
logActivity("User $logged_in_user Role: $loggedInUserRole");

if ($loggedInUserRole !== "Super Admin" && $loggedInUserRole !== "Admin") {
    logActivity("Access denied for $logged_in_user: Not a recognized Administrator or Super Administrator.");
    echo json_encode(["success" => false, "message" => "Access Denied."]);
    exit();
}

// Decode incoming JSON
$data = json_decode(file_get_contents("php://input"), true);
logActivity("Request payload received.");

// Validate presence of required keys
$requiredFields = ['unit_id', 'unit_name', 'group_id'];
foreach ($requiredFields as $field) {
    if (!isset($data[$field]) || $data[$field] === '') {
        logActivity("Validation failed: Missing field - $field");
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit();
    }
}


$unitId    = intval($data['unit_id']);
$unit_name = trim($data['unit_name']);
$group_id  = intval($data['group_id']);

// Check for soft-deleted unit
$checkDelSql = "SELECT delete_status FROM unit WHERE unit_id = ?";
$stmt = $conn->prepare($checkDelSql);
$stmt->bind_param("i", $unitId);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    logActivity("Unit ID $unitId not found.");
    echo json_encode(["success" => false, "message" => "Unit not found."]);
    exit();
}

$unitRow = $res->fetch_assoc();
$currentDeleteStatus = $unitRow['delete_status'];

// Check if the unit is currently deleted but the update does not attempt to reactivate
if ($currentDeleteStatus == 1) {
    logActivity("Blocked update attempt: Unit ID $unitId is Deactivated.");
    echo json_encode(["success" => false, "message" => "Unit is deleted. Please reactivate it before updating."]);
    exit();
}
$stmt->close();

// Check uniqueness of new name
$checkDupSql = "SELECT COUNT(*) as count FROM unit WHERE unit_name = ? AND unit_id != ?";
$stmt = $conn->prepare($checkDupSql);
$stmt->bind_param("si", $unit_name, $unitId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if ($row['count'] > 0) {
    logActivity("Duplicate unit name '$unit_name' detected.");
    echo json_encode(["success" => false, "message" => "Another unit with the same name already exists."]);
    exit();
}

// Proceed with update
$updateSql = "UPDATE unit SET unit_name = ?, group_id = ? WHERE unit_id = ?";
$stmt = $conn->prepare($updateSql);
$stmt->bind_param("sii", $unit_name, $group_id, $unitId);

if ($stmt->execute()) {
    logActivity("Unit ID $unitId updated successfully by $logged_in_user.");
    echo json_encode(["success" => true, "message" => "Unit record updated successfully."]);
} else {
    logActivity("Update failed for Unit ID $unitId: " . $stmt->error);
    echo json_encode(["success" => false, "message" => "Failed to update unit."]);
}

$stmt->close();
$conn->close();
logActivity("Update Unit request completed.");

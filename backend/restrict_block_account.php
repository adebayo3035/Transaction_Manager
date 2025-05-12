<?php
include 'config.php';
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['unique_id'])) {
    logActivity("Access Denied! No session found.");
    echo json_encode(["success" => false, "message" => "Access Denied! Kindly login first."]);
    exit();
}

$role = $_SESSION['role'];
if ($role !== "Super Admin") {
    logActivity("Access Denied! User with role '$role' tried to access restricted endpoint.");
    echo json_encode(["success" => false, "message" => "Access Denied! Permission not granted."]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);
$identifier = $data['staffID'] ?? null;
$restrictionType = $data['restrictionType'] ?? null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$identifier || !$restrictionType) {
    logActivity("Invalid request method or missing payload fields.");
    echo json_encode(["success" => false, "message" => "Invalid request."]);
    exit();
}

if (!in_array($restrictionType, ['restrict', 'block'])) {
    logActivity("Invalid restrictionType: $restrictionType provided.");
    echo json_encode(["success" => false, "message" => "Please Select a Valid Lien Type."]);
    exit();
}

$columnToUpdate = ($restrictionType === 'restrict') ? 'restriction_id' : 'block_id';
logActivity("Starting $restrictionType process for identifier: $identifier");

if (is_numeric($identifier)) {
    $stmt = $conn->prepare("SELECT unique_id, role, restriction_id, block_id FROM admin_tbl WHERE unique_id = ?");
    $stmt->bind_param("i", $identifier);
} else {
    $stmt = $conn->prepare("SELECT unique_id, role, restriction_id, block_id FROM admin_tbl WHERE email = ? OR phone = ?");
    $stmt->bind_param("ss", $identifier, $identifier);
}

$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    logActivity("Account not found for identifier: $identifier");
    echo json_encode(["success" => false, "message" => "Account Not Found."]);
    $stmt->close();
    $conn->close();
    exit();
}

$stmt->bind_result($uniqueId, $foundRole, $restrictionId, $blockId);
$stmt->fetch();
$stmt->close();

// Prevent blocking/restricting Super Admins
if ($foundRole === "Super Admin") {
    logActivity("Attempted to $restrictionType a Super Admin with ID: $uniqueId");
    echo json_encode(["success" => false, "message" => "Super Admin Account cannot be restricted or blocked."]);
    $conn->close();
    exit();
}

// Check existing restriction or block
if ($columnToUpdate === 'restriction_id' && $restrictionId == 1) {
    logActivity("Restriction already exists for account ID: $uniqueId");
    echo json_encode(["success" => false, "message" => "There is an existing restriction on this account."]);
    $conn->close();
    exit();
} elseif ($columnToUpdate === 'block_id' && $blockId == 1) {
    logActivity("Block already exists for account ID: $uniqueId");
    echo json_encode(["success" => false, "message" => "There is an existing block on this account."]);
    $conn->close();
    exit();
}

// Update the column
$updateStmt = $conn->prepare("UPDATE admin_tbl SET $columnToUpdate = 1 WHERE unique_id = ?");
$updateStmt->bind_param("i", $uniqueId);

if ($updateStmt->execute()) {
    logActivity("Successfully updated $columnToUpdate to 1 for account ID: $uniqueId");
    echo json_encode(["success" => true, "message" => "Account successfully updated."]);
} else {
    logActivity("Failed to update $columnToUpdate for account ID: $uniqueId. MySQL error: " . $updateStmt->error);
    echo json_encode(["success" => false, "message" => "Failed to update account. Please try again."]);
}

$updateStmt->close();
$conn->close();

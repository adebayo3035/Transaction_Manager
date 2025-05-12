<?php
header('Content-Type: application/json');
include('config.php');
session_start();

// Validate session and permissions
if (!isset($_SESSION['unique_id'])) {
    logActivity("Unauthorized access attempt - No session found");
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}

$loggedInUserRole = $_SESSION['role'];
$logged_in_user = $_SESSION['unique_id'];
logActivity("Revenue update process initiated by User ID: $logged_in_user with Role: $loggedInUserRole");

if ($loggedInUserRole !== "Super Admin") {
    logActivity("Unauthorized access attempt by User ID: $logged_in_user (Role: $loggedInUserRole)");
    echo json_encode(["success" => false, "message" => "Access Denied."]);
    exit();
}

// Process request data
$data = json_decode(file_get_contents("php://input"), true);
logActivity("Received request data: " . json_encode($data));

if (!isset($data['revenue_id'])) {
    logActivity("Validation failed - Revenue ID missing in request");
    echo json_encode(["success" => false, "message" => "Revenue ID is missing."]);
    exit();
}

$revenueId = $data['revenue_id'];
$revenue_name = trim($data['revenue_name']);
$revenue_description = isset($data['revenue_description']) ? trim($data['revenue_description']) : '';

logActivity("Processing update for Revenue ID: $revenueId");

// Validate required fields
if (empty($revenueId) || empty($revenue_name)) {
    logActivity("Validation failed - Required fields missing for Revenue ID: $revenueId");
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
    exit();
}

// Check for duplicate revenue names
logActivity("Checking for duplicate revenue names for Revenue ID: $revenueId");
$selectRest = "SELECT COUNT(*) AS revenue_count FROM revenue_types WHERE revenue_type_name = ? AND revenue_type_id != ?";
$stmt = $conn->prepare($selectRest);

if (!$stmt) {
    logActivity("Database error - Failed to prepare duplicate check query for Revenue ID: $revenueId");
    echo json_encode(["success" => false, "message" => "Database error occurred."]);
    exit();
}

$stmt->bind_param("si", $revenue_name, $revenueId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if ($row['revenue_count'] > 0) {
    logActivity("Duplicate detected - Revenue name '$revenue_name' already exists (excluding ID: $revenueId)");
    echo json_encode(["success" => false, "message" => "Revenue with the same name already exists."]);
    $stmt->close();
    exit();
}
$stmt->close();

// Prepare and execute update query
logActivity("Preparing update query for Revenue ID: $revenueId");
$sql = "UPDATE revenue_types SET 
            revenue_type_name = ?, 
            revenue_type_description = ?,
            updated_at = NOW()
        WHERE revenue_type_id = ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    logActivity("Database error - Failed to prepare update query for Revenue ID: $revenueId");
    echo json_encode(["success" => false, "message" => "Database error occurred."]);
    exit();
}

$stmt->bind_param("ssi", $revenue_name, $revenue_description, $revenueId);
logActivity("Executing update for Revenue ID: $revenueId with new name: '$revenue_name'");

if ($stmt->execute()) {
    $affectedRows = $conn->affected_rows;
    logActivity("Success - Updated Revenue ID: $revenueId. Affected rows: $affectedRows");
    echo json_encode([
        "success" => true, 
        "message" => "Revenue Record has been Successfully Updated.",
        "affected_rows" => $affectedRows
    ]);
} else {
    logActivity("Update failed for Revenue ID: $revenueId - Database error: " . $conn->error);
    echo json_encode([
        "success" => false, 
        "message" => "Failed to update Revenue Details.",
        "error" => $conn->error
    ]);
}

// Clean up resources
$stmt->close();
$conn->close();
logActivity("Revenue update process completed for Revenue ID: $revenueId");
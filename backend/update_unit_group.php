<?php
session_start();
include("config.php"); // Ensure it contains your DB connection and logActivity()

header('Content-Type: application/json');
if (!isset($_SESSION["unique_id"])) {
    logActivity("Session not initialized or expired. No user_id in session.");
}
// Get current user info
$loggedInUserId = $_SESSION["unique_id"] ?? null;
$loggedInUserRole = $_SESSION["role"] ?? null;

logActivity("Restore request received by user ID: " . ($loggedInUserId ?? 'null') . ", Role: " . ($loggedInUserRole ?? 'null'));

// Log raw session for debugging (remove this in production)
logActivity("Session snapshot: " . json_encode($_SESSION));

// Ensure only Super Admin can restore
if (strtolower($loggedInUserRole) !== "super admin") {
    logActivity("Access denied. User ID: " . ($loggedInUserId ?? 'null') . " is not Super Admin. Detected role: " . ($loggedInUserRole ?? 'null'));
    echo json_encode(["success" => false, "message" => "Access denied."]);
    exit();
}


// Get and decode request body
$data = json_decode(file_get_contents("php://input"), true);
$classType = $data["class_type"] ?? '';
$deleteStatus = $data["delete_status"] ?? null; // Should be null for restore

// Determine target table and ID column
$table = '';
$idColumn = '';
$idValue = null;

if ($classType === "unit") {
    $table = "unit";
    $idColumn = "unit_id";
    $idValue = $data["unit_id"] ?? null;
} elseif ($classType === "group") {
    $table = "groups";
    $idColumn = "group_id";
    $idValue = $data["group_id"] ?? null;
} else {
    echo json_encode(["success" => false, "message" => "Invalid class type."]);
    exit();
}

if (!$idValue) {
    echo json_encode(["success" => false, "message" => "Missing ID."]);
    exit();
}

// Perform the update
$stmt = $conn->prepare("UPDATE $table SET delete_status = ? WHERE $idColumn = ?");
$stmt->bind_param("si", $deleteStatus, $idValue);

if ($stmt->execute()) {
    logActivity("Record restored in $table by $loggedInUserId (ID: $idValue).");
    echo json_encode(["success" => true, "message" => ucfirst($classType) . " restored successfully."]);
} else {
    logActivity("Failed to restore $classType: " . $stmt->error);
    echo json_encode(["success" => false, "message" => "Failed to restore record."]);
}

$stmt->close();
$conn->close();


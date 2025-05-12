<?php
session_start();
include 'config.php'; // Ensure this includes the $conn variable

header('Content-Type: application/json');
$user_id = $_SESSION['unique_id'] ?? null;

if (!$user_id) {
    logActivity("Restriction Check Failed: User not authenticated.");
    echo json_encode(['success' => false, 'message' => 'User not authenticated.']);
    exit;
}

// Retrieve the restriction_id for the user
$query = "SELECT restriction_id FROM admin_tbl WHERE unique_id = ?";
$stmt = $conn->prepare($query);

if (!$stmt) {
    logActivity("Restriction Check DB Error: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Database error.']);
    exit;
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($restriction_id);
$stmt->fetch();
$stmt->close();

if ($restriction_id == 1) {
    logActivity("Restriction Check: Blocked access for user ID $user_id due to restriction.");
    echo json_encode(['success' => false, 'message' => 'There is a restriction on this staff account.']);
    exit;
}

logActivity("Restriction Check Passed: User ID $user_id allowed access.");
// If you want to continue the flow after this, do so here

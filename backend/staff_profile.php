<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
include 'config.php';

if (!isset($_SESSION['unique_id'])) {
    logActivity("Unauthorized access attempt to admin profile – no session found.");
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

$admin_id = $_SESSION['unique_id'];

// Fetch admin info
$sql = "SELECT unique_id, firstname, lastname, email, photo, gender, phone, role, restriction_id, delete_status, block_id, created_at FROM admin_tbl WHERE unique_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($admin = $result->fetch_assoc()) {
    logActivity("Admin profile fetched successfully for user ID: {$admin_id}");
    echo json_encode(['success' => true, 'admin' => $admin]);
} else {
    logActivity("Failed to fetch admin profile for user ID: {$admin_id}");
    echo json_encode(['success' => false, 'message' => 'Admin not found']);
}

$stmt->close();
$conn->close();

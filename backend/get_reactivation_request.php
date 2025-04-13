<?php
session_start();
require 'config.php';
header('Content-Type: application/json');

// Only Super Admin can access
if (!isset($_SESSION['unique_id']) || $_SESSION['role'] !== 'Super Admin') {
    http_response_code(403);
    echo json_encode(["error" => "Unauthorized access"]);
    exit;
}

$staff_id = $_GET['staff_id'] ?? null;

if (!$staff_id) {
    http_response_code(400);
    echo json_encode(["error" => "Staff ID is required"]);
    exit;
}

try {
    // Fetch latest deactivation ID
    $stmt = $conn->prepare("SELECT id FROM admin_deactivation_logs WHERE admin_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $deactivation = $result->fetch_assoc();

    if (!$deactivation) {
        echo json_encode(["error" => "Deactivation log not found"]);
        exit;
    }

    $deactivation_id = $deactivation['id'];

    // Fetch reactivation request details
    $stmt = $conn->prepare("SELECT * FROM admin_reactivation_logs WHERE deactivation_log_id = ?");
    $stmt->bind_param("i", $deactivation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $request = $result->fetch_assoc();

    if (!$request) {
        echo json_encode(["error" => "No reactivation request found"]);
        exit;
    }

    echo json_encode(["success" => true, "data" => $request]);
} catch (Exception $e) {
    logActivity("Error fetching reactivation request: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Server error"]);
}


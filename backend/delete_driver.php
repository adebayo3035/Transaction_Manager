<?php
include('config.php');
session_start();
header('Content-Type: application/json');

// Validate session
if (!isset($_SESSION['unique_id'])) {
    logActivity("Access denied: No active session from IP: " . $_SERVER['REMOTE_ADDR']);
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Access denied. Please log in first.']));
}

$adminId = $_SESSION['unique_id'];
$adminRole = $_SESSION['role'] ?? 'Unknown';

// Validate input
$data = json_decode(file_get_contents("php://input"), true) ?? [];
if (empty($data['id']) || !is_numeric($data['id'])) {
    logActivity("Invalid Driver ID in request from Admin ID: $adminId");
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Valid Driver ID required.']));
}

$driverId = (int)$data['id'];

// === Get current driver status ===
$currentStmt = $conn->prepare("SELECT restriction, status FROM driver WHERE id = ?");
if (!$currentStmt || !$currentStmt->bind_param("i", $driverId) || !$currentStmt->execute()) {
    logActivity("DB Error checking driver status: " . ($conn->error ?? 'Unknown'));
    http_response_code(500);
    exit(json_encode(['success' => false, 'message' => 'System error.']));
}

$currentStmt->bind_result($currentRestriction, $currentStatus);
if (!$currentStmt->fetch()) {
    $currentStmt->close();
    logActivity("Driver not found: ID $driverId");
    http_response_code(404);
    exit(json_encode(['success' => false, 'message' => 'Driver not found.']));
}
$currentStmt->close();

// Validate account state
if ($currentRestriction == 1) {
    logActivity("Attempt to deactivate restricted driver ID $driverId by Admin $adminId");
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Cannot deactivate restricted accounts.']));
}

if ($currentStatus === 'Not Available') {
    logActivity("Attempt to deactivate unavailable driver ID $driverId by Admin $adminId");
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Cannot deactivate unavailable accounts.']));
}

// Check account lock
$lockStmt = $conn->prepare("SELECT status FROM driver_lock_history WHERE driver_id = ? ORDER BY id DESC LIMIT 1");
if ($lockStmt && $lockStmt->bind_param("i", $driverId) && $lockStmt->execute()) {
    $lockStmt->bind_result($lockStatus);
    $lockStmt->fetch();
    if ($lockStatus === 'locked') {
        $lockStmt->close();
        logActivity("Attempt to modify locked driver ID $driverId by Admin $adminId");
        http_response_code(423);
        exit(json_encode(['success' => false, 'message' => 'Account is locked.']));
    }
}
if ($lockStmt) $lockStmt->close();

// === Transaction ===
$conn->begin_transaction();

try {
    // Get active session
    $sessionStmt = $conn->prepare("SELECT session_id FROM driver_active_sessions WHERE driver_id = ? AND status = 'Active'");
    if (!$sessionStmt || !$sessionStmt->bind_param("i", $driverId) || !$sessionStmt->execute()) {
        throw new Exception("Session check failed: " . ($conn->error ?? 'Unknown'));
    }
    
    $sessionStmt->bind_result($session_id);
    $hasSession = $sessionStmt->fetch();
    $sessionStmt->close();

    // Deactivate session if exists
    if ($hasSession && $session_id) {
        $updateSessionStmt = $conn->prepare("UPDATE driver_active_sessions SET status = 'Inactive' WHERE session_id = ?");
        if (!$updateSessionStmt || !$updateSessionStmt->bind_param("s", $session_id) || !$updateSessionStmt->execute()) {
            throw new Exception("Session deactivation failed: " . ($conn->error ?? 'Unknown'));
        }
        $updateSessionStmt->close();
    }

    // Update driver status
    $delete_status = 'Yes';
    $updateDriverStmt = $conn->prepare("UPDATE driver SET delete_status = ? WHERE id = ?");
    if (!$updateDriverStmt || !$updateDriverStmt->bind_param("si", $delete_status, $driverId) || !$updateDriverStmt->execute()) {
        throw new Exception("Driver deactivation failed: " . ($updateDriverStmt->error ?? 'Unknown'));
    }
    $updateDriverStmt->close();

    // Log deactivation
    $referenceId = bin2hex(random_bytes(16));
    $logStmt = $conn->prepare("INSERT INTO account_deactivation_audit_log 
                              (reference_id, account_id, account_type, action_type, initiated_by, initiated_by_role) 
                              VALUES (?, ?, 'DRIVER', 'DEACTIVATE', ?, ?)");
    if (!$logStmt || !$logStmt->bind_param("ssss", $referenceId, $driverId, $adminId, $adminRole) || !$logStmt->execute()) {
        throw new Exception("Audit log failed: " . ($logStmt->error ?? 'Unknown'));
    }
    $logStmt->close();

    $conn->commit();
    logActivity("SUCCESS: Driver $driverId deactivated by Admin $adminId");
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Account deactivated successfully.']);

} catch (Exception $e) {
    $conn->rollback();
    logActivity("FAILED: Driver $driverId deactivation by Admin $adminId - " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Deactivation failed. Please try again.']);
} finally {
    $conn->close();
}
<?php
header('Content-Type: application/json');

include('restriction_checker.php');
// Validate session
if (!isset($_SESSION['unique_id'])) {
    logActivity("Access denied: No active session from IP: " . $_SERVER['REMOTE_ADDR']);
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Access denied. Please log in first.']));
}

$adminId = $_SESSION['unique_id'];
$adminRole = $_SESSION['role'] ?? 'Unknown';
if ($adminRole !== "Super Admin") {
        $errorMsg = "Unauthorized deletion attempt by user: " . $adminId;
        logActivity($errorMsg);
        echo json_encode(["success" => false, "message" => "You do not have permission to delete."]);
        exit();
}

// Validate input
 $data = json_decode(file_get_contents("php://input"), true);
    if (!isset($data['customer_id']) || !is_numeric($data['customer_id'])) {
        $errorMsg = "Missing customer ID in request";
        logActivity($errorMsg);
        echo json_encode(["success" => false, "message" => "Customer ID is required."]);
        exit();
    }

    $customerId = (int)$data['customer_id'];
    logActivity("Attempting to delete customer ID: " . $customerId);

// === Get current Customer Restriction status ===
$currentStmt = $conn->prepare("SELECT restriction,delete_status FROM customers WHERE customer_id = ?");
if (!$currentStmt || !$currentStmt->bind_param("i", $customerId) || !$currentStmt->execute()) {
    logActivity("DB Error checking Customer Restriction Status: " . ($conn->error ?? 'Unknown'));
    http_response_code(500);
    exit(json_encode(['success' => false, 'message' => 'System error.']));
}

$currentStmt->bind_result($currentRestriction, $currentDeleteStatus);
if (!$currentStmt->fetch()) {
    $currentStmt->close();
    logActivity("Customer Record Not found for Customer ID:  $customerId ");
    http_response_code(404);
    exit(json_encode(['success' => false, 'message' => 'Customer Record Not found.']));
}
$currentStmt->close();

// Validate account state
if ($currentRestriction == 1) {
    logActivity("Attempt to deactivate restricted Customer ID $customerId  by Admin $adminId");
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Cannot deactivate restricted accounts.']));
}
if ($currentDeleteStatus == 'Yes') {
    logActivity("Attempt to deactivate an already Deactivated Account for Customer ID $customerId  by Admin $adminId");
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Unable to Deactivate Account!.']));
}

// Check account lock
$lockStmt = $conn->prepare("SELECT status FROM customer_lock_history WHERE customer_id = ? ORDER BY id DESC LIMIT 1");
if ($lockStmt && $lockStmt->bind_param("i", $customerId ) && $lockStmt->execute()) {
    $lockStmt->bind_result($lockStatus);
    $lockStmt->fetch();
    if ($lockStatus === 'locked') {
        $lockStmt->close();
        logActivity("Attempt to modify locked customer ID $customerId  by Admin $adminId");
        http_response_code(423);
        exit(json_encode(['success' => false, 'message' => 'Unable to Deactivate Locked Account.']));
    }
}
if ($lockStmt) $lockStmt->close();

// === Transaction ===
$conn->begin_transaction();

try {
    // Get active session
    $sessionStmt = $conn->prepare("SELECT session_id FROM customer_active_sessions WHERE customer_id = ? AND status = 'Active'");
    if (!$sessionStmt || !$sessionStmt->bind_param("i", $customerId ) || !$sessionStmt->execute()) {
        throw new Exception("Session check failed: " . ($conn->error ?? 'Unknown'));
    }
    
    $sessionStmt->bind_result($session_id);
    $hasSession = $sessionStmt->fetch();
    $sessionStmt->close();

    // Deactivate session if exists
    if ($hasSession && $session_id) {
        $updateSessionStmt = $conn->prepare("UPDATE customer_active_sessions SET status = 'Inactive' WHERE session_id = ?");
        if (!$updateSessionStmt || !$updateSessionStmt->bind_param("s", $session_id) || !$updateSessionStmt->execute()) {
            throw new Exception("Session deactivation failed: " . ($conn->error ?? 'Unknown'));
        }
        $updateSessionStmt->close();
    }

    // Update customer Delete status
    $delete_status = 'Yes';
    $updateCustomerStmt = $conn->prepare("UPDATE customers SET delete_status = ? WHERE customer_id = ?");
if (!$updateCustomerStmt || !$updateCustomerStmt->bind_param("si", $delete_status, $customerId ) || !$updateCustomerStmt->execute()) {
    throw new Exception("Customer Account deactivation failed: " . ($updateCustomerStmt->error ?? 'Unknown'));
}
$updateCustomerStmt->close();


    // Log deactivation
    $referenceId = bin2hex(random_bytes(16));
    $logStmt = $conn->prepare("INSERT INTO account_deactivation_audit_log 
                              (reference_id, account_id, account_type, action_type, initiated_by, initiated_by_role) 
                              VALUES (?, ?, 'CUSTOMERS', 'DEACTIVATE', ?, ?)");
    if (!$logStmt || !$logStmt->bind_param("ssss", $referenceId, $customerId , $adminId, $adminRole) || !$logStmt->execute()) {
        throw new Exception("Audit log failed: " . ($logStmt->error ?? 'Unknown'));
    }
    $logStmt->close();

    $conn->commit();
    logActivity("SUCCESS: Customer ID:  $customerId  deactivated Successfully by Admin $adminId");
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Account deactivated successfully.']);

} catch (Exception $e) {
    $conn->rollback();
    logActivity("FAILED: Customer ID: $customerId  deactivation by Admin $adminId - " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Deactivation failed. Please try again.']);
} finally {
    $conn->close();
}
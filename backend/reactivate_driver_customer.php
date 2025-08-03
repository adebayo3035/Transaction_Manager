<?php
// Define constants for reuse
define('ALLOWED_ACCOUNT_TYPES', ['customers', 'driver']);
define('ACTION_DEACTIVATE', 'DEACTIVATE');
define('ACTION_REACTIVATE', 'REACTIVATE');
define('REQUIRED_ROLE', 'Super Admin');

// Standardized response function
function jsonResponse($success, $message, $logMessage = null) {
    if ($logMessage) {
        logActivity($logMessage);
    }
    echo json_encode(["success" => $success, "message" => $message]);
    exit();
}

try {
    // Initialize session and database
    include 'config.php';
    include 'auth_utils.php';
    session_start();
    header('Content-Type: application/json');

    // === Session Validation ===
    if (!isset($_SESSION['unique_id'])) {
        jsonResponse(false, "Access Denied! Kindly login first.", 
            "UNAUTHORIZED_ACCESS: Attempt to access Reactivation endpoint without valid session from IP: " . $_SERVER['REMOTE_ADDR']);
    }

    $role = $_SESSION['role'] ?? 'Unknown';
    $adminID = $_SESSION['unique_id'];
    logActivity("SESSION_VALIDATION: User ID $adminID with role $role attempting Account Reactivation action");

    // === Role Validation ===
    if ($role !== REQUIRED_ROLE) {
        jsonResponse(false, "Access Denied! Permission not granted.",
            "PERMISSION_DENIED: User ID $adminID with role $role attempted Super Admin action from IP: " . $_SERVER['REMOTE_ADDR']);
    }

    // === Request Method Validation ===
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, "Invalid request method.",
            "INVALID_METHOD: User ID $adminID used " . $_SERVER['REQUEST_METHOD'] . " on POST-only endpoint");
    }

    // === Input Validation ===
    $data = json_decode(file_get_contents("php://input"), true);
    $userID = $data['userID'] ?? null;
    $accountType = strtolower($data['accountType'] ?? '');
    $providedAnswer = trim($data['secretAnswer'] ?? '');
    $referenceID = $data['reference_id'] ?? null;

    logActivity("INPUT_RECEIVED: User ID $adminID attempting to Reactivate account. UserID: $userID, Type: $accountType, RefID: $referenceID");

    if (!is_numeric($userID) || empty($providedAnswer) || empty($referenceID)) {
        jsonResponse(false, "Missing or invalid input.",
            "INVALID_INPUT: User ID $adminID provided incomplete data. UserID: " . ($userID ?? 'null') . 
            ", Answer: " . (!empty($providedAnswer) ? 'provided' : 'empty') . 
            ", RefID: " . ($referenceID ?? 'null'));
    }

    if (!in_array($accountType, ALLOWED_ACCOUNT_TYPES)) {
        jsonResponse(false, "Invalid account type.",
            "INVALID_ACCOUNT_TYPE: User ID $adminID provided invalid type: $accountType");
    }

    // === Secret Answer Verification ===
    $checkSecret = $conn->prepare("SELECT secret_answer FROM admin_tbl WHERE unique_id = ?");
    $checkSecret->bind_param("i", $adminID);
    $checkSecret->execute();
    $checkSecret->store_result();
    $checkSecret->bind_result($storedHash);

    if ($checkSecret->num_rows === 0) {
        jsonResponse(false, "Admin user not found.",
            "ADMIN_NOT_FOUND: Admin ID $adminID not found in database");
    }

    $checkSecret->fetch();
    $checkSecret->close();

    if (!verifyAndUpgradeSecretAnswer($conn, $adminID, $providedAnswer, $storedHash)) {
              jsonResponse(false, "Invalid secret answer.",
            "SECRET_ANSWER_MISMATCH: Admin ID $adminID provided wrong answer for $accountType ID $userID");
    }

    // === Combined Reference ID and Account Status Validation ===
    $checkSql = "SELECT a.delete_status 
                FROM $accountType a
                JOIN account_deactivation_audit_log l ON l.account_id = a.id
                WHERE a.id = ? 
                AND l.reference_id = ?
                AND l.account_type = ?
                AND l.action_type = ?";
    
    $checkStmt = $conn->prepare($checkSql);
    $accountTypeUpper = strtoupper($accountType);
    $actionDeactivate = ACTION_DEACTIVATE; // Create a variable from the constant
    $checkStmt->bind_param("isss", $userID, $referenceID, $accountTypeUpper, $actionDeactivate);

    $checkStmt->execute();
    $checkStmt->store_result();
    $checkStmt->bind_result($delete_status);

    if ($checkStmt->num_rows === 0) {
        jsonResponse(false, "Invalid reference ID or account not found.",
            "INVALID_REFERENCE_OR_ACCOUNT: RefID $referenceID not found for $accountType ID $userID by Admin ID $adminID");
    }

    $checkStmt->fetch();
    $checkStmt->close();

    if ($delete_status !== 'Yes') {
        jsonResponse(false, "This account is not Deactivated.",
            "NO_DEACTIVATION_FOUND: $accountType ID $userID not DEACTIVATED (Admin ID $adminID)");
    }

    // === Transaction Processing ===
    if ($conn->begin_transaction() === false) {
        jsonResponse(false, "System error. Please try again.",
            "TRANSACTION_FAILED: Could not start transaction for Admin ID $adminID");
    }

    logActivity("TRANSACTION_START: Admin ID $adminID starting reactivation for $accountType ID $userID");

    try {
        // Update delete_status field
        $updateSql = "UPDATE $accountType SET delete_status = NULL WHERE id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("i", $userID);

        if (!$updateStmt->execute()) {
            throw new Exception("DB Update Error: " . $updateStmt->error);
        }
        $updateStmt->close();
        logActivity("ACCOUNT_UPDATED: Admin ID: $adminID REACTIVATED $accountType ID $userID DEACTIVATION");

        // Insert audit trail
        $logQuery = "INSERT INTO account_deactivation_audit_log 
                    (reference_id, account_id, account_type, action_type, initiated_by, initiated_by_role) 
                    VALUES (?, ?, ?, ?, ?, ?)";

        $logStmt = $conn->prepare($logQuery);
        $actionReactivate = ACTION_REACTIVATE;
        $logStmt->bind_param("sissss", $referenceID, $userID, $accountTypeUpper, $actionReactivate, $adminID, $role);

        if (!$logStmt->execute()) {
            throw new Exception("Audit Log Error: " . $logStmt->error);
        }
        $logStmt->close();
        logActivity("AUDIT_LOG_CREATED: Admin ID $adminID created log for $accountType ID $userID RefID $referenceID");

        $conn->commit();
        logActivity("TRANSACTION_SUCCESS: Admin ID $adminID successfully reactivated $accountType ID $userID");
        jsonResponse(true, "Account Reactivated Successfully!");

    } catch (Exception $e) {
        $conn->rollback();
        jsonResponse(false, "Failed to Reactivate Account.",
            "TRANSACTION_FAILED: Admin ID $adminID failed to reactivate $accountType ID $userID. Error: " . $e->getMessage());
    }

} catch (Exception $e) {
    http_response_code(500);
    jsonResponse(false, "A system error occurred",
        "SYSTEM_ERROR: " . $e->getMessage());
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
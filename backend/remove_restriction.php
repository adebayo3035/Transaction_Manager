<?php
include 'config.php';
session_start();
header('Content-Type: application/json');

// === Session Validation ===
if (!isset($_SESSION['unique_id'])) {
    logActivity("UNAUTHORIZED_ACCESS: Attempt to access unrestrict endpoint without valid session from IP: " . $_SERVER['REMOTE_ADDR']);
    echo json_encode(["success" => false, "message" => "Access Denied! Kindly login first."]);
    exit();
}

$role = $_SESSION['role'];
$adminID = $_SESSION['unique_id'];
logActivity("SESSION_VALIDATION: User ID $adminID with role $role attempting unrestrict action");

// === Role Validation ===
if ($role !== "Super Admin") {
    logActivity("PERMISSION_DENIED: User ID $adminID with role $role attempted Super Admin action from IP: " . $_SERVER['REMOTE_ADDR']);
    echo json_encode(["success" => false, "message" => "Access Denied! Permission not granted."]);
    exit();
}

// === Request Method Validation ===
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logActivity("INVALID_METHOD: User ID $adminID used " . $_SERVER['REQUEST_METHOD'] . " on POST-only endpoint");
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit();
}

// === Input Validation ===
$data = json_decode(file_get_contents("php://input"), true);
$userID = $data['userID'] ?? null;
$accountType = $data['accountType'] ?? null;
$providedAnswer = $data['secretAnswer'] ?? '';
$referenceID = $data['reference_id'] ?? null;

logActivity("INPUT_RECEIVED: User ID $adminID attempting to unrestrict account. UserID: $userID, Type: $accountType, RefID: $referenceID");

if (!is_numeric($userID) || empty($providedAnswer) || empty($referenceID)) {
    logActivity("INVALID_INPUT: User ID $adminID provided incomplete data. UserID: " . ($userID ?? 'null') . ", Answer: " . (!empty($providedAnswer) ? 'provided' : 'empty') . ", RefID: " . ($referenceID ?? 'null'));
    echo json_encode(["success" => false, "message" => "Missing or invalid input."]);
    exit();
}

$allowedTables = ['customers', 'driver'];
if (!in_array($accountType, $allowedTables)) {
    logActivity("INVALID_ACCOUNT_TYPE: User ID $adminID provided invalid type: $accountType");
    echo json_encode(["success" => false, "message" => "Invalid account type."]);
    exit();
}

// === Secret Answer Verification ===
$checkSecret = $conn->prepare("SELECT secret_answer FROM admin_tbl WHERE unique_id = ?");
$checkSecret->bind_param("i", $adminID);
$checkSecret->execute();
$checkSecret->store_result();
$checkSecret->bind_result($storedHash);

if ($checkSecret->num_rows === 0) {
    logActivity("ADMIN_NOT_FOUND: Admin ID $adminID not found in database");
    echo json_encode(["success" => false, "message" => "Admin user not found."]);
    exit();
}

$checkSecret->fetch();
$checkSecret->close();

if (md5($providedAnswer) !== $storedHash) {
    logActivity("SECRET_ANSWER_MISMATCH: Admin ID $adminID provided wrong answer for $accountType ID $userID");
    echo json_encode(["success" => false, "message" => "Invalid secret answer."]);
    exit();
}

// === Reference ID Validation ===
$checkRefSql = "SELECT COUNT(*) FROM account_restriction_audit_log 
                WHERE reference_id = ? 
                AND account_id = ? 
                AND account_type = ? 
                AND action_type = 'RESTRICT'";
$checkRefStmt = $conn->prepare($checkRefSql);
$checkRefStmt->bind_param("sis", $referenceID, $userID, $accountType);
$checkRefStmt->execute();
$checkRefStmt->bind_result($count);
$checkRefStmt->fetch();
$checkRefStmt->close();

if ($count === 0) {
    logActivity("INVALID_REFERENCE: RefID $referenceID not found for $accountType ID $userID by Admin ID $adminID");
    echo json_encode(["success" => false, "message" => "Invalid reference ID provided."]);
    exit();
}

// === Current Restriction Check ===
$checkSql = "SELECT restriction FROM $accountType WHERE id = ?";
$checkStmt = $conn->prepare($checkSql);
$checkStmt->bind_param("i", $userID);
$checkStmt->execute();
$checkStmt->store_result();
$checkStmt->bind_result($currentRestriction);

if ($checkStmt->num_rows === 0) {
    logActivity("ACCOUNT_NOT_FOUND: $accountType account ID $userID not found by Admin ID $adminID");
    echo json_encode(["success" => false, "message" => "Account not found."]);
    exit();
}

$checkStmt->fetch();
$checkStmt->close();

if ((int) $currentRestriction === 0) {
    logActivity("NO_RESTRICTION_FOUND: $accountType ID $userID not restricted (Admin ID $adminID)");
    echo json_encode(["success" => false, "message" => "This account is not restricted."]);
    exit();
}

// === Transaction Processing ===
$conn->begin_transaction();
logActivity("TRANSACTION_START: Admin ID $adminID starting unrestrict for $accountType ID $userID");

try {
    // Update restriction field
    $updateSql = "UPDATE $accountType SET restriction = 0 WHERE id = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("i", $userID);

    if (!$updateStmt->execute()) {
        throw new Exception("DB Update Error: " . $updateStmt->error);
    }
    $updateStmt->close();
    logActivity("ACCOUNT_UPDATED: Admin ID $adminID updated $accountType ID $userID restriction");

    // Insert audit trail
    $adminRole = $_SESSION['role'] ?? 'Unknown';
    $logQuery = "INSERT INTO account_restriction_audit_log 
                (reference_id, account_id, account_type, action_type, initiated_by, initiated_by_role) 
                VALUES (?, ?, ?, 'UNRESTRICT', ?, ?)";

    $logStmt = $conn->prepare($logQuery);
    $accountType = strtoupper($accountType);
    $logStmt->bind_param("sisss", $referenceID, $userID, $accountType, $adminID, $adminRole);

    if (!$logStmt->execute()) {
        throw new Exception("Audit Log Error: " . $logStmt->error);
    }
    $logStmt->close();
    logActivity("AUDIT_LOG_CREATED: Admin ID $adminID created log for $accountType ID $userID RefID $referenceID");

    $conn->commit();
    logActivity("TRANSACTION_SUCCESS: Admin ID $adminID successfully unrestricted $accountType ID $userID");
    echo json_encode(["success" => true, "message" => "Restriction successfully removed."]);

} catch (Exception $e) {
    $conn->rollback();
    logActivity("TRANSACTION_FAILED: Admin ID $adminID failed to unrestrict $accountType ID $userID. Error: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Failed to lift restriction."]);
} finally {
    $conn->close();
}
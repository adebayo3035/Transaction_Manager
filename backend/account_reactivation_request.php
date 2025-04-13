<?php
header('Content-Type: application/json');
require_once 'config.php';

$data = json_decode(file_get_contents("php://input"), true);
$email = trim($data['email'] ?? '');
$otp = trim($data['otp'] ?? '');
$reason = trim($data['reason'] ?? '');

// ====== 1. INPUT VALIDATION ======
if (empty($email) || empty($reason) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email address or empty reason field']);
    logActivity("OTP_VALIDATION_FAILED: Invalid email - $email");
    exit;
}

if (empty($otp) || !preg_match('/^\d{6}$/', $otp)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'OTP must be 6 digits']);
    logActivity("OTP_VALIDATION_FAILED: Invalid OTP format for $email");
    exit;
}

// ====== 2. FETCH ADMIN UNIQUE ID ======
$query = "SELECT unique_id FROM admin_tbl WHERE email = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    logActivity("Staff Account cannot be found for Email Address: $email");
    http_response_code(404);
    echo json_encode(["error" => "Staff record cannot be found"]);
    exit;
}
$unique_id = $user['unique_id'];

// ====== 3. CHECK OTP VALIDITY ======
$query = "
    SELECT id, otp, expires_at, status 
    FROM admin_otp_request 
    WHERE unique_id = ? 
    AND status = 'pending'
    ORDER BY created_at DESC 
    LIMIT 1
";
logActivity("OTP_VALIDATION_QUERY: $query");
logActivity("OTP_VALIDATION_PARAMS: unique_id=$unique_id");

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $unique_id);
$stmt->execute();
$result = $stmt->get_result();
$otpRecord = $result->fetch_assoc();

logActivity("OTP_VALIDATION_RESULT: " . json_encode($otpRecord));

if (!$otpRecord) {
    http_response_code(404);
    $response = ['success' => false, 'message' => 'No pending OTP found for this email'];
    echo json_encode($response);
    logActivity("OTP_VALIDATION_FAILED: No pending OTP for $email | " . json_encode($response));
    exit;
}

$currentTime = date('Y-m-d H:i:s', strtotime('-1 hour'));
$expiryTime = $otpRecord['expires_at'];
$otpId = $otpRecord['id'];

logActivity("OTP_TIME_COMPARISON: current=$currentTime | expiry=$expiryTime | server_timezone=" . date_default_timezone_get());

if (strtotime($expiryTime) < strtotime($currentTime)) {
    $updateQuery = "UPDATE admin_otp_request SET status = 'expired' WHERE id = ?";
    logActivity("OTP_EXPIRY_UPDATE_QUERY: $updateQuery");

    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("i", $otpId);
    $stmt->execute();
    logActivity("OTP_EXPIRY_UPDATE_RESULT: affected_rows=" . $stmt->affected_rows);

    http_response_code(400);
    $response = ['success' => false, 'message' => 'OTP expired, kindly generate another OTP'];
    echo json_encode($response);
    logActivity("OTP_VALIDATION_FAILED: Expired OTP for $unique_id | " . json_encode($response) . " | OTP_ID=$otpId");
    exit;
}

logActivity("OTP_VALIDATION_SUCCESS: Valid OTP found for $unique_id | OTP_ID=$otpId");

if (!password_verify($otp, $otpRecord['otp'])) {
    logActivity("OTP_MISMATCH_DETAILS: " . json_encode([
        'otp_id' => $otpId,
        'input_otp' => $otp,
        'hashed_otp' => $otpRecord['otp']
    ]));

    http_response_code(401);
    $response = ['success' => false, 'message' => 'Invalid OTP'];
    echo json_encode($response);
    logActivity("OTP_VALIDATION_FAILED: Incorrect OTP for $unique_id | " . json_encode($response));

    updateOtpStatus($conn, $otpId, 'failed', "Failed OTP validation attempt");
    exit;
}

// ====== 4. CHECK USER ACCOUNT STATUS ======
$stmt = $conn->prepare("SELECT id, delete_status, block_id FROM admin_tbl WHERE unique_id = ?");
$stmt->bind_param("s", $unique_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

logActivity("USER_STATUS_RESULT: " . json_encode($user ?? 'null'));

if (!$user) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Account not found']);
    logActivity("OTP_VALIDATION_FAILED: User not found - $email and $unique_id");
    updateOtpStatus($conn, $otpId, 'failed', 'Account Not Found');
    exit;
}

if ($user['block_id'] != 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Account is blocked. Contact support.']);
    logActivity("ACCOUNT_BLOCKED: " . json_encode([
        'user_id' => $user['id'],
        'block_id' => $user['block_id'],
        'email' => $email
    ]));
    updateOtpStatus($conn, $otpId, 'used', "User account has been blocked");
    exit;
}

if ($user['delete_status'] != 'Yes') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Account is already active']);
    logActivity("OTP_VALIDATION_FAILED: Account already active - $email");
    updateOtpStatus($conn, $otpId, 'used', "Account is Active and not Deactivated");
    exit;
}

// ====== 5. PROCESS OTP & CREATE REACTIVATION REQUEST ======
logActivity("TRANSACTION_START: Beginning OTP verification transaction");
$conn->begin_transaction();

try {
    logActivity("TRANSACTION_STEP: Checking for existing reactivation requests");
    $stmt = $conn->prepare("
        SELECT id 
        FROM admin_reactivation_logs
        WHERE admin_id = ? 
        AND status = 'Pending'
        AND date_created > NOW() - INTERVAL 24 HOUR
        LIMIT 1
    ");
    $stmt->bind_param("i", $unique_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $existingRequest = $result->fetch_assoc();
        logActivity("DUPLICATE_REQUEST_FOUND: " . json_encode($existingRequest));
        updateOtpStatus($conn, $otpId, 'failed', "Duplicate reactivation request found");
        throw new Exception("User already has a pending reactivation request (ID: {$existingRequest['id']})");
    }

    // Step 2: Get the latest deactivation log ID for this admin
$deactivationStmt = $conn->prepare("
SELECT id 
FROM admin_deactivation_logs 
WHERE admin_id = ? 
ORDER BY id DESC 
LIMIT 1
");
$deactivationStmt->bind_param("i", $unique_id);
$deactivationStmt->execute();
$deactivationResult = $deactivationStmt->get_result();
$deactivationLog = $deactivationResult->fetch_assoc();

if (!$deactivationLog) {
throw new Exception("Deactivation log not found for this admin.");
}

$deactivation_log_id = $deactivationLog['id']; // Get the ID from the deactivation log

    logActivity("TRANSACTION_STEP: Creating new reactivation request");
    $accountType = 'Admin';
    $stmt = $conn->prepare("
        INSERT INTO admin_reactivation_logs
        (deactivation_log_id, admin_id, reactivation_reason, status, date_created)
        VALUES (?, ?, ?, 'Pending', NOW())
    ");
    $stmt->bind_param("iis", $deactivation_log_id, $unique_id, $reason);
    $stmt->execute();

    $reactivationRequestId = $conn->insert_id;
    logActivity("REACTIVATION_CREATED: RequestID=$reactivationRequestId");

    updateOtpStatus($conn, $otpId, 'verified', "Successfully verified OTP for reactivation");
    logActivity("OTP_VERIFIED: OTP_ID=$otpId");

    $conn->commit();
    logActivity("TRANSACTION_COMPLETE: Successfully committed all changes");

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Reactivation request submitted for admin approval',
        'request_id' => $reactivationRequestId
    ]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to process reactivation request',
        'error' => $e->getMessage()
    ]);
    updateOtpStatus($conn, $otpId, 'failed', "System Error.");
    logActivity("REACTIVATION_FAILED: {$e->getMessage()} for $email");
    exit;
}

/**
 * Updates the status of an OTP record
 *
 * @param mysqli $conn
 * @param int $otpId
 * @param string $newStatus
 * @param string $usage_description
 * @return bool
 */
function updateOtpStatus($conn, $otpId, $newStatus, $usage_description) {
    $allowedStatuses = ['pending', 'verified', 'expired', 'failed', 'used'];
    if (!in_array($newStatus, $allowedStatuses)) {
        logActivity("Invalid OTP status: $newStatus");
        return false;
    }

    if (!is_numeric($otpId) || $otpId <= 0) {
        logActivity("Invalid OTP ID: $otpId");
        return false;
    }

    try {
        $conn->begin_transaction();

        $stmt = $conn->prepare("SELECT status FROM admin_otp_request WHERE id = ?");
        $stmt->bind_param("i", $otpId);
        $stmt->execute();
        $result = $stmt->get_result();
        $currentStatus = $result->fetch_assoc()['status'] ?? null;

        if (!$currentStatus) {
            throw new Exception("OTP record not found for ID: $otpId");
        }

        $updateStmt = $conn->prepare("
            UPDATE admin_otp_request 
            SET status = ?, expires_at = NOW(), usage_description = ?
            WHERE id = ?
        ");
        $updateStmt->bind_param("ssi", $newStatus, $usage_description, $otpId);
        $updateStmt->execute();

        if ($updateStmt->affected_rows === 0) {
            throw new Exception("No rows affected - OTP may not exist");
        }

        logActivity("OTP_STATUS_CHANGE: ID = $otpId | FROM = $currentStatus | TO = $newStatus");
        $conn->commit();
        return true;

    } catch (Exception $e) {
        $conn->rollback();
        logActivity("OTP_STATUS_UPDATE_FAILED: " . $e->getMessage() . " | OTP_ID=$otpId");
        return false;
    }
}

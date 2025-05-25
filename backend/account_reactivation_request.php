<?php
header('Content-Type: application/json');
require 'config.php';
require_once 'verify_otp.php';

// Get input data
$data = json_decode(file_get_contents("php://input"), true);
$email = trim($data['email'] ?? '');
$otp = trim($data['otp'] ?? '');
$reason = trim($data['reason'] ?? '');

// ====== 1. INPUT VALIDATION ======
if (empty($email) || empty($reason) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email address or empty reason field']);
    logActivity("REACTIVATION_FAILED: Invalid input for $email");
    exit;
}

if (empty($otp) || !preg_match('/^\d{6}$/', $otp)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid OTP format (must be 6 digits)']);
    logActivity("REACTIVATION_FAILED: Invalid OTP for $email");
    exit;
}

// ====== 2. OTP VERIFICATION ======
$otpVerifier = new OTPVerification($conn);
$otpCheck = $otpVerifier->verifyOTP('admin', $email, $otp, 'Admin Account Re-activation');

if (!$otpCheck['success']) {
    http_response_code($otpCheck['code']);
    echo json_encode($otpCheck);
    logActivity("REACTIVATION_FAILED: OTP verification failed for $email - " . $otpCheck['message']);
    exit;
}

$user_id = $otpCheck['user_id']; // Get the admin's unique ID from OTP verification

// ====== 3. ADMIN ACCOUNT VERIFICATION ======
$stmt = $conn->prepare("SELECT unique_id, id, delete_status, block_id FROM admin_tbl WHERE id = ? and email = ?");
$stmt->bind_param("ss", $user_id, $email);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();

if (!$admin) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Admin account not found']);
    logActivity("REACTIVATION_FAILED: Admin with $user_id and $email Cannot be found");
    exit;
}
$admin_id = $admin['unique_id'];

if ($admin['block_id'] != 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Your account is blocked. Contact support.']);
    logActivity("REACTIVATION_FAILED: Admin $admin_id is blocked");
    exit;
}

if ($admin['delete_status'] != 'Yes') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Your account is already active']);
    logActivity("REACTIVATION_FAILED: Admin $admin_id account already active");
    exit;
}

// ====== 4. PROCESS REACTIVATION REQUEST ======
$conn->begin_transaction();

try {
    logActivity("REACTIVATION_ATTEMPT: Starting reactivation process for admin $admin_id");

    // Check for existing pending requests
    $stmt = $conn->prepare("
        SELECT id FROM admin_reactivation_logs
        WHERE admin_id = ? AND status = 'Pending'
        LIMIT 1
    ");
    $stmt->bind_param("s", $admin_id);
    $stmt->execute();
    $pendingResult = $stmt->get_result();

    if ($pendingResult->num_rows > 0) {
        logActivity("REACTIVATION_BLOCKED: Admin $admin_id already has a pending reactivation request");
        throw new Exception("You already have a pending reactivation request");
    }
    logActivity("REACTIVATION_VALIDATED: No existing pending request for admin $admin_id");

    // Check if last request was declined within last 24 hours
    $stmt = $conn->prepare("
        SELECT id, date_created 
        FROM admin_reactivation_logs
        WHERE admin_id = ? AND status = 'Declined'
        ORDER BY date_created DESC
        LIMIT 1
    ");
    $stmt->bind_param("s", $admin_id);
    $stmt->execute();
    $lastDeclined = $stmt->get_result()->fetch_assoc();

    if ($lastDeclined) {
        $declinedTime = strtotime($lastDeclined['date_created']);
        $now = time();
        if ($declinedTime > ($now - 86400)) {
            $nextRequestTime = date('Y-m-d H:i:s', $declinedTime + 86400);
            logActivity("REACTIVATION_BLOCKED: Admin $admin_id's last request was declined at {$lastDeclined['date_created']}. Must wait until $nextRequestTime");
            throw new Exception("Your last request was declined. You can submit a new request after $nextRequestTime");
        }
        logActivity("REACTIVATION_VALIDATED: Last declined request for admin $admin_id was more than 24 hours ago");
    } else {
        logActivity("REACTIVATION_VALIDATED: No previous declined request found for admin $admin_id");
    }

    // Get the latest deactivation log
    $stmt = $conn->prepare("
        SELECT id, deactivated_by FROM admin_deactivation_logs
        WHERE admin_id = ? AND status = 'Deactivated' 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $stmt->bind_param("s", $admin_id);
    $stmt->execute();
    $deactivationLog = $stmt->get_result()->fetch_assoc();
    $deactivation_log_id = $deactivationLog['id'] ?? null;

    logActivity("REACTIVATION_LOG_FOUND: Latest deactivation log ID for admin $admin_id is " . ($deactivation_log_id ?? 'NULL'));

    // Create new reactivation request
    $stmt = $conn->prepare("
        INSERT INTO admin_reactivation_logs
        (deactivation_log_id, admin_id, reactivation_reason, status, date_created)
        VALUES (?, ?, ?, 'Pending', NOW())
    ");
    $stmt->bind_param("iss", $deactivation_log_id, $admin_id, $reason);
    $stmt->execute();
    $requestId = $conn->insert_id;

    logActivity("REACTIVATION_CREATED: Reactivation request $requestId submitted for admin $admin_id");

    // Notify super admin
    $title = "Admin Account Reactivation Request";
    $eventDetails = "Admin $admin_id requested account reactivation on " . date('Y-m-d H:i:s');
    sendAdminNotification($conn, $title, "Account Reactivation", $eventDetails, $admin_id, "Reactivation request created");
    logActivity("REACTIVATION_NOTIFICATION_SENT: Notification sent to Super Admin for request $requestId");

    $conn->commit();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Reactivation request submitted successfully',
        'request_id' => $requestId
    ]);
    logActivity("REACTIVATION_SUCCESS: Request $requestId successfully committed for admin $admin_id");

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    logActivity("REACTIVATION_FAILED: Transaction rolled back for admin $admin_id - " . $e->getMessage());
}

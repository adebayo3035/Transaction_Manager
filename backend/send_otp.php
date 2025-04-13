<?php
require 'sendOTPGmail.php'; // Include PHPMailer function
require 'config.php'; // Database connection

header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true);
$email = trim($data['email']);
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    logActivity("Invalid Request method sent from client");
    exit();
}
if (!isset($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    logActivity("OTP_FAILED: Invalid email input - $email");
    exit;
}
$otp = rand(100000, 999999); // Generate 6-digit OTP
// $expires_at = date('Y-m-d H:i:s', strtotime('+2 minutes'));

// Get admin Unique ID uing the email address
$query = "SELECT unique_id  FROM admin_tbl WHERE email = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    logActivity("Staff Account cannot be found for Email Address: $email");
    http_response_code(404);
    // echo json_encode(["error" => "Staff record cannot be found"]);
    echo json_encode(['success' => false, 'message' => 'Staff Record Cannot be found.']);
    exit;
}
$unique_id = $user['unique_id'];


// ====== 2. RATE LIMITING ======
$ip = $_SERVER['REMOTE_ADDR'];
$maxAttempts = 3;
$timeWindow = 300; // 5 minutes in seconds

$stmt = $conn->prepare("
    SELECT COUNT(*) AS attempts 
    FROM admin_otp_request 
    WHERE unique_id = ? AND created_at > (NOW() - INTERVAL $timeWindow SECOND)
");
$stmt->bind_param("s", $unique_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if ($row['attempts'] >= $maxAttempts) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many OTP requests. Please wait.']);
    logActivity("OTP_RATE_LIMIT: Too many requests for $email from IP $ip");
    exit;
}

// ====== 3. CHECK EXISTING UNEXPIRED OTP ======
$stmt = $conn->prepare("
    SELECT id, expires_at 
    FROM admin_otp_request 
    WHERE unique_id = ? AND status = 'pending' AND expires_at > NOW()
    ORDER BY created_at DESC 
    LIMIT 1
");
$stmt->bind_param("i", $unique_id);
$stmt->execute();
$result = $stmt->get_result();
$existingOtp = $result->fetch_assoc();

if ($existingOtp) {
    // Option 1: Resend the same OTP (extend expiry)
    // Option 2: Block new OTP until expiry
    http_response_code(200); // or 429 if blocking
    echo json_encode(['success' => true, 'message' => 'An active OTP already exists. Check your email.']);
    logActivity("OTP_EXISTS: Active OTP found for $email");
    exit;
}

$otpHashed = password_hash($otp, PASSWORD_BCRYPT); // Store hashed
$expiresAt = date('Y-m-d H:i:s', strtotime('+2 minutes')); // Expires in 15 mins

// ====== 5. GENERATE, SEND AND STORE OTP IN DATABASE ======
// Start transaction
$conn->begin_transaction();

try {
    // ====== 1. STORE OTP IN DATABASE ======
    $stmt = $conn->prepare("
        INSERT INTO admin_otp_request (unique_id, email, otp, expires_at, status)
        VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 2 MINUTE), 'pending')
    ");
    $stmt->bind_param("iss", $unique_id, $email, $otpHashed);
    $stmt->execute();

    if ($stmt->affected_rows <= 0) {
        throw new Exception("Database insert failed");
    }
    // $subject = "OTP for Staff Account Re-activation";
    // $body = "Your OTP for Account Re-activation is: $otp. It expires in 2 minutes.";  // OTP message
    // $status = sendEmailWithGmailSMTP($email, $body, $subject);
    // // ====== 2. SEND OTP VIA EMAIL ======
    // if (!($status)) {
    //     throw new Exception("Email send failed");
    // }
    // Commit if both succeed
    $conn->commit();
    echo json_encode(['success' => true, 'message' => $otp]);
    logActivity("OTP_SUCCESS: OTP has been generated and sent to $email");

} catch (Exception $e) {
    // Rollback on any failure
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to process OTP']);
    logActivity("OTP_FAILURE: " . $e->getMessage() . " for $email");
    exit;
}



<?php
include_once "config.php";
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

// Log function assumed to be available globally
// logActivity($action, $status, $description, $email = null)

function validatePassword($password) {
    return strlen($password) >= 8 &&
        preg_match('/[!@#$%^&*(),.?":{}|<>_]/', $password) &&
        preg_match('/[A-Z]/', $password) &&
        preg_match('/\d/', $password);
}

function checkResetAttempts($conn, $email) {
    $stmt = $conn->prepare("SELECT reset_attempts FROM admin_password_reset_attempts WHERE email = ? AND DATE(last_attempt_date) = CURDATE()");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if ($row['reset_attempts'] >= 3) {
            logActivity("Password Reset Attempt Failed. Exceeded daily reset limit for email: $email");
            return ['success' => false, 'message' => 'You have exceeded the reset attempts for today.'];
        }
    }

    return ['success' => true];
}

function checkLockStatus($conn, $unique_id) {
    $stmt = $conn->prepare("SELECT attempts, locked_until FROM admin_login_attempts WHERE unique_id = ?");
    $stmt->bind_param("i", $unique_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $attempts = $row['attempts'];
        $locked_until = new DateTime($row['locked_until']);
        $now = new DateTime();

        if ($attempts >= 3 && $now < $locked_until) {
            $remaining = $locked_until->diff($now)->format('%i minutes %s seconds');
            logActivity("Password Reset Attempt Failed Account locked. Try again in $remaining");
            return ['success' => false, 'message' => "Your account is locked. Try again in $remaining."];
        }
    }

    return ['success' => true];
}

function updateResetAttempts($conn, $email) {
    $stmt = $conn->prepare("SELECT reset_attempts FROM admin_password_reset_attempts WHERE email = ? AND DATE(last_attempt_date) = CURDATE()");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $attempts = $row['reset_attempts'] + 1;
        $stmt = $conn->prepare("UPDATE admin_password_reset_attempts SET reset_attempts = ?, last_attempt_date = NOW() WHERE email = ?");
        $stmt->bind_param("is", $attempts, $email);
    } else {
        $attempts = 1;
        $stmt = $conn->prepare("INSERT INTO admin_password_reset_attempts (email, reset_attempts, last_attempt_date) VALUES (?, ?, NOW())");
        $stmt->bind_param("si", $email, $attempts);
    }
    $stmt->execute();
    $stmt->close();
    logActivity("Reset Attempt Count Update. Success: Reset attempts updated to $attempts for email $email");
}

function updatePassword($conn, $email, $hashedPassword) {
    $stmt = $conn->prepare("UPDATE admin_tbl SET password = ? WHERE email = ?");
    $stmt->bind_param("ss", $hashedPassword, $email);

    if ($stmt->execute()) {
        $stmtReset = $conn->prepare("UPDATE admin_password_reset_attempts SET reset_attempts = 0, last_attempt_date = NOW() WHERE email = ?");
        $stmtReset->bind_param("s", $email);
        $stmtReset->execute();
        $stmtReset->close();
        logActivity("Password Reset Success: Password reset completed $email");
        return ['success' => true, 'message' => 'Password reset successfully.'];
    } else {
        logActivity("Password Reset Failed: Database update failed for Email: $email");
        return ['success' => false, 'message' => 'Failed to update the password.'];
    }
}

if (!isset($data['email'], $data['password'], $data['confirmPassword'], $data['secret_answer'])) {
    logActivity("Password Reset Failed Missing required fields");
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit();
}

$email = mysqli_real_escape_string($conn, $data['email']);
$password = $data['password'];
$confirmPassword = $data['confirmPassword'];
$secret_answer = $data['secret_answer'];

if (!validatePassword($password)) {
    logActivity("Password Reset Failed Weak password for Email: $email");
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long and include uppercase letters, digits, and special characters.']);
    exit();
}

$resetCheck = checkResetAttempts($conn, $email);
if (!$resetCheck['success']) {
    echo json_encode($resetCheck);
    exit();
}

$stmt = $conn->prepare("SELECT unique_id, secret_answer FROM admin_tbl WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    logActivity("Password Reset Failed Email not found for E-mail: $email");
    echo json_encode(['success' => false, 'message' => 'Your record cannot be found.']);
    exit();
}

$stmt->bind_result($unique_id, $db_secret_answer);
$stmt->fetch();

$lockCheck = checkLockStatus($conn, $unique_id);
if (!$lockCheck['success']) {
    echo json_encode($lockCheck);
    exit();
}

if ((md5($secret_answer) !== $db_secret_answer) || ($password !== $confirmPassword)) {
    updateResetAttempts($conn, $email);
    logActivity("Password Reset Failed Secret answer mismatch or passwords do not match: $email");
    echo json_encode(['success' => false, 'message' => 'Staff validation failed.']);
    exit();
}

$hashedPassword = md5($password);
echo json_encode(updatePassword($conn, $email, $hashedPassword));
$conn->close();

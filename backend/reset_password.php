<?php
// Include database connection
include_once "config.php";

// Set content type to JSON
header('Content-Type: application/json');

// Get the POST data
$data = json_decode(file_get_contents("php://input"), true);

// Function to validate password
function validatePassword($password) {
    $minLength = 8;
    $hasSpecialChar = preg_match('/[!@#$%^&*(),.?":{}|<>_]/', $password);
    $hasUpperCase = preg_match('/[A-Z]/', $password);
    $hasDigit = preg_match('/\d/', $password);

    return strlen($password) >= $minLength && $hasSpecialChar && $hasUpperCase && $hasDigit;
}

// Function to check reset attempts for today
function checkResetAttempts($conn, $email) {
    $stmt = $conn->prepare("SELECT reset_attempts, last_attempt_date FROM admin_password_reset_attempts WHERE email = ? AND DATE(last_attempt_date) = CURDATE()");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if ($row['reset_attempts'] >= 3) {
            return ['success' => false, 'message' => 'You have exceeded the reset attempts for today.'];
        }
    }
    return ['success' => true];
}

// Function to check if the user is locked
function checkLockStatus($conn, $unique_id) {
    $stmt = $conn->prepare("SELECT attempts, locked_until FROM admin_login_attempts WHERE unique_id = ?");
    $stmt->bind_param("i", $unique_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $max_attempts = 3;
    $lockout_duration = 60; // lockout time in minutes
    $current_time = new DateTime();

    if ($result->num_rows > 0) {
        $attempt_data = $result->fetch_assoc();
        $attempts = $attempt_data['attempts'];
        $locked_until = new DateTime($attempt_data['locked_until']);

        if ($attempts >= $max_attempts && $current_time < $locked_until) {
            $time_remaining = $locked_until->diff($current_time)->format('%i minutes %s seconds');
            return ['success' => false, 'message' => "Your account is locked. Please try again in $time_remaining."];
        }
    }
    return ['success' => true];
}

// Function to update or reset reset_attempts
function updateResetAttempts($conn, $email) {
    $stmt = $conn->prepare("SELECT * FROM admin_password_reset_attempts WHERE email = ? AND DATE(last_attempt_date) = CURDATE()");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        $attempt = 1;
        $stmtInsert = $conn->prepare("INSERT INTO admin_password_reset_attempts (email, reset_attempts, last_attempt_date) VALUES (?, ?, NOW())");
        $stmtInsert->bind_param("si", $email, $attempt);
        $stmtInsert->execute();
        $stmtInsert->close();
    } else {
        $row = $result->fetch_assoc();
        $new_attempt = $row['reset_attempts'] + 1;

        $stmtUpdate = $conn->prepare("UPDATE admin_password_reset_attempts SET reset_attempts = ?, last_attempt_date = NOW() WHERE email = ? AND DATE(last_attempt_date) = CURDATE()");
        $stmtUpdate->bind_param("is", $new_attempt, $email);
        $stmtUpdate->execute();
        $stmtUpdate->close();
    }
}

// Function to update password
function updatePassword($conn, $email, $hashedPassword) {
    $stmt = $conn->prepare("UPDATE admin_tbl SET password = ? WHERE email = ?");
    $stmt->bind_param("ss", $hashedPassword, $email);

    if ($stmt->execute()) {
        $stmtReset = $conn->prepare("UPDATE admin_password_reset_attempts SET reset_attempts = 0, last_attempt_date = NOW() WHERE email = ? AND DATE(last_attempt_date) = CURDATE()");
        $stmtReset->bind_param("s", $email);
        $stmtReset->execute();
        $stmtReset->close();
        return ['success' => true, 'message' => 'Password reset successfully.'];
    } else {
        return ['success' => false, 'message' => 'Failed to update the password.'];
    }
}

// Validate input data
if (isset($data['email'], $data['password'], $data['secret_answer'], $data['confirmPassword'])) {
    $email = mysqli_real_escape_string($conn, $data['email']);
    $password = mysqli_real_escape_string($conn, $data['password']);
    $confirmPassword = mysqli_real_escape_string($conn, $data['confirmPassword']);
    $secret_answer = mysqli_real_escape_string($conn, $data['secret_answer']);

    // Validate password strength
    if (!validatePassword($password)) {
        echo json_encode(['success' => false, 'message' => 'Please input a valid password.']);
        exit();
    }

    // Check reset attempts for today
    $resetAttemptCheck = checkResetAttempts($conn, $email);
    if (!$resetAttemptCheck['success']) {
        echo json_encode($resetAttemptCheck);
        exit();
    }

    // Check if the user is locked
    $emailQuery = $conn->prepare("SELECT unique_id, secret_answer FROM admin_tbl WHERE email = ?");
    $emailQuery->bind_param("s", $email);
    $emailQuery->execute();
    $emailQuery->store_result();

    if ($emailQuery->num_rows > 0) {
        $emailQuery->bind_result($unique_id, $db_secret_answer);
        $emailQuery->fetch();

        $lockCheck = checkLockStatus($conn, $unique_id);
        if (!$lockCheck['success']) {
            echo json_encode($lockCheck);
            exit();
        }

        // Validate secret answer and confirm password
        if ((md5($secret_answer) !== $db_secret_answer) || ($password !== $confirmPassword)) {
            updateResetAttempts($conn, $email);
            echo json_encode(['success' => false, 'message' => 'Staff Validation Failed.']);
            exit();
        }

        // Update password
        $hashedPassword = md5($password);
        echo json_encode(updatePassword($conn, $email, $hashedPassword));

    } else {
        echo json_encode(['success' => false, 'message' => 'Your Record cannot be found.']);
        exit();
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit();
}

// Close the database connection
$conn->close();

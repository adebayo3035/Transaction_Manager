<?php
// Include database connection
include_once "config.php";

// Set content type to JSON
header('Content-Type: application/json');

// Get the POST data
$data = json_decode(file_get_contents("php://input"), true);

// Function to check reset attempts for today
function checkResetAttempts($conn, $email) {
    $stmt = $conn->prepare("SELECT reset_attempts, last_attempt_date FROM customer_password_reset_attempts WHERE email = ? AND DATE(last_attempt_date) = CURDATE()");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if ($row['reset_attempts'] >= 3) {
            logActivity("Exceeded reset attempts for email: $email.");
            return ['success' => false, 'message' => 'You have exceeded the reset attempts for today.'];
        }
    }
    return ['success' => true];
}

// Function to check if the user is locked
function checkLockStatus($conn, $customer_id) {
    $stmt = $conn->prepare("SELECT attempts, locked_until FROM customer_login_attempts WHERE customer_id = ?");
    $stmt->bind_param("i", $customer_id);
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
            logActivity("Account locked for customer_id: $customer_id. Lockout time: $time_remaining.");
            return ['success' => false, 'message' => "Your account is locked. Please try again in $time_remaining."];
        }
    }
    return ['success' => true];
}

// Function to update or reset reset_attempts
function updateResetAttempts($conn, $email) {
    $stmt = $conn->prepare("SELECT * FROM customer_password_reset_attempts WHERE email = ? AND DATE(last_attempt_date) = CURDATE()");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        $attempt = 1;
        $stmtInsert = $conn->prepare("INSERT INTO customer_password_reset_attempts (email, reset_attempts, last_attempt_date) VALUES (?, ?, NOW())");
        $stmtInsert->bind_param("si", $email, $attempt);
        $stmtInsert->execute();
        $stmtInsert->close();
        logActivity("First reset attempt for email: $email.");
    } else {
        $row = $result->fetch_assoc();
        $new_attempt = $row['reset_attempts'] + 1;

        $stmtUpdate = $conn->prepare("UPDATE customer_password_reset_attempts SET reset_attempts = ?, last_attempt_date = NOW() WHERE email = ? AND DATE(last_attempt_date) = CURDATE()");
        $stmtUpdate->bind_param("is", $new_attempt, $email);
        $stmtUpdate->execute();
        $stmtUpdate->close();
        logActivity("Updated reset attempts for email: $email to $new_attempt.");
    }
}

// Validate input data
if (isset($data['email'], $data['secret_answer'])) {
    $email = mysqli_real_escape_string($conn, $data['email']);
    $secret_answer = mysqli_real_escape_string($conn, $data['secret_answer']);

    // Log function to log activity
logActivity("Starting Password Reset for Customer ID: $email");

    // Check reset attempts for today
    $resetAttemptCheck = checkResetAttempts($conn, $email);
    if (!$resetAttemptCheck['success']) {
        echo json_encode($resetAttemptCheck);
        exit();
    }

    // Check if the user exists
    $emailQuery = $conn->prepare("SELECT customer_id, secret_answer FROM customers WHERE email = ?");
    $emailQuery->bind_param("s", $email);
    $emailQuery->execute();
    $emailQuery->store_result();

    if ($emailQuery->num_rows > 0) {
        $emailQuery->bind_result($customer_id, $db_secret_answer);
        $emailQuery->fetch();

        logActivity("Email found: $email. Validating secret answer...");

        $lockCheck = checkLockStatus($conn, $customer_id);
        if (!$lockCheck['success']) {
            echo json_encode($lockCheck);
            exit();
        }

        // Validate secret answer and confirm password
        if ((md5($secret_answer) !== $db_secret_answer)) {
            updateResetAttempts($conn, $email);
            logActivity("Invalid secret answer for email: $email.");
            echo json_encode(['success' => false, 'message' => 'Account Validation Failed.']);
            exit();
        }

        // Successful validation - Generate token
        $token = bin2hex(random_bytes(16)); // Secure random token
        $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes')); // Token expiration

        // Store the token
        $stmtToken = $conn->prepare("INSERT INTO customer_password_reset_tokens (email, token, expires_at) VALUES (?, ?, ?)");
        $stmtToken->bind_param("sss", $email, $token, $expires_at);
        $stmtToken->execute();
        $stmtToken->close();

        logActivity("Password reset token generated for email: $email.");
        
        // Return success and token
        echo json_encode(['success' => true, 'token' => $token]);
    } else {
        logActivity("No customer found with email: $email.");
        echo json_encode(['success' => false, 'message' => 'Your Record cannot be found.']);
        exit();
    }
} else {
    logActivity("Missing required fields: email or secret_answer.");
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit();
}

// Close the database connection
$conn->close();


<?php
header('Content-Type: application/json');
include 'config.php';

// Function to validate password
function validatePassword($password)
{
    $minLength = 8;
    $hasSpecialChar = preg_match('/[!@#$%^&*(),.?":{}|<>_]/', $password);
    $hasUpperCase = preg_match('/[A-Z]/', $password);
    $hasDigit = preg_match('/\d/', $password);

    return strlen($password) >= $minLength && $hasSpecialChar && $hasUpperCase && $hasDigit;
}

// Function to update password
function updatePassword($conn, $email, $hashedPassword)
{
    // Start transaction to ensure atomicity
    $conn->begin_transaction();

    // Prepare the SQL for updating the password
    $stmt = $conn->prepare("UPDATE customers SET password = ? WHERE email = ?");

    if (!$stmt) {
        return ['success' => false, 'message' => 'Failed to prepare the statement for updating password.'];
    }

    $stmt->bind_param("ss", $hashedPassword, $email);

    if ($stmt->execute()) {
        $stmt->close();

        // Reset customer password reset attempts
        $stmtReset = $conn->prepare("UPDATE customer_password_reset_attempts SET reset_attempts = 0, last_attempt_date = NOW() WHERE email = ? AND DATE(last_attempt_date) = CURDATE()");
        if (!$stmtReset) {
            $conn->rollback();
            return ['success' => false, 'message' => 'Failed to prepare the statement for resetting attempts.'];
        }

        $stmtReset->bind_param("s", $email);
        $stmtReset->execute();
        $stmtReset->close();

        // Delete the token after successful password reset
        $stmtDeleteToken = $conn->prepare("DELETE FROM customer_password_reset_tokens WHERE email = ?");
        if (!$stmtDeleteToken) {
            $conn->rollback();
            return ['success' => false, 'message' => 'Failed to prepare the statement for deleting token.'];
        }

        $stmtDeleteToken->bind_param("s", $email);
        $stmtDeleteToken->execute();
        $stmtDeleteToken->close();

        // Commit the transaction if everything is successful
        $conn->commit();
        return ['success' => true, 'message' => 'Password reset successfully.'];
    } else {
        $conn->rollback();
        return ['success' => false, 'message' => 'Failed to update the password.'];
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Decode JSON input
    $data = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
        exit();
    }

    // Check required fields
    if (empty($data['email']) || empty($data['new_password']) || empty($data['confirm_password']) || empty($data['token'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        exit();
    }

    $email = $conn->real_escape_string($data['email']);
    $new_password = $conn->real_escape_string($data['new_password']);
    $confirm_password = $conn->real_escape_string($data['confirm_password']);
    $token = $conn->real_escape_string($data['token']);

    // Validate token
    $stmt = $conn->prepare("SELECT expires_at FROM customer_password_reset_tokens WHERE email = ? AND token = ?");
    $stmt->bind_param("ss", $email, $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Check if the token has expired
        if (strtotime($row['expires_at']) > time()) {
            // Token is valid, proceed with password reset
            if (!validatePassword($new_password)) {
                echo json_encode(['success' => false, 'message' => 'Please input a valid password.']);
                exit();
            }

            if ($new_password !== $confirm_password) {
                echo json_encode(['success' => false, 'message' => 'Your Password does not match.']);
                exit();
            }

            $hashedPassword = md5($new_password);

            // Call updatePassword function
            $updateResult = updatePassword($conn, $email, $hashedPassword);
            echo json_encode($updateResult);
            exit();
        } else {
            echo json_encode(['success' => false, 'message' => 'Token has expired.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid token.']);
    }

    $stmt->close();
}

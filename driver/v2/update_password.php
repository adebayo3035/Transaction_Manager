<?php
header('Content-Type: application/json');
include 'config.php';

// Function to validate password
function validatePassword($password) {
    // Log function entry
    logActivity("Entering validatePassword function.");

    $minLength = 8;
    $hasSpecialChar = preg_match('/[!@#$%^&*(),.?":{}|<>_]/', $password);
    $hasUpperCase = preg_match('/[A-Z]/', $password);
    $hasDigit = preg_match('/\d/', $password);

    // Log validation results
    logActivity("Password validation results - Length: " . (strlen($password) >= $minLength ? "Valid" : "Invalid") .
                ", Special Character: " . ($hasSpecialChar ? "Valid" : "Invalid") .
                ", Uppercase Letter: " . ($hasUpperCase ? "Valid" : "Invalid") .
                ", Digit: " . ($hasDigit ? "Valid" : "Invalid"));

    $isValid = strlen($password) >= $minLength && $hasSpecialChar && $hasUpperCase && $hasDigit;

    // Log function exit
    logActivity("Exiting validatePassword function. Password is " . ($isValid ? "valid" : "invalid") . ".");
    return $isValid;
}

// Function to update password
function updatePassword($conn, $email, $hashedPassword) {
    // Log function entry
    logActivity("Entering updatePassword function for email: $email.");

    // Start transaction to ensure atomicity
    $conn->begin_transaction();
    logActivity("Transaction started for email: $email.");

    // Prepare the SQL for updating the password
    $queryUpdatePassword = "UPDATE driver SET password = ? WHERE email = ?";
    $stmt = $conn->prepare($queryUpdatePassword);

    if (!$stmt) {
        // Log SQL preparation failure
        logActivity("Failed to prepare SQL statement for updating password: $queryUpdatePassword.");
        return ['success' => false, 'message' => 'Failed to prepare the statement for updating password.'];
    }

    // Log SQL query and parameters
    logActivity("Executing SQL query: $queryUpdatePassword | Params: [$hashedPassword, $email]");

    $stmt->bind_param("ss", $hashedPassword, $email);

    if ($stmt->execute()) {
        $stmt->close();
        logActivity("Password updated successfully for email: $email.");

        // Reset driver password reset attempts
        $queryResetAttempts = "UPDATE driver_password_reset_attempts SET reset_attempts = 0, last_attempt_date = NOW() WHERE email = ? AND DATE(last_attempt_date) = CURDATE()";
        $stmtReset = $conn->prepare($queryResetAttempts);

        if (!$stmtReset) {
            // Log SQL preparation failure
            logActivity("Failed to prepare SQL statement for resetting attempts: $queryResetAttempts.");
            $conn->rollback();
            logActivity("Transaction rolled back due to failure in resetting attempts.");
            return ['success' => false, 'message' => 'Failed to prepare the statement for resetting attempts.'];
        }

        // Log SQL query and parameters
        logActivity("Executing SQL query: $queryResetAttempts | Params: [$email]");

        $stmtReset->bind_param("s", $email);
        $stmtReset->execute();
        $stmtReset->close();
        logActivity("Reset attempts updated successfully for email: $email.");

        // Delete the token after successful password reset
        $queryDeleteToken = "DELETE FROM driver_password_reset_tokens WHERE email = ?";
        $stmtDeleteToken = $conn->prepare($queryDeleteToken);

        if (!$stmtDeleteToken) {
            // Log SQL preparation failure
            logActivity("Failed to prepare SQL statement for deleting token: $queryDeleteToken.");
            $conn->rollback();
            logActivity("Transaction rolled back due to failure in deleting token.");
            return ['success' => false, 'message' => 'Failed to prepare the statement for deleting token.'];
        }

        // Log SQL query and parameters
        logActivity("Executing SQL query: $queryDeleteToken | Params: [$email]");

        $stmtDeleteToken->bind_param("s", $email);
        $stmtDeleteToken->execute();
        $stmtDeleteToken->close();
        logActivity("Token deleted successfully for email: $email.");

        // Commit the transaction if everything is successful
        $conn->commit();
        logActivity("Transaction committed successfully for email: $email.");
        return ['success' => true, 'message' => 'Password reset successfully.'];
    } else {
        // Log SQL execution failure
        logActivity("Failed to execute SQL statement for updating password.");
        $conn->rollback();
        logActivity("Transaction rolled back due to failure in updating password.");
        return ['success' => false, 'message' => 'Failed to update the password.'];
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Log function entry
    logActivity("Entering password reset request handler.");

    // Decode JSON input
    $data = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        // Log JSON decoding error
        logActivity("Invalid JSON input received.");
        echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
        exit();
    }

    // Log JSON input
    logActivity("JSON input received: " . json_encode($data));

    // Check required fields
    if (empty($data['email']) || empty($data['new_password']) || empty($data['confirm_password']) || empty($data['token'])) {
        // Log missing required fields
        logActivity("Missing required fields in the request.");
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        exit();
    }

    $email = $conn->real_escape_string($data['email']);
    $new_password = $conn->real_escape_string($data['new_password']);
    $confirm_password = $conn->real_escape_string($data['confirm_password']);
    $token = $conn->real_escape_string($data['token']);

    // Log sanitized input data
    logActivity("Sanitized input data - Email: $email, New Password: [hidden], Confirm Password: [hidden], Token: $token");

    // Validate token
    $query = "SELECT expires_at FROM driver_password_reset_tokens WHERE email = ? AND token = ?";
    $stmt = $conn->prepare($query);

    if ($stmt === false) {
        // Log SQL preparation failure
        logActivity("Failed to prepare SQL statement for token validation.");
        echo json_encode(['success' => false, 'message' => 'Failed to prepare SQL statement.']);
        exit();
    }

    // Log SQL query and parameters
    logActivity("Executing SQL query: $query | Params: [$email, $token]");

    $stmt->bind_param("ss", $email, $token);
    if (!$stmt->execute()) {
        // Log SQL execution failure
        logActivity("Failed to execute SQL statement for token validation.");
        echo json_encode(['success' => false, 'message' => 'Failed to execute SQL statement.']);
        exit();
    }

    $result = $stmt->get_result();

    // Log the number of rows returned
    logActivity("Number of rows returned: " . $result->num_rows);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Log the retrieved expiry time
        logActivity("Token expiry time: " . $row['expires_at']);

        // Check if the token has expired
        if (strtotime($row['expires_at']) > time()) {
            // Log token validation success
            logActivity("Token is valid for email: $email.");

            // Token is valid, proceed with password reset
            if (!validatePassword($new_password)) {
                // Log password validation failure
                logActivity("Password validation failed for email: $email.");
                echo json_encode(['success' => false, 'message' => 'Please input a valid password.']);
                exit();
            }

            if ($new_password !== $confirm_password) {
                // Log password mismatch
                logActivity("Password mismatch for email: $email.");
                echo json_encode(['success' => false, 'message' => 'Your Password does not match.']);
                exit();
            }

            $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);

            // Log password hashing
            logActivity("Password hashed successfully for email: $email.");

            // Call updatePassword function
            $updateResult = updatePassword($conn, $email, $hashedPassword);

            // Log password update result
            logActivity("Password update result: " . json_encode($updateResult));

            echo json_encode($updateResult);
            exit();
        } else {
            // Log token expiry
            logActivity("Token has expired for email: $email.");
            echo json_encode(['success' => false, 'message' => 'Token has expired.']);
            exit();
        }
    } else {
        // Log invalid token
        logActivity("Invalid token for email: $email.");
        echo json_encode(['success' => false, 'message' => 'Invalid token.']);
        exit();
    }

}
$stmt->close();

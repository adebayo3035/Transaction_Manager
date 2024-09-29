<?php
header('Content-Type: application/json');
include 'config.php'; // Include your database configuration

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Decode the JSON input
    $data = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
        exit;
    }

    // Retrieve and sanitize input data
    $email = $conn->real_escape_string($data['email']);
    $secret_answer = $conn->real_escape_string($data['secret_answer']);
    $hashedSecretAnswer = md5($secret_answer); // Hash secret answer
    $today = date('Y-m-d');

    // Check if the user has exceeded the reset attempt limit for today
    $stmtCheckAttempts = $conn->prepare("SELECT reset_attempts, last_attempt_date FROM driver_password_reset_attempts WHERE email = ? AND DATE(last_attempt_date) = CURDATE()");
    $stmtCheckAttempts->bind_param("s", $email);
    $stmtCheckAttempts->execute();
    $resultCheckAttempts = $stmtCheckAttempts->get_result();

    if ($resultCheckAttempts->num_rows > 0) {
        $rowAttempts = $resultCheckAttempts->fetch_assoc();
        
        if ($rowAttempts['reset_attempts'] >= 3) {
            echo json_encode(['success' => false, 'message' => 'You have exceeded the reset attempts for today.']);
            exit;
        }
    }

    // Proceed with secret answer validation
    $stmt = $conn->prepare("SELECT secret_answer FROM driver WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if ($row['secret_answer'] !== $hashedSecretAnswer) {
            echo json_encode(['success' => false, 'message' => 'Information Validation Unsuccessful.']);
            // Update or insert the reset attempt count logic here...
            $stmt = $conn->prepare("SELECT * FROM driver_password_reset_attempts WHERE email = ? AND DATE(last_attempt_date) = CURDATE()");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 0) {
                // No reset attempt for today, insert new record
                $attempt = 1;
                $stmtInsert = $conn->prepare("INSERT INTO driver_password_reset_attempts (email, reset_attempts, last_attempt_date) VALUES (?, ?, NOW())");
                $stmtInsert->bind_param("si", $email, $attempt);
                $stmtInsert->execute();
                $stmtInsert->close();
            } else {
                // Reset attempt exists for today, update the attempts count
                $row = $result->fetch_assoc();
                $reset_attempts = $row['reset_attempts'];
                $new_attempt = $reset_attempts + 1;

                $stmtResetAttempts = $conn->prepare("UPDATE driver_password_reset_attempts SET reset_attempts = ?, last_attempt_date = NOW() WHERE email = ? AND DATE(last_attempt_date) = CURDATE()");
                $stmtResetAttempts->bind_param("is", $new_attempt, $email);
                $stmtResetAttempts->execute();
                $stmtResetAttempts->close();
            }
            $stmt->close();
            exit;

        } else {
            // Successful validation
            // Generate a unique token
            $token = bin2hex(random_bytes(16)); // Secure random token
            $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes')); // Token expires in 15 minutes

            // Store the token in the database along with the expiration time
            $stmtToken = $conn->prepare("INSERT INTO driver_password_reset_tokens (email, token, expires_at) VALUES (?, ?, ?)");
            $stmtToken->bind_param("sss", $email, $token, $expires_at);
            $stmtToken->execute();
            $stmtToken->close();

            echo json_encode(['success' => true, 'token' => $token]);

            // Reset the reset_attempts to 0 on successful validation
            $stmtResetAttempts = $conn->prepare("UPDATE driver_password_reset_attempts SET reset_attempts = 0, last_attempt_date = NOW() WHERE email = ? AND DATE(last_attempt_date) = CURDATE()");
            $stmtResetAttempts->bind_param("s", $email);
            $stmtResetAttempts->execute();
            $stmtResetAttempts->close();
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Email not found.']);
    }

    $stmt->close();
    $stmtCheckAttempts->close();
}


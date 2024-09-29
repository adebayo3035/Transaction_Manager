<?php
// Include database connection
include_once "config.php";

// Set content type to JSON
header('Content-Type: application/json');

// Get the POST data
$data = json_decode(file_get_contents("php://input"), true);

// Check if all required fields are provided
if (isset($data['email'], $data['password'], $data['secret_answer'], $data['confirmPassword'])) {
    $email = mysqli_real_escape_string($conn, $data['email']);
    $password = mysqli_real_escape_string($conn, $data['password']);
    $confirmPassword = mysqli_real_escape_string($conn, $data['confirmPassword']);
    $secret_answer = mysqli_real_escape_string($conn, $data['secret_answer']);

    // REGEX TO VALIDATE PASSWORD AND SECRET ANSWER
    $minLength = 8;
    $hasSpecialChar = preg_match('/[!@#$%^&*(),.?":{}|<>_]/', $password);
    $hasUpperCase = preg_match('/[A-Z]/', $password);
    $hasDigit = preg_match('/\d/', $password);

    // Validate password strength
    if (strlen($password) < $minLength || !$hasSpecialChar || !$hasUpperCase || !$hasDigit) {
        echo json_encode(['success' => false, 'message' => 'Please input a valid password.']);
        exit();
    }
    $today = date('Y-m-d');

    // Check if the user has exceeded the reset attempt limit for today
    $stmtCheckAttempts = $conn->prepare("SELECT reset_attempts, last_attempt_date FROM admin_password_reset_attempts WHERE email = ? AND DATE(last_attempt_date) = CURDATE()");
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

    // Step 1: Validate secret answer and email address
    $emailQuery = $conn->prepare("SELECT secret_answer FROM admin_tbl WHERE email = ?");
    $emailQuery->bind_param("s", $email);
    $emailQuery->execute();
    $emailQuery->store_result();

    if ($emailQuery->num_rows > 0) {
        // Secret answer exists in the database
        $emailQuery->bind_result($db_secret_answer);
        $emailQuery->fetch();

        // Step 2: Validate the secret answer (compare with md5 hash)
        if ((md5($secret_answer) !== $db_secret_answer) || ($password !== $confirmPassword)) {
            echo json_encode(['success' => false, 'message' => 'Staff Validation Failed.']);
            // Update or insert the reset attempt count logic here...
            $stmt = $conn->prepare("SELECT * FROM admin_password_reset_attempts WHERE email = ? AND DATE(last_attempt_date) = CURDATE()");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows == 0) {
                // No reset attempt for today, insert new record
                $attempt = 1;
                $stmtInsert = $conn->prepare("INSERT INTO admin_password_reset_attempts (email, reset_attempts, last_attempt_date) VALUES (?, ?, NOW())");
                $stmtInsert->bind_param("si", $email, $attempt);
                $stmtInsert->execute();
                $stmtInsert->close();
                exit();
            } else {
                // Reset attempt exists for today, update the attempts count
                $row = $result->fetch_assoc();
                $reset_attempts = $row['reset_attempts'];
                $new_attempt = $reset_attempts + 1;

                $stmtResetAttempts = $conn->prepare("UPDATE admin_password_reset_attempts SET reset_attempts = ?, last_attempt_date = NOW() WHERE email = ? AND DATE(last_attempt_date) = CURDATE()");
                $stmtResetAttempts->bind_param("is", $new_attempt, $email);
                $stmtResetAttempts->execute();
                $stmtResetAttempts->close();
            }
            $stmt->close();
            exit;
        } else {
            // Step 4: Hash the new password (using password_hash)
            $hashedPassword = md5($password);
            // Step 5: Update the password in the database
            $updateQuery = $conn->prepare("UPDATE admin_tbl SET password = ? WHERE email = ?");
            $updateQuery->bind_param("ss", $hashedPassword, $email);

            if ($updateQuery->execute()) {
                // Reset the reset_attempts to 0 on successful validation
                $stmtResetAttempts = $conn->prepare("UPDATE admin_password_reset_attempts SET reset_attempts = 0, last_attempt_date = NOW() WHERE email = ? AND DATE(last_attempt_date) = CURDATE()");
                $stmtResetAttempts->bind_param("s", $email);
                $stmtResetAttempts->execute();
                $stmtResetAttempts->close();
                echo json_encode(['success' => true, 'message' => 'Password reset successfully.']);
                exit();
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update the password.']);
                
            }
            $updateQuery->close();
        }

    } else {
        // Email not found
        echo json_encode(['success' => false, 'message' => 'Your Record cannot be found.']);
        exit();
    }

    $emailQuery->close();
} else {
    // Missing required fields
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit();
}

// Close the database connection
$conn->close();


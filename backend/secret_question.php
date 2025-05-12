<?php
include 'config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? null;
    $password = $input['password'] ?? null;

    if (!$email || !$password) {
        echo json_encode(['success' => false, 'message' => 'Email and password are required']);
        exit();
    }

    $max_attempts = 3;
    $lockout_duration = 15; // minutes

    $stmt = $conn->prepare("SELECT unique_id, secret_question, password FROM admin_tbl WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $admin = $result->fetch_assoc();
        $unique_id = $admin['unique_id'];
        $hashedPassword = $admin['password'];
        $secret_question = $admin['secret_question'];

        $stmtCheck = $conn->prepare("SELECT attempts, locked_until FROM admin_login_attempts WHERE unique_id = ? LIMIT 1");
        $stmtCheck->bind_param("s", $unique_id);
        $stmtCheck->execute();
        $resultCheck = $stmtCheck->get_result();

        $current_time = new DateTime();

        if ($resultCheck->num_rows > 0) {
            $lockRow = $resultCheck->fetch_assoc();
            $attempts = $lockRow['attempts'];
            $locked_until = new DateTime($lockRow['locked_until']);

            if ($attempts >= $max_attempts && $current_time < $locked_until) {
                $interval = $current_time->diff($locked_until);
                $remaining = $interval->format('%i minutes %s seconds');
                logActivity("Login attempt blocked: $email is locked.");
                echo json_encode(['success' => false, 'message' => "Your account is locked. Try again in $remaining."]);
                exit();
            }
        }

        if (md5($password) === $hashedPassword) {
            // Reset login attempts on success
            $stmtReset = $conn->prepare("DELETE FROM admin_login_attempts WHERE unique_id = ?");
            $stmtReset->bind_param("s", $unique_id);
            $stmtReset->execute();

            logActivity("Login successful for $email.");
            echo json_encode(['success' => true, 'secret_question' => $secret_question]);
        } else {
            // Update or insert login attempt
            if ($resultCheck->num_rows > 0) {
                $newAttempts = $attempts + 1;
                $lockedUntilTime = ($newAttempts >= $max_attempts)
                    ? $current_time->modify("+$lockout_duration minutes")->format('Y-m-d H:i:s')
                    : null;

                $stmtUpdate = $conn->prepare("UPDATE admin_login_attempts SET attempts = ?, locked_until = ? WHERE unique_id = ?");
                $stmtUpdate->bind_param("iss", $newAttempts, $lockedUntilTime, $unique_id);
                $stmtUpdate->execute();
            } else {
                $initialAttempts = 1;
                $stmtInsert = $conn->prepare("INSERT INTO admin_login_attempts (unique_id, attempts, locked_until) VALUES (?, ?, NULL)");
                $stmtInsert->bind_param("si", $unique_id, $initialAttempts);
                $stmtInsert->execute();
            }

            logActivity("Invalid password attempt for $email.");
            echo json_encode(['success' => false, 'message' => 'Invalid password. Please try again.']);
        }
    } else {
        logActivity("Login failed: User with email $email not found.");
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();

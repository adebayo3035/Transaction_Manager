<?php
// Database connection
include 'config.php'; // Replace with your actual database connection

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve the JSON body from the request
    $input = json_decode(file_get_contents('php://input'), true);

    // Retrieve the inputs (email/phone and password)
    $email = $input['email'] ?? null;
    $password = $input['password'] ?? null;

    // Validate input fields
    if (!$email || !$password) {
        echo json_encode(['success' => false, 'message' => 'Email and password are required']);
        exit();
    }

    // Define maximum attempts and lockout duration (in minutes)
    $max_attempts = 3;
    $lockout_duration = 15; // in minutes

    // Check if the user exists
    $stmt = $conn->prepare("SELECT id, secret_question, password FROM driver WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $driver_id = $row['id'];
        $hashedPassword = $row['password'];
        $secret_question = $row['secret_question'];

        // Check if the account is locked
        $stmtCheckLock = $conn->prepare("SELECT attempts, locked_until FROM driver_login_attempts WHERE driver_id = ? LIMIT 1");
        $stmtCheckLock->bind_param('s', $driver_id);
        $stmtCheckLock->execute();
        $resultCheckLock = $stmtCheckLock->get_result();

        if ($resultCheckLock->num_rows > 0) {
            $rowLock = $resultCheckLock->fetch_assoc();
            $attempts = $rowLock['attempts'];
            $locked_until = new DateTime($rowLock['locked_until']);
            $current_time = new DateTime();

            // Check if the account is currently locked
            if ($attempts >= $max_attempts && $current_time < $locked_until) {
                $time_remaining = $locked_until->diff($current_time)->format('%i minutes %s seconds');
                echo json_encode(['success' => false, 'message' => "Your account is locked. Please try again in $time_remaining."]);
                exit();
            }
        }

        // Verify the password
        $verifyPassword = password_verify($password, $hashedPassword);
        if($verifyPassword) { 
            // Return the secret question since the account is not locked and the password is correct
            echo json_encode([
                'success' => true,
                'secret_question' => $secret_question
            ]);
        } else {
            // Password is incorrect
            echo json_encode(['success' => false, 'message' => 'Invalid password. Please try again.']);
        }
    } else {
        // User not found
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

// Close database connection
$conn->close();

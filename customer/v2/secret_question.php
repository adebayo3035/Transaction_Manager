<?php
// Database connection
include 'config.php'; // Replace with your actual database connection
include 'auth_utils.php';
header('Content-Type: application/json');

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve the JSON body from the request
    $input = json_decode(file_get_contents('php://input'), true);
    logActivity("Received login attempt: " . json_encode($input));

    // Retrieve the inputs (email/phone and password)
    $email = $input['email'] ?? null;
    $password = $input['password'] ?? null;

    // Validate input fields
    if (!$email || !$password) {
        logActivity("Login failed: Email and password are required");
        echo json_encode(['success' => false, 'message' => 'Email and password are required']);
        exit();
    }

    // Define maximum attempts and lockout duration (in minutes)
    $max_attempts = 3;
    $lockout_duration = 15; // in minutes

    // Check if the user exists
    $stmt = $conn->prepare("SELECT customer_id, secret_question, password FROM customers WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $customer_id = $row['customer_id'];
        $hashedPassword = $row['password'];
        $secret_question = $row['secret_question'];

        logActivity("User found: Customer ID $customer_id");

        // Check if the account is locked
        $stmtCheckLock = $conn->prepare("SELECT attempts, locked_until FROM customer_login_attempts WHERE customer_id = ? LIMIT 1");
        $stmtCheckLock->bind_param('s', $customer_id);
        $stmtCheckLock->execute();
        $resultCheckLock = $stmtCheckLock->get_result();

        if ($resultCheckLock->num_rows > 0) {
            $rowLock = $resultCheckLock->fetch_assoc();
            $attempts = $rowLock['attempts'];
            $locked_until = new DateTime($rowLock['locked_until']);
            $current_time = new DateTime();

            if ($attempts >= $max_attempts && $current_time < $locked_until) {
                $time_remaining = $locked_until->diff($current_time)->format('%i minutes %s seconds');
                logActivity("Account locked: Customer ID $customer_id - Time remaining: $time_remaining");
                echo json_encode(['success' => false, 'message' => "Your account is locked. Please try again in $time_remaining."]);
                exit();
            }
        }

         if (!verifyAndUpgradePassword($conn, $customer_id, $password, $hashedPassword)) {
            logActivity("Invalid password attempt: Customer ID $customer_id");
            echo json_encode(['success' => false, 'message' => 'Invalid password. Please try again.']);
            exit();
        }
        else{
            logActivity("Secret Question retrieved successfully for: Customer ID $customer_id");
            echo json_encode([
                'success' => true,
                'secret_question' => $secret_question
            ]);
        }

        // Verify the password
        // if (md5($password) == $hashedPassword) { // Adjust hashing algorithm if needed
        //     logActivity("Secret Question retrieved successfully for: Customer ID $customer_id");
        //     echo json_encode([
        //         'success' => true,
        //         'secret_question' => $secret_question
        //     ]);
        // } else {
        //     logActivity("Invalid password attempt: Customer ID $customer_id");
        //     echo json_encode(['success' => false, 'message' => 'Invalid password. Please try again.']);
        // }
    } else {
        logActivity("Login failed: User not found - Email: $email");
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
} else {
    logActivity("Invalid request method attempted");
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

// Close database connection
$conn->close();

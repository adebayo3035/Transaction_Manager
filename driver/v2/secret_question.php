<?php
// Database connection
include 'config.php'; // Replace with your actual database connection

// Log function entry
logActivity("Entering login request handler.");

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve the JSON body from the request
    $input = json_decode(file_get_contents('php://input'), true);

    // Log JSON input
    logActivity("JSON input received: " . json_encode($input));

    // Retrieve the inputs (email/phone and password)
    $email = $input['email'] ?? null;
    $password = $input['password'] ?? null;

    // Log input data
    logActivity("Input data - Email: $email, Password: [hidden]");

    // Validate input fields
    if (!$email || !$password) {
        // Log missing input fields
        logActivity("Missing required fields: Email or Password.");
        echo json_encode(['success' => false, 'message' => 'Email and password are required']);
        exit();
    }

    // Define maximum attempts and lockout duration (in minutes)
    $max_attempts = 3;
    $lockout_duration = 15; // in minutes

    // Check if the user exists
    $query = "SELECT id, secret_question, password FROM driver WHERE email = ? LIMIT 1";
    $stmt = $conn->prepare($query);

    if ($stmt === false) {
        // Log SQL preparation failure
        logActivity("Failed to prepare SQL statement for fetching driver details.");
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit();
    }

    // Log SQL query and parameters
    logActivity("Executing SQL query: $query | Params: [$email]");

    $stmt->bind_param("s", $email);
    if (!$stmt->execute()) {
        // Log SQL execution failure
        logActivity("Failed to execute SQL statement for fetching driver details.");
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit();
    }

    $result = $stmt->get_result();

    // Log the number of rows returned
    logActivity("Number of rows returned: " . $result->num_rows);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $driver_id = $row['id'];
        $hashedPassword = $row['password'];
        $secret_question = $row['secret_question'];

        // Log driver details fetched
        logActivity("Driver details fetched for driver ID: $driver_id.");

        // Check if the account is locked
        $queryCheckLock = "SELECT attempts, locked_until FROM driver_login_attempts WHERE driver_id = ? LIMIT 1";
        $stmtCheckLock = $conn->prepare($queryCheckLock);

        if ($stmtCheckLock === false) {
            // Log SQL preparation failure
            logActivity("Failed to prepare SQL statement for checking account lock status.");
            echo json_encode(['success' => false, 'message' => 'Database error']);
            exit();
        }

        // Log SQL query and parameters
        logActivity("Executing SQL query: $queryCheckLock | Params: [$driver_id]");

        $stmtCheckLock->bind_param('s', $driver_id);
        if (!$stmtCheckLock->execute()) {
            // Log SQL execution failure
            logActivity("Failed to execute SQL statement for checking account lock status.");
            echo json_encode(['success' => false, 'message' => 'Database error']);
            exit();
        }

        $resultCheckLock = $stmtCheckLock->get_result();

        // Log the number of rows returned
        logActivity("Number of rows returned for lock status check: " . $resultCheckLock->num_rows);

        if ($resultCheckLock->num_rows > 0) {
            $rowLock = $resultCheckLock->fetch_assoc();
            $attempts = $rowLock['attempts'];
            $locked_until = new DateTime($rowLock['locked_until']);
            $current_time = new DateTime();

            // Log lock status details
            logActivity("Lock status details - Attempts: $attempts, Locked Until: " . $rowLock['locked_until']);

            // Check if the account is currently locked
            if ($attempts >= $max_attempts && $current_time < $locked_until) {
                $time_remaining = $locked_until->diff($current_time)->format('%i minutes %s seconds');

                // Log account locked
                logActivity("Account locked for driver ID: $driver_id. Time remaining: $time_remaining.");
                echo json_encode(['success' => false, 'message' => "Your account is locked. Please try again in $time_remaining."]);
                exit();
            }
        }

        // Verify the password
        $verifyPassword = password_verify($password, $hashedPassword);
        if ($verifyPassword) {
            // Log password verification success
            logActivity("Password verification successful for driver ID: $driver_id.");

            // Return the secret question since the account is not locked and the password is correct
            echo json_encode([
                'success' => true,
                'secret_question' => $secret_question
            ]);
        } else {
            // Log password verification failure
            logActivity("Password verification failed for driver ID: $driver_id.");

            // Password is incorrect
            echo json_encode(['success' => false, 'message' => 'Invalid password. Please try again.']);
        }
    } else {
        // Log user not found
        logActivity("User not found for email: $email.");

        // User not found
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
} else {
    // Log invalid request method
    logActivity("Invalid request method received.");
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

// Close database connection
$conn->close();

// Log function exit
logActivity("Exiting login request handler.");
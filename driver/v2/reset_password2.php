<?php
// Include database connection
include_once "config.php";

// Set content type to JSON
header('Content-Type: application/json');

// Get the POST data
$data = json_decode(file_get_contents("php://input"), true);

// Function to check reset attempts for today
function checkResetAttempts($conn, $email) {
    // Log function entry
    logActivity("Entering checkResetAttempts function for email: $email.");

    // Prepare the SQL query
    $query = "SELECT reset_attempts, last_attempt_date FROM driver_password_reset_attempts WHERE email = ? AND DATE(last_attempt_date) = CURDATE()";
    $stmt = $conn->prepare($query);

    // Log SQL query and parameters
    logActivity("Executing SQL query: $query | Params: [$email]");

    if ($stmt === false) {
        // Log SQL preparation failure
        logActivity("Failed to prepare SQL statement for checkResetAttempts.");
        return ['success' => false, 'message' => 'Database error'];
    }

    // Bind parameters and execute the query
    $stmt->bind_param("s", $email);
    if (!$stmt->execute()) {
        // Log SQL execution failure
        logActivity("Failed to execute SQL statement for checkResetAttempts.");
        return ['success' => false, 'message' => 'Database error'];
    }

    // Get the result
    $result = $stmt->get_result();

    // Log the number of rows returned
    logActivity("Number of rows returned: " . $result->num_rows);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Log the retrieved data
        logActivity("Retrieved data: reset_attempts = " . $row['reset_attempts'] . ", last_attempt_date = " . $row['last_attempt_date']);

        // Check if reset attempts have been exceeded
        if ($row['reset_attempts'] >= 3) {
            // Log reset attempts exceeded
            logActivity("Reset attempts exceeded for email: $email.");
            return ['success' => false, 'message' => 'You have exceeded the reset attempts for today.'];
        }
    } else {
        // Log no rows found
        logActivity("No reset attempts found for email: $email.");
    }

    // Log function exit with success
    logActivity("Exiting checkResetAttempts function with success for email: $email.");
    return ['success' => true];
}

function checkLockStatus($conn, $driver_id) {
    // Log function entry
    logActivity("Entering checkLockStatus function for driver_id: $driver_id.");

    // Prepare the SQL query
    $query = "SELECT attempts, locked_until FROM driver_login_attempts WHERE driver_id = ?";
    $stmt = $conn->prepare($query);

    // Log SQL query and parameters
    logActivity("Executing SQL query: $query | Params: [$driver_id]");

    if ($stmt === false) {
        // Log SQL preparation failure
        logActivity("Failed to prepare SQL statement for checkLockStatus.");
        return ['success' => false, 'message' => 'Database error'];
    }

    // Bind parameters and execute the query
    $stmt->bind_param("i", $driver_id);
    if (!$stmt->execute()) {
        // Log SQL execution failure
        logActivity("Failed to execute SQL statement for checkLockStatus.");
        return ['success' => false, 'message' => 'Database error'];
    }

    // Get the result
    $result = $stmt->get_result();

    // Log the number of rows returned
    logActivity("Number of rows returned: " . $result->num_rows);

    $max_attempts = 3;
    $lockout_duration = 60; // lockout time in minutes
    $current_time = new DateTime();

    if ($result->num_rows > 0) {
        $attempt_data = $result->fetch_assoc();

        // Log the retrieved data
        logActivity("Retrieved data: attempts = " . $attempt_data['attempts'] . ", locked_until = " . $attempt_data['locked_until']);

        $attempts = $attempt_data['attempts'];
        $locked_until = new DateTime($attempt_data['locked_until']);

        if ($attempts >= $max_attempts && $current_time < $locked_until) {
            $time_remaining = $locked_until->diff($current_time)->format('%i minutes %s seconds');

            // Log account locked status
            logActivity("Account locked for driver_id: $driver_id. Time remaining: $time_remaining.");
            return ['success' => false, 'message' => "Your account is locked. Please try again in $time_remaining."];
        }
    } else {
        // Log no rows found
        logActivity("No lock attempts found for driver_id: $driver_id.");
    }

    // Log function exit with success
    logActivity("Exiting checkLockStatus function with success for driver_id: $driver_id.");
    return ['success' => true];
}

// Function to check if the user is locked
function updateResetAttempts($conn, $email) {
    // Log function entry
    logActivity("Entering updateResetAttempts function for email: $email.");

    // Prepare the SQL query
    $query = "SELECT * FROM driver_password_reset_attempts WHERE email = ? AND DATE(last_attempt_date) = CURDATE()";
    $stmt = $conn->prepare($query);

    // Log SQL query and parameters
    logActivity("Executing SQL query: $query | Params: [$email]");

    if ($stmt === false) {
        // Log SQL preparation failure
        logActivity("Failed to prepare SQL statement for updateResetAttempts.");
        return;
    }

    // Bind parameters and execute the query
    $stmt->bind_param("s", $email);
    if (!$stmt->execute()) {
        // Log SQL execution failure
        logActivity("Failed to execute SQL statement for updateResetAttempts.");
        return;
    }

    // Get the result
    $result = $stmt->get_result();

    // Log the number of rows returned
    logActivity("Number of rows returned: " . $result->num_rows);

    if ($result->num_rows == 0) {
        // Insert a new record
        $attempt = 1;
        $queryInsert = "INSERT INTO driver_password_reset_attempts (email, reset_attempts, last_attempt_date) VALUES (?, ?, NOW())";
        $stmtInsert = $conn->prepare($queryInsert);

        // Log SQL query and parameters for insert
        logActivity("Executing SQL query: $queryInsert | Params: [$email, $attempt]");

        if ($stmtInsert === false) {
            // Log SQL preparation failure for insert
            logActivity("Failed to prepare SQL statement for insert in updateResetAttempts.");
            return;
        }

        $stmtInsert->bind_param("si", $email, $attempt);
        if (!$stmtInsert->execute()) {
            // Log SQL execution failure for insert
            logActivity("Failed to execute SQL statement for insert in updateResetAttempts.");
            return;
        }
        $stmtInsert->close();

        // Log successful insert
        logActivity("Inserted new reset attempt record for email: $email.");
    } else {
        // Update an existing record
        $row = $result->fetch_assoc();
        $new_attempt = $row['reset_attempts'] + 1;

        $queryUpdate = "UPDATE driver_password_reset_attempts SET reset_attempts = ?, last_attempt_date = NOW() WHERE email = ? AND DATE(last_attempt_date) = CURDATE()";
        $stmtUpdate = $conn->prepare($queryUpdate);

        // Log SQL query and parameters for update
        logActivity("Executing SQL query: $queryUpdate | Params: [$new_attempt, $email]");

        if ($stmtUpdate === false) {
            // Log SQL preparation failure for update
            logActivity("Failed to prepare SQL statement for update in updateResetAttempts.");
            return;
        }

        $stmtUpdate->bind_param("is", $new_attempt, $email);
        if (!$stmtUpdate->execute()) {
            // Log SQL execution failure for update
            logActivity("Failed to execute SQL statement for update in updateResetAttempts.");
            return;
        }
        $stmtUpdate->close();

        // Log successful update
        logActivity("Updated reset attempt record for email: $email. New attempt count: $new_attempt.");
    }

    // Log function exit
    logActivity("Exiting updateResetAttempts function for email: $email.");
}

// Log function entry
logActivity("Entering password reset validation block.");

// Validate input data
if (isset($data['email'], $data['secret_answer'])) {
    $email = mysqli_real_escape_string($conn, $data['email']);
    $secret_answer = mysqli_real_escape_string($conn, $data['secret_answer']);

    // Log input data
    logActivity("Input data received - Email: $email, Secret Answer: [hidden]");

    // Check reset attempts for today
    $resetAttemptCheck = checkResetAttempts($conn, $email);
    if (!$resetAttemptCheck['success']) {
        // Log reset attempts exceeded
        logActivity("Reset attempts exceeded for email: $email.");
        echo json_encode($resetAttemptCheck);
        exit();
    }

    // Log reset attempts check passed
    logActivity("Reset attempts check passed for email: $email.");

    // Check if the user is locked
    $emailQuery = $conn->prepare("SELECT id, secret_answer FROM driver WHERE email = ?");
    if ($emailQuery === false) {
        // Log SQL preparation failure
        logActivity("Failed to prepare SQL statement to fetch driver details.");
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit();
    }

    $emailQuery->bind_param("s", $email);
    if (!$emailQuery->execute()) {
        // Log SQL execution failure
        logActivity("Failed to execute SQL statement to fetch driver details.");
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit();
    }

    $emailQuery->store_result();

    if ($emailQuery->num_rows > 0) {
        $emailQuery->bind_result($driver_id, $db_secret_answer);
        $emailQuery->fetch();

        // Log driver details fetched
        logActivity("Driver details fetched for email: $email. Driver ID: $driver_id");

        // Check if the user is locked
        $lockCheck = checkLockStatus($conn, $driver_id);
        if (!$lockCheck['success']) {
            // Log account locked
            logActivity("Account locked for driver ID: $driver_id.");
            echo json_encode($lockCheck);
            exit();
        }

        // Log account not locked
        logActivity("Account not locked for driver ID: $driver_id.");

        // Validate secret answer and confirm password
        if ((md5($secret_answer) !== $db_secret_answer)) {
            // Log secret answer validation failure
            logActivity("Secret answer validation failed for email: $email.");
            updateResetAttempts($conn, $email);
            echo json_encode(['success' => false, 'message' => 'Account Validation Failed.']);
            exit();
        }

        // Log secret answer validation success
        logActivity("Secret answer validation successful for email: $email.");

        // Step 4: Successful validation - Generate token
        $token = bin2hex(random_bytes(16)); // Secure random token
        $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes')); // Token expiration

        // Log token generation
        logActivity("Token generated for email: $email. Token: $token, Expires at: $expires_at");

        // Delete any existing tokens for the user
        $stmtDelete = $conn->prepare("DELETE FROM driver_password_reset_tokens WHERE email = ?");
        if ($stmtDelete === false) {
            // Log SQL preparation failure for delete
            logActivity("Failed to prepare DELETE statement for email: $email.");
            echo json_encode(['success' => false, 'message' => 'Failed to prepare DELETE statement.']);
            exit();
        }
        $stmtDelete->bind_param("s", $email);
        if (!$stmtDelete->execute()) {
            // Log SQL execution failure for delete
            logActivity("Failed to delete existing tokens for email: $email.");
            echo json_encode(['success' => false, 'message' => 'Failed to delete existing tokens.']);
            exit();
        }
        $stmtDelete->close();

        // Log successful deletion of existing tokens
        logActivity("Existing tokens deleted for email: $email.");

        // Store the new token
        $stmtToken = $conn->prepare("INSERT INTO driver_password_reset_tokens (email, token, expires_at) VALUES (?, ?, ?)");
        if ($stmtToken === false) {
            // Log SQL preparation failure for insert
            logActivity("Failed to prepare INSERT statement for email: $email.");
            echo json_encode(['success' => false, 'message' => 'Failed to prepare INSERT statement.']);
            exit();
        }
        $stmtToken->bind_param("sss", $email, $token, $expires_at);
        if (!$stmtToken->execute()) {
            // Log SQL execution failure for insert
            logActivity("Failed to store new token for email: $email.");
            echo json_encode(['success' => false, 'message' => 'Failed to store new token.']);
            exit();
        }
        $stmtToken->close();

        // Log successful token storage
        logActivity("New token stored successfully for email: $email.");

        // Return success and token
        echo json_encode(['success' => true, 'token' => $token]);
    } else {
        // Log no record found
        logActivity("No record found for email: $email.");
        echo json_encode(['success' => false, 'message' => 'Your Record cannot be found.']);
        exit();
    }
} else {
    // Log missing required fields
    logActivity("Missing required fields in the request.");
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit();
}

// Close the database connection
$conn->close();

// Log function exit
logActivity("Exiting password reset validation block.");
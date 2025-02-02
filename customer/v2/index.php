<?php
header('Content-Type: application/json');
include 'config.php';
include 'activity_logger.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function destroySession($customerId, $conn)
{
    // Fetch the session_id from customer_active_sessions table
    $sessionId = null;
    $stmt = $conn->prepare("SELECT session_id FROM customer_active_sessions WHERE customer_id = ?");
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $stmt->bind_result($sessionId);

    if ($stmt->fetch()) {
        // Log session ID being destroyed
        error_log("Destroying session for session_id: " . $sessionId . " and Customer ID " . $customerId);
        logActivity("Destriying session for session ID:" . $sessionId . " and Customer ID " . $customerId);

        // Destroy the session
        session_id($sessionId);
        session_start();
        session_destroy();

        // Remove the session record from the table
        $stmt->close();
        $stmt = $conn->prepare("DELETE FROM customer_active_sessions WHERE customer_id = ?");
        $stmt->bind_param("i", $customerId);
        $stmt->execute();
    } else {
        error_log("No active session found for customer_id: " . $customerId);
        logActivity("No active session found for customer_id: " . $customerId);
    }

    $stmt->close();
}

$data = json_decode(file_get_contents('php://input'), true);
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve username and password
    $username = $data['username'];
    $password = $data['password'];
    $encrypted_password = md5($password);

    // Log user attempting login
    error_log("Login attempt for username: " . $username);
    logActivity("Login attempt for username: " . $username);

    // Step 1: Fetch the user's customer_id based on either email or phone
    $stmt = $conn->prepare("SELECT * FROM customers WHERE email = ? OR mobile_number = ?");
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $customer_id = $row['customer_id']; // Get the user's unique ID

        // Log customer_id found for user
        error_log("Customer ID for user: " . $customer_id);
        logActivity("Customer ID for user: " . $customer_id);

        // Step 2: Check if the user is locked due to too many failed login attempts
        $stmtCheckAttempts = $conn->prepare("SELECT attempts, locked_until FROM customer_login_attempts WHERE customer_id = ?");
        $stmtCheckAttempts->bind_param("i", $customer_id);
        $stmtCheckAttempts->execute();
        $resultCheckAttempts = $stmtCheckAttempts->get_result();

        $max_attempts = 3;
        $lockout_duration = 60; // lockout time in minutes

        if ($resultCheckAttempts->num_rows > 0) {
            $attempt_data = $resultCheckAttempts->fetch_assoc();
            $attempts = $attempt_data['attempts'];

            // Handle null or invalid locked_until value
            $locked_until = null;
            if ($attempt_data['locked_until'] !== null && strtotime($attempt_data['locked_until']) !== false) {
                $locked_until = new DateTime($attempt_data['locked_until']);
            } else {
                // If locked_until is null or invalid, assume the account is not locked
                $locked_until = new DateTime('now'); // Use current time as a fallback
            }

            $current_time = new DateTime();

            // Check if the user is currently locked
            if ($attempts >= $max_attempts && $current_time < $locked_until) {
                $time_remaining = $locked_until->diff($current_time)->format('%i minutes %s seconds');
                echo json_encode(["success" => false, "message" => "Your account is locked. Please try again in $time_remaining."]);
                logActivity("Your account is locked. Please try again in $time_remaining.");
                exit();
            } elseif ($attempts >= $max_attempts && $current_time >= $locked_until) {
                // Reset attempts and unblock the account since the lockout duration has expired
                error_log("Unlocking account for customer_id: " . $customer_id);
                logActivity("Unlocking account for customer_id: " . $customer_id);

                // Delete login attempts
                $stmtResetAttempts = $conn->prepare("DELETE FROM customer_login_attempts WHERE customer_id = ?");
                $stmtResetAttempts->bind_param("i", $customer_id);

                // Update customer_lock_history table
                $status = 'unlocked'; // New status to indicate the account is unlocked
                $unlock_method = 'System unlock'; // Method used to unlock the account
                $customerID = 0; // DEFAULT SYSTEM ID

                $stmtUnlockHistory = $conn->prepare("UPDATE customer_lock_history SET status = ?, unlocked_by = ?, unlock_method = ?, unlocked_at = NOW() WHERE customer_id = ? AND status = 'locked'");
                $stmtUnlockHistory->bind_param("sisi", $status, $customerID, $unlock_method, $customer_id);

                // Execute the statements
                $stmtResetAttempts->execute();
                $stmtUnlockHistory->execute();
            }
        }

        // Step 3: Fetch customer details
        $stmt = $conn->prepare("SELECT * FROM customers WHERE customer_id = ?");
        $stmt->bind_param("i", $customer_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $customer_data = $result->fetch_assoc();
            $customer_password = $customer_data['password'];


            if ($encrypted_password === $customer_password) {
                // Step 4: Successful login - Reset login attempts
                destroySession($customer_id, $conn);
                session_start();
                session_regenerate_id(true);

                // Set session variables
                $_SESSION['customer_id'] = $customer_data['customer_id'];
                $_SESSION['customer_name'] = $row['firstname'] . " " . $row['lastname'];
                $_SESSION['email'] = $row['email'];
                // $_SESSION['wallet_balance'] = $row['wallet_balance'];

                // Create a new session
                $newSessionId = session_id();
                $loginTime = date('Y-m-d H:i:s');
                $stmt = $conn->prepare("INSERT INTO customer_active_sessions (customer_id, session_id, login_time, status) VALUES (?, ?, ?, 'Active')");
                $stmt->bind_param("iss", $customer_id, $newSessionId, $loginTime);
                $stmt->execute();
                logActivity("Inserting new Session for Customer" . $customer_id . "Into customer_active_session table");

                // Reset login attempts
                $stmtResetAttempts = $conn->prepare("DELETE FROM customer_login_attempts WHERE customer_id = ?");
                $stmtResetAttempts->bind_param("i", $customer_id);
                $stmtResetAttempts->execute();

                echo json_encode(["success" => true, "message" => "Login successful"]);
                logActivity("Customer with Customer ID" . $customer_id . "has successfully logged in");
            } else {
                error_log("Invalid password for customer_id: " . $customer_id);
                logActivity("Invalid Password for" . $customer_id);
                handleFailedLogin($conn, $customer_id, $max_attempts, $lockout_duration);
            }
        } else {
            logActivity("Account for Customer ID" . $customer_id . "is blocked. Kindly contact Admin");
            echo json_encode(["success" => false, "message" => "Account is blocked. Kindly contact Super Admin."]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "This Email or Phone Number does not exist!"]);
        error_log("No record found for this username: " . $username); // Debugging
        logActivity("No record found for this username: " . $username . "In the Database");
    }
}

$stmt->close();
$conn->close();


// Function to handle failed login attempts
function handleFailedLogin($conn, $customer_id, $max_attempts, $lockout_duration)
{
    // Check if an entry exists for this customer_id
    $stmtCheckAttempts = $conn->prepare("SELECT * FROM customer_login_attempts WHERE customer_id = ?");
    if ($stmtCheckAttempts) {
        $stmtCheckAttempts->bind_param("i", $customer_id);
        $stmtCheckAttempts->execute();
        $resultCheckAttempts = $stmtCheckAttempts->get_result();

        if ($resultCheckAttempts->num_rows > 0) {
            $row = $resultCheckAttempts->fetch_assoc();
            $current_attempts = $row['attempts'];
            $attemptss = $current_attempts + 1;

            // Debugging purposes: Check the value of attempts before the update
            error_log("Attempts before update for customer_id $customer_id: $attemptss");
            logActivity("Attempts before update for customer_id $customer_id: $attemptss");

            if ($attemptss >= $max_attempts) {
                // Lock the account for the specified duration
                $locked_until = new DateTime();
                $locked_until->modify("+$lockout_duration minutes");
                $lock_period = $locked_until->format('Y-m-d H:i:s');

                $stmtLock = $conn->prepare("UPDATE customer_login_attempts SET attempts = ?, locked_until = ? WHERE customer_id = ?");
                $stmtLock->bind_param("isi", $attemptss, $lock_period, $customer_id);
                if ($stmtLock->execute()) {
                    logActivity("Attempting to lock account for customer_id for Customer ID: $customer_id");
                    // Inserrt into customer lock history table

                    $locked_by = 0000; // Default system id
                    $lock_reason = "Account locked due to too many failed login attempts.";
                    $lock_method = "Automatic lock after failed login attempts.";
                    $stmtInsertLock = $conn->prepare("
                    INSERT INTO customer_lock_history (customer_id, status, locked_by, lock_reason, lock_method, locked_at) 
                    VALUES (?, 'locked', ?, ?, ?, NOW())
                ");
                    $stmtInsertLock->bind_param("iiss", $customer_id, $locked_by, $lock_reason, $lock_method);
                    $stmtInsertLock->execute();

                    logActivity("Inserting Account lock into customer_lock_history table after too many failed attempts, account is logged");
                    logActivity("After too many failed login attempts, account is locked for 1 hour");
                    // $stmtInsertLock->close();

                    echo json_encode(["success" => false, "message" => "Too many failed login attempts. Your account is locked for 1 hour."]);
                } else {
                    error_log("Failed to execute lock query: " . $stmtLock->error);
                    logActivity("Failed to execute lock query: " . $stmtLock->error);
                }
            } else {
                // Update the attempts count without locking the account
                $stmtUpdateAttempts = $conn->prepare("UPDATE customer_login_attempts SET attempts = ?, last_attempt = NOW() WHERE customer_id = ?");
                $stmtUpdateAttempts->bind_param("ii", $attemptss, $customer_id);
                if ($stmtUpdateAttempts->execute()) {
                    echo json_encode(["success" => false, "message" => "Invalid login credentials. Attempts left: " . ($max_attempts - $attemptss)]);
                    logActivity("Invalid login credentials. Attempts left: " . ($max_attempts - $attemptss));
                } else {
                    error_log("Failed to execute update attempts query: " . $stmtUpdateAttempts->error);
                    logActivity("Failed to execute update attempts query: " . $stmtUpdateAttempts->error);
                }
            }
        } else {
            // No record found for this customer_id
            error_log("No login attempt record found for customer_id: " . $customer_id);
            logActivity("No login attempt record found for customer_id: " . $customer_id);

            // Create a new record for login attempts
            $attemptss = 1;
            $stmtInsertAttempts = $conn->prepare("INSERT INTO customer_login_attempts (customer_id, attempts, last_attempt) VALUES (?, ?, NOW())");
            logActivity("Attempting to Insert into customer_login_attempts table for Customer ID: " . $customer_id);
            $stmtInsertAttempts->bind_param("ii", $customer_id, $attemptss);
            if ($stmtInsertAttempts->execute()) {
                echo json_encode(["success" => false, "message" => "Invalid login credentials. Attempts left: " . ($max_attempts - $attemptss)]);
                logActivity("Invalid login credentials. Attempts left: " . ($max_attempts - $attemptss));
            } else {
                error_log("Failed to insert new attempt record: " . $stmtInsertAttempts->error);
                logActivity("Failed to insert new attempt record: " . $stmtInsertAttempts->error);
            }
        }
    }

    $stmtCheckAttempts->close();
}


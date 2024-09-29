<?php
function destroySession($uniqueId, $conn)
{
    // Fetch the session_id from admin_active_sessions table
    $sessionId = null;
    $stmt = $conn->prepare("SELECT session_id FROM admin_active_sessions WHERE unique_id = ?");
    $stmt->bind_param("i", $uniqueId);
    $stmt->execute();
    $stmt->bind_result($sessionId);

    if ($stmt->fetch()) {
        // Log session ID being destroyed
        error_log("Destroying session for session_id: " . $sessionId);
        
        // Destroy the session
        session_id($sessionId);
        session_start();
        session_destroy();

        // Remove the session record from the table
        $stmt->close();
        $stmt = $conn->prepare("DELETE FROM admin_active_sessions WHERE unique_id = ?");
        $stmt->bind_param("i", $uniqueId);
        $stmt->execute();
    } else {
        error_log("No active session found for unique_id: " . $uniqueId);
    }

    $stmt->close();
}

header('Content-Type: application/json');
include 'config.php';

$data = json_decode(file_get_contents('php://input'), true);
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve username and password
    $username = $data['username'];
    $password = $data['password'];
    $encrypted_password = md5($password);

    // Log user attempting login
    error_log("Login attempt for username: " . $username);

    // Step 1: Fetch the user's unique_id based on either email or phone
    $stmt = $conn->prepare("SELECT * FROM admin_tbl WHERE email = ? OR phone = ?");
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $unique_id = $row['unique_id']; // Get the user's unique ID

        // Log unique_id found for user
        error_log("Unique ID for user: " . $unique_id);

        // Step 2: Check if the user is locked due to too many failed login attempts
        $stmtCheckAttempts = $conn->prepare("SELECT attempts, locked_until FROM admin_login_attempts WHERE unique_id = ?");
        $stmtCheckAttempts->bind_param("i", $unique_id);
        $stmtCheckAttempts->execute();
        $resultCheckAttempts = $stmtCheckAttempts->get_result();

        $max_attempts = 3;
        $lockout_duration = 60; // lockout time in minutes

        if ($resultCheckAttempts->num_rows > 0) {
            $attempt_data = $resultCheckAttempts->fetch_assoc();
            $attempts = $attempt_data['attempts'];
            $locked_until = new DateTime($attempt_data['locked_until']);
            $current_time = new DateTime();

            // Check if the user is currently locked
            if ($attempts >= $max_attempts && $current_time < $locked_until) {
                $time_remaining = $locked_until->diff($current_time)->format('%i minutes %s seconds');
                echo json_encode(["success" => false, "message" => "Your account is locked. Please try again in $time_remaining."]);
                exit();
            } elseif ($attempts >= $max_attempts && $current_time >= $locked_until) {
                // Reset attempts and unblock the account since the lockout duration has expired
                error_log("Unlocking account for unique_id: " . $unique_id);
                $stmtResetAttempts = $conn->prepare("DELETE FROM admin_login_attempts WHERE unique_id = ?");
                $stmtResetAttempts->bind_param("i", $unique_id);
                

                // Reset block_id
                $block_id = 0;
                $stmtUnblock = $conn->prepare("UPDATE admin_tbl SET block_id = ? WHERE unique_id = ?");
                $stmtUnblock->bind_param("ii", $block_id, $unique_id);

                // Update admin_lock_history table
                $status = 'unlocked'; // New status to indicate the account is unlocked
                $unlock_method = 'System unlock'; // Method used to unlock the account
                $adminID = 0; // DEFAULT SYSTEM ID
                
                $stmtUnlockHistory = $conn->prepare("UPDATE admin_lock_history SET status = ?, unlocked_by = ?, unlock_method = ?, unlocked_at = NOW() WHERE unique_id = ? AND status = 'locked'");
                $stmtUnlockHistory->bind_param("sisi", $status, $adminID, $unlock_method, $unique_id);
                $stmtUnblock->execute();
                $stmtResetAttempts->execute();
                $stmtUnlockHistory->execute();

            }
        }

        // Step 3: Fetch admin details
        $stmt = $conn->prepare("SELECT * FROM admin_tbl WHERE unique_id = ?");
        $stmt->bind_param("i", $unique_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $admin_data = $result->fetch_assoc();
            $admin_password = $admin_data['password'];
            $block_id = $admin_data['block_id'];

            if ($block_id == 0) {
                if ($encrypted_password === $admin_password) {
                    // Step 4: Successful login - Reset login attempts
                    destroySession($unique_id, $conn);
                    session_start();
                    session_regenerate_id(true);

                    // Set session variables
                    $_SESSION['unique_id'] = $admin_data['unique_id'];
                    $_SESSION['firstname'] = $admin_data['firstname'];
                    $_SESSION['lastname'] = $admin_data['lastname'];
                    $_SESSION['role'] = $admin_data['role'];
                    $_SESSION['restriction_id'] = $admin_data['restriction_id'];
                    $_SESSION['secret_answer'] = md5($admin_data['secret_answer']);

                    // Create a new session
                    $newSessionId = session_id();
                    $loginTime = date('Y-m-d H:i:s');
                    $stmt = $conn->prepare("INSERT INTO admin_active_sessions (unique_id, session_id, login_time, status) VALUES (?, ?, ?, 'Active')");
                    $stmt->bind_param("iss", $unique_id, $newSessionId, $loginTime);
                    $stmt->execute();

                    // Reset login attempts
                    $stmtResetAttempts = $conn->prepare("DELETE FROM admin_login_attempts WHERE unique_id = ?");
                    $stmtResetAttempts->bind_param("i", $unique_id);
                    $stmtResetAttempts->execute();

                    echo json_encode(["success" => true, "message" => "Login successful"]);
                } else {
                    error_log("Invalid password for unique_id: " . $unique_id);
                    handleFailedLogin($conn, $unique_id, $max_attempts, $lockout_duration);
                }
            } else {
                echo json_encode(["success" => false, "message" => "Account is blocked. Kindly contact Super Admin."]);
            }
        }
    } else {
        echo json_encode(["success" => false, "message" => "This Email or Phone Number does not exist!"]);
        error_log("No record found for this username: " . $username); // Debugging
    }

    $stmt->close();
    $conn->close();
}

// Function to handle failed login attempts
function handleFailedLogin($conn, $unique_id, $max_attempts, $lockout_duration)
{
    // Check if an entry exists for this unique_id
    $stmtCheckAttempts = $conn->prepare("SELECT * FROM admin_login_attempts WHERE unique_id = ?");
    if ($stmtCheckAttempts) {
        $stmtCheckAttempts->bind_param("i", $unique_id);
        $stmtCheckAttempts->execute();
        $resultCheckAttempts = $stmtCheckAttempts->get_result();

        if ($resultCheckAttempts->num_rows > 0) {
            $row = $resultCheckAttempts->fetch_assoc();
            $current_attempts = $row['attempts'];
            $attemptss = $current_attempts + 1;

            // Debugging purposes: Check the value of attempts before the update
            error_log("Attempts before update for unique_id $unique_id: $attemptss");

            if ($attemptss >= $max_attempts) {
                // Lock the account for the specified duration
                $locked_until = new DateTime();
                $locked_until->modify("+$lockout_duration minutes");
                $lock_period = $locked_until->format('Y-m-d H:i:s');

                $stmtLock = $conn->prepare("UPDATE admin_login_attempts SET attempts = ?, locked_until = ? WHERE unique_id = ?");
                $stmtLock->bind_param("isi", $attemptss, $lock_period, $unique_id);
                if ($stmtLock->execute()) {
                    // Block the account in the admin table
                    $block_id = 1;
                    $stmtBlock = $conn->prepare("UPDATE admin_tbl SET block_id = ? WHERE unique_id = ?");
                    $stmtBlock->bind_param("ii", $block_id, $unique_id);
                    $stmtBlock->execute();

                    // Inserrt into admin lock history table

                    $locked_by = 0000; // Default system id
                    $lock_reason = "Account locked due to too many failed login attempts.";
                    $lock_method = "Automatic lock after failed login attempts.";
                    $stmtInsertLock = $conn->prepare("
                    INSERT INTO admin_lock_history (unique_id, status, locked_by, lock_reason, lock_method, locked_at) 
                    VALUES (?, 'locked', ?, ?, ?, NOW())
                ");
                $stmtInsertLock->bind_param("iiss", $unique_id, $locked_by, $lock_reason, $lock_method);
                $stmtInsertLock->execute();
                // $stmtInsertLock->close();

                    echo json_encode(["success" => false, "message" => "Too many failed login attempts. Your account is locked for 1 hour."]);
                } else {
                    error_log("Failed to execute lock query: " . $stmtLock->error);
                }
            } else {
                // Update the attempts count without locking the account
                $stmtUpdateAttempts = $conn->prepare("UPDATE admin_login_attempts SET attempts = ?, last_attempt = NOW() WHERE unique_id = ?");
                $stmtUpdateAttempts->bind_param("ii", $attemptss, $unique_id);
                if ($stmtUpdateAttempts->execute()) {
                    echo json_encode(["success" => false, "message" => "Invalid login credentials. Attempts left: " . ($max_attempts - $attemptss)]);
                } else {
                    error_log("Failed to execute update attempts query: " . $stmtUpdateAttempts->error);
                }
            }
        } else {
            // No record found for this unique_id
            error_log("No login attempt record found for unique_id: " . $unique_id);

            // Create a new record for login attempts
            $attemptss = 1;
            $stmtInsertAttempts = $conn->prepare("INSERT INTO admin_login_attempts (unique_id, attempts, last_attempt) VALUES (?, ?, NOW())");
            $stmtInsertAttempts->bind_param("ii", $unique_id, $attemptss);
            if ($stmtInsertAttempts->execute()) {
                echo json_encode(["success" => false, "message" => "Invalid login credentials. Attempts left: " . ($max_attempts - $attemptss)]);
            } else {
                error_log("Failed to insert new attempt record: " . $stmtInsertAttempts->error);
            }
        }
    }

    $stmtCheckAttempts->close();
}


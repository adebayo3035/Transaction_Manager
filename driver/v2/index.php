<?php
include 'config.php';

function destroySession($driverId, $conn)
{
    // Fetch the session_id from driver_active_sessions table
    $sessionId = null;
    $stmt = $conn->prepare("SELECT session_id FROM driver_active_sessions WHERE driver_id = ?");
    $stmt->bind_param("i", $driverId);
    $stmt->execute();
    $stmt->bind_result($sessionId);

    if ($stmt->fetch()) {
        // Log session ID being destroyed
        logActivity("Destroying session for session_id: " . $sessionId);

        // Destroy the session
        session_id($sessionId);
        session_start();
        session_destroy();

        // Remove the session record from the table
        $stmt->close();
        $stmt = $conn->prepare("DELETE FROM driver_active_sessions WHERE driver_id = ?");
        $stmt->bind_param("i", $driverId);
        $stmt->execute();
    } else {
        logActivity("No active session found for driver_id: " . $driverId);
    }

    $stmt->close();
}

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve username and password
    $username = $data['username'];
    $password = $data['password'];

    // Log user attempting login
    logActivity("Login attempt for username: " . $username);

    // Step 1: Fetch the user's driver_id based on either email or phone
    $stmt = $conn->prepare("SELECT * FROM driver WHERE email = ? OR phone_number = ?");
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $driver_id = $row['id']; // Get the user's unique ID

        // Log driver_id found for user
        logActivity("Driver ID for user: " . $driver_id);

        // Step 2: Check if the user is locked due to too many failed login attempts
        $stmtCheckAttempts = $conn->prepare("SELECT attempts, locked_until FROM driver_login_attempts WHERE driver_id = ?");
        $stmtCheckAttempts->bind_param("i", $driver_id);
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
                logActivity("Account locked for driver_id: $driver_id. Time remaining: $time_remaining.");
                echo json_encode(["success" => false, "message" => "Your account is locked. Please try again in $time_remaining."]);
                exit();
            } elseif ($attempts >= $max_attempts && $current_time >= $locked_until) {
                // Reset attempts and unblock the account since the lockout duration has expired
                logActivity("Unlocking account for driver_id: " . $driver_id);
                $stmtResetAttempts = $conn->prepare("DELETE FROM driver_login_attempts WHERE driver_id = ?");
                $stmtResetAttempts->bind_param("i", $driver_id);


                // Update driver_lock_history table
                $status = 'unlocked'; // New status to indicate the account is unlocked
                $unlock_method = 'System unlock'; // Method used to unlock the account
                $driverID = 0; // DEFAULT SYSTEM ID

                $stmtUnlockHistory = $conn->prepare("UPDATE driver_lock_history SET status = ?, unlocked_by = ?, unlock_method = ?, unlocked_at = NOW() WHERE driver_id = ? AND status = 'locked'");
                $stmtUnlockHistory->bind_param("sisi", $status, $driverID, $unlock_method, $driver_id);
                $stmtResetAttempts->execute();
                $stmtUnlockHistory->execute();
            }
        }

        // Step 3: Fetch driver details
        $stmt = $conn->prepare("SELECT * FROM driver WHERE id = ?");
        $stmt->bind_param("i", $driver_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $driver_data = $result->fetch_assoc();
            $driver_password = $driver_data['password'];
            $restriction_id = $driver_data['restriction'];
            $verifyPassword = password_verify($password, $driver_password);

            if ($restriction_id !== 0) {
                logActivity("Account restricted for driver_id: " . $driver_id);
                echo json_encode([
                    "success" => false,
                    "message" => "Your Account is restricted. Kindly contact Admin to remove Restriction.",
                ]);
                exit();
            }
            if ($verifyPassword) {
                // Step 4: Successful login - Reset login attempts
                destroySession($driver_id, $conn);
                session_start();
                session_regenerate_id(true);

                // Set session variables
                $_SESSION['driver_id'] = $driver_data['id'];
                $_SESSION['driver_name'] = $row['firstname'] . " " . $row['lastname'];

                // Create a new session
                $newSessionId = session_id();
                $loginTime = date('Y-m-d H:i:s');
                $stmt = $conn->prepare("INSERT INTO driver_active_sessions (driver_id, session_id, login_time, status) VALUES (?, ?, ?, 'Active')");
                $stmt->bind_param("iss", $driver_id, $newSessionId, $loginTime);
                $stmt->execute();

                // Reset login attempts
                $stmtResetAttempts = $conn->prepare("DELETE FROM driver_login_attempts WHERE driver_id = ?");
                $stmtResetAttempts->bind_param("i", $driver_id);
                $stmtResetAttempts->execute();

                logActivity("Login successful for driver_id: " . $driver_id);
                echo json_encode(["success" => true, "message" => "Login successful"]);
            } else {
                logActivity("Invalid password for driver_id: " . $driver_id);
                handleFailedLogin($conn, $driver_id, $max_attempts, $lockout_duration);
            }
        } else {
            logActivity("Account blocked for driver_id: " . $driver_id);
            echo json_encode(["success" => false, "message" => "Account is blocked. Kindly contact Super Admin."]);
        }
    } else {
        logActivity("No record found for username: " . $username);
        echo json_encode(["success" => false, "message" => "This Email or Phone Number does not exist!"]);
    }
}

$stmt->close();
$conn->close();

// Function to handle failed login attempts
function handleFailedLogin($conn, $driver_id, $max_attempts, $lockout_duration)
{
    // Check if an entry exists for this driver_id
    $stmtCheckAttempts = $conn->prepare("SELECT * FROM driver_login_attempts WHERE driver_id = ?");
    if ($stmtCheckAttempts) {
        $stmtCheckAttempts->bind_param("i", $driver_id);
        $stmtCheckAttempts->execute();
        $resultCheckAttempts = $stmtCheckAttempts->get_result();

        if ($resultCheckAttempts->num_rows > 0) {
            $row = $resultCheckAttempts->fetch_assoc();
            $current_attempts = $row['attempts'];
            $attemptss = $current_attempts + 1;

            logActivity("Attempts before update for driver_id $driver_id: $attemptss");

            if ($attemptss >= $max_attempts) {
                // Lock the account for the specified duration
                $locked_until = new DateTime();
                $locked_until->modify("+$lockout_duration minutes");
                $lock_period = $locked_until->format('Y-m-d H:i:s');

                $stmtLock = $conn->prepare("UPDATE driver_login_attempts SET attempts = ?, locked_until = ? WHERE driver_id = ?");
                $stmtLock->bind_param("isi", $attemptss, $lock_period, $driver_id);
                if ($stmtLock->execute()) {
                    // Insert into driver lock history table
                    $locked_by = 0000; // Default system id
                    $lock_reason = "Account locked due to too many failed login attempts.";
                    $lock_method = "Automatic lock after failed login attempts.";
                    $stmtInsertLock = $conn->prepare("
                        INSERT INTO driver_lock_history (driver_id, status, locked_by, lock_reason, lock_method, locked_at) 
                        VALUES (?, 'locked', ?, ?, ?, NOW())
                    ");
                    $stmtInsertLock->bind_param("iiss", $driver_id, $locked_by, $lock_reason, $lock_method);
                    $stmtInsertLock->execute();

                    logActivity("Account locked for driver_id: $driver_id. Lock period: $lock_period.");
                    echo json_encode(["success" => false, "message" => "Too many failed login attempts. Your account is locked for 1 hour."]);
                } else {
                    logActivity("Failed to execute lock query: " . $stmtLock->error);
                }
            } else {
                // Update the attempts count without locking the account
                $stmtUpdateAttempts = $conn->prepare("UPDATE driver_login_attempts SET attempts = ?, last_attempt = NOW() WHERE driver_id = ?");
                $stmtUpdateAttempts->bind_param("ii", $attemptss, $driver_id);
                if ($stmtUpdateAttempts->execute()) {
                    logActivity("Invalid login credentials for driver_id: $driver_id. Attempts left: " . ($max_attempts - $attemptss));
                    echo json_encode(["success" => false, "message" => "Invalid login credentials. Attempts left: " . ($max_attempts - $attemptss)]);
                } else {
                    logActivity("Failed to execute update attempts query: " . $stmtUpdateAttempts->error);
                }
            }
        } else {
            // No record found for this driver_id
            logActivity("No login attempt record found for driver_id: " . $driver_id);

            // Create a new record for login attempts
            $attemptss = 1;
            $stmtInsertAttempts = $conn->prepare("INSERT INTO driver_login_attempts (driver_id, attempts, last_attempt) VALUES (?, ?, NOW())");
            $stmtInsertAttempts->bind_param("ii", $driver_id, $attemptss);
            if ($stmtInsertAttempts->execute()) {
                logActivity("Invalid login credentials for driver_id: $driver_id. Attempts left: " . ($max_attempts - $attemptss));
                echo json_encode(["success" => false, "message" => "Invalid login credentials. Attempts left: " . ($max_attempts - $attemptss)]);
            } else {
                logActivity("Failed to insert new attempt record: " . $stmtInsertAttempts->error);
            }
        }
    }

    $stmtCheckAttempts->close();
}
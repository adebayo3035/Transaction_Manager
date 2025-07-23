<?php
header('Content-Type: application/json');
include 'config.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Constants
define('MAX_LOGIN_ATTEMPTS', 3);
define('LOCKOUT_DURATION_MINUTES', 60); // 1 hour lockout

function destroySession($customerId, $conn) {
    try {
        // Fetch the session_id from customer_active_sessions table
         $stmt = $conn->prepare("SELECT session_id FROM customer_active_sessions WHERE customer_id = ?");
        $stmt->bind_param("i", $customerId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $sessionId = $row['session_id']; // Explicit assignment
            // Log session ID being destroyed
            logActivity("Destroying session for session_id: $sessionId and Customer ID $customerId");
            
            // Close the statement before starting a new session
            $stmt->close();
            
            // Destroy the session
            session_id($sessionId);
            session_start();
            session_destroy();
            
            // Remove the session record from the table
            $stmt = $conn->prepare("DELETE FROM customer_active_sessions WHERE customer_id = ?");
            $stmt->bind_param("i", $customerId);
            $stmt->execute();
        } else {
            logActivity("No active session found for customer_id: $customerId");
        }
    } catch (Exception $e) {
        logActivity("Error in destroySession: " . $e->getMessage());
    } finally {
        if (isset($stmt) && $stmt instanceof mysqli_stmt) {
            $stmt->close();
        }
    }
}

function handleFailedLogin($conn, $customer_id) {
    try {
        // Check if an entry exists for this customer_id
        $stmtCheckAttempts = $conn->prepare("SELECT * FROM customer_login_attempts WHERE customer_id = ?");
        $stmtCheckAttempts->bind_param("i", $customer_id);
        $stmtCheckAttempts->execute();
        $resultCheckAttempts = $stmtCheckAttempts->get_result();

        $attempts = 1;
        
        if ($resultCheckAttempts->num_rows > 0) {
            $row = $resultCheckAttempts->fetch_assoc();
            $attempts = $row['attempts'] + 1;
            
            logActivity("Attempts before update for customer_id $customer_id: $attempts");

            if ($attempts >= MAX_LOGIN_ATTEMPTS) {
                // Lock the account for the specified duration
                $locked_until = date('Y-m-d H:i:s', strtotime("+" . LOCKOUT_DURATION_MINUTES . " minutes"));
                
                $stmtLock = $conn->prepare("UPDATE customer_login_attempts SET attempts = ?, locked_until = ? WHERE customer_id = ?");
                $stmtLock->bind_param("isi", $attempts, $locked_until, $customer_id);
                
                if ($stmtLock->execute()) {
                    // Insert into customer lock history table
                    $locked_by = 0000; // Default system id
                    $lock_reason = "Account locked due to too many failed login attempts.";
                    $lock_method = "Automatic lock after failed login attempts.";
                    
                    $stmtInsertLock = $conn->prepare("
                        INSERT INTO customer_lock_history 
                        (customer_id, status, locked_by, lock_reason, lock_method, locked_at) 
                        VALUES (?, 'locked', ?, ?, ?, NOW())
                    ");
                    $stmtInsertLock->bind_param("iiss", $customer_id, $locked_by, $lock_reason, $lock_method);
                    $stmtInsertLock->execute();
                    $stmtInsertLock->close();
                    
                    logActivity("Account locked for customer_id: $customer_id");
                    return ["success" => false, "message" => "Too many failed login attempts. Your account is locked for " . LOCKOUT_DURATION_MINUTES . " minutes."];
                }
            } else {
                // Update the attempts count without locking the account
                $stmtUpdateAttempts = $conn->prepare("UPDATE customer_login_attempts SET attempts = ?, last_attempt = NOW() WHERE customer_id = ?");
                $stmtUpdateAttempts->bind_param("ii", $attempts, $customer_id);
                $stmtUpdateAttempts->execute();
                $stmtUpdateAttempts->close();
                
                $remaining = MAX_LOGIN_ATTEMPTS - $attempts;
                return ["success" => false, "message" => "Invalid login credentials. Attempts left: $remaining"];
            }
        } else {
            // Create a new record for login attempts
            $stmtInsertAttempts = $conn->prepare("INSERT INTO customer_login_attempts (customer_id, attempts, last_attempt) VALUES (?, ?, NOW())");
            $stmtInsertAttempts->bind_param("ii", $customer_id, $attempts);
            $stmtInsertAttempts->execute();
            $stmtInsertAttempts->close();
            
            $remaining = MAX_LOGIN_ATTEMPTS - $attempts;
            return ["success" => false, "message" => "Invalid login credentials. Attempts left: $remaining"];
        }
    } catch (Exception $e) {
        logActivity("Error in handleFailedLogin: " . $e->getMessage());
        return ["success" => false, "message" => "An error occurred during login."];
    } finally {
        if (isset($stmtCheckAttempts) && $stmtCheckAttempts instanceof mysqli_stmt) {
            $stmtCheckAttempts->close();
        }
    }
}

function verifyPassword($inputPassword, $storedPassword) {
    // Using md5 is insecure - consider migrating to password_hash() and password_verify()
    return md5($inputPassword) === $storedPassword;
}

// Main execution
try {
    if ($_SERVER['REQUEST_METHOD'] != 'POST') {
        http_response_code(405);
        echo json_encode(["success" => false, "message" => "Method not allowed"]);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Invalid JSON data"]);
        exit;
    }

    if (empty($data['username']) || empty($data['password'])) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Username and password are required"]);
        exit;
    }

    $username = $data['username'];
    $password = $data['password'];
    
    logActivity("Login attempt for username: $username");

    // Step 1: Fetch the user's customer_id based on either email or phone
    $stmt = $conn->prepare("SELECT * FROM customers WHERE email = ? OR mobile_number = ?");
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(["success" => false, "message" => "Invalid credentials"]);
        logActivity("No record found for username: $username");
        exit;
    }

    $row = $result->fetch_assoc();
    $customer_id = $row['customer_id'];
    logActivity("Customer ID found: $customer_id");

    // Check account status
    if ((int)$row['restriction'] !== 0) {
        logActivity("Login denied - Account restricted for Customer ID: $customer_id");
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Account restricted. Contact Admin.']);
        exit;
    }

    if ($row['delete_status'] === 'Yes') {
        logActivity("Login denied - Account deactivated for Customer ID: $customer_id");
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Account deactivated. Contact Admin.']);
        exit;
    }

    // Check login attempts and lock status
    $stmtCheckAttempts = $conn->prepare("SELECT attempts, locked_until FROM customer_login_attempts WHERE customer_id = ?");
    $stmtCheckAttempts->bind_param("i", $customer_id);
    $stmtCheckAttempts->execute();
    $resultCheckAttempts = $stmtCheckAttempts->get_result();

    if ($resultCheckAttempts->num_rows > 0) {
        $attempt_data = $resultCheckAttempts->fetch_assoc();
        $attempts = $attempt_data['attempts'];
        $locked_until = $attempt_data['locked_until'];

        if ($attempts >= MAX_LOGIN_ATTEMPTS && strtotime($locked_until) > time()) {
            $time_remaining = strtotime($locked_until) - time();
            $minutes = floor($time_remaining / 60);
            $seconds = $time_remaining % 60;
            
            echo json_encode([
                "success" => false, 
                "message" => "Your account is locked. Please try again in $minutes minutes $seconds seconds."
            ]);
            exit;
        } elseif ($attempts >= MAX_LOGIN_ATTEMPTS) {
            // Reset attempts since lockout duration has expired
            $stmtResetAttempts = $conn->prepare("DELETE FROM customer_login_attempts WHERE customer_id = ?");
            $stmtResetAttempts->bind_param("i", $customer_id);
            $stmtResetAttempts->execute();
            
            // Update lock history
            $status = 'unlocked';
            $unlock_method = 'System unlock';
            $customerID = 0; // DEFAULT SYSTEM ID

            $stmtUnlockHistory = $conn->prepare("
                UPDATE customer_lock_history 
                SET status = ?, unlocked_by = ?, unlock_method = ?, unlocked_at = NOW() 
                WHERE customer_id = ? AND status = 'locked'
            ");
            $stmtUnlockHistory->bind_param("sisi", $status, $customerID, $unlock_method, $customer_id);
            $stmtUnlockHistory->execute();
        }
    }

    // Verify password
    if (!verifyPassword($password, $row['password'])) {
        $response = handleFailedLogin($conn, $customer_id);
        echo json_encode($response);
        exit;
    }

    // Successful login
    destroySession($customer_id, $conn);
    session_start();
    session_regenerate_id(true);

    // Set session variables
    $_SESSION['customer_id'] = $row['customer_id'];
    $_SESSION['customer_name'] = $row['firstname'] . " " . $row['lastname'];
    $_SESSION['email'] = $row['email'];

    // Create a new session record
    $newSessionId = session_id();
    $loginTime = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("
        INSERT INTO customer_active_sessions 
        (customer_id, session_id, login_time, status) 
        VALUES (?, ?, ?, 'Active')
    ");
    $stmt->bind_param("iss", $customer_id, $newSessionId, $loginTime);
    $stmt->execute();
    logActivity("New session created for Customer ID: $customer_id");

    // Reset login attempts
    $stmtResetAttempts = $conn->prepare("DELETE FROM customer_login_attempts WHERE customer_id = ?");
    $stmtResetAttempts->bind_param("i", $customer_id);
    $stmtResetAttempts->execute();

    echo json_encode(["success" => true, "message" => "Login successful"]);
    logActivity("Customer ID $customer_id logged in successfully");

} catch (Exception $e) {
    http_response_code(500);
    logActivity("Error in login process: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "An error occurred during login"]);
} finally {
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
<?php
include 'config.php';
include 'auth_utils.php';
header('Content-Type: application/json');

// Validate request method and input
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (empty($data['username']) || empty($data['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Username and password are required']);
    exit;
}

$username = trim($data['username']);
$password = $data['password'];
logActivity("Login attempt initiated for username: $username");

try {
    // STEP 1: Look up driver by email or phone
    $stmt = $conn->prepare("SELECT id, password, restriction, delete_status, firstname, lastname 
                          FROM driver WHERE email = ? OR phone_number = ?");
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }

    $stmt->bind_param("ss", $username, $username);
    if (!$stmt->execute()) {
        throw new Exception("Database error: " . $stmt->error);
    }

    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        logActivity("No account found for: $username");
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        exit;
    }

    $driver = $result->fetch_assoc();
    $driverId = $driver['id'];
    logActivity("Driver account found - ID: $driverId");

    // STEP 2: Check account status
    if ((int)$driver['restriction'] !== 0) {
        logActivity("Login denied - Account restricted for driver ID: $driverId");
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Account restricted. Contact Admin.']);
        exit;
    }

    if ($driver['delete_status'] === 'Yes') {
        logActivity("Login denied - Account deactivated for driver ID: $driverId");
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Account deactivated. Contact Admin.']);
        exit;
    }

    // STEP 3: Check for lockout
    $lockoutStatus = checkLockoutStatus($conn, $driverId);
    if ($lockoutStatus['is_locked']) {
        logActivity("Login denied - Account locked for driver ID: $driverId");
        http_response_code(429);
        echo json_encode([
            'success' => false, 
            'message' => "Account locked. Try again after {$lockoutStatus['remaining_time']}."
        ]);
        exit;
    }

    // STEP 4: Verify password
    // if (!password_verify($password, $driver['password'])) {
    //     handleFailedLogin($conn, $driverId);
    //     logActivity("Login failed - Invalid password for driver ID: $driverId");
    //     http_response_code(401);
    //     echo json_encode([
    //         'success' => false, 
    //         'message' => "Invalid Login credentials. Attempts left: {$lockoutStatus['remaining_attempts']}"
    //     ]);
    //     exit;
    // }
     if (!verifyAndUpgradePassword($conn, $driverId, $password, $driver['password'])){
        $response = handleFailedLogin($conn, $driverId);
        logActivity("Login failed - Invalid password for driver ID: $driverId");
        http_response_code(401);
        echo json_encode([
            'success' => false, 
            'message' => "Invalid Login credentials. Attempts left: {$lockoutStatus['remaining_attempts']}"
        ]);
        exit;
    }

    // STEP 5: Successful login
    session_start();
    
    // Regenerate session ID to prevent fixation
    session_regenerate_id(true);
    
    // Destroy any existing sessions for this user
    destroyExistingSessions($conn, $driverId);

    // Set session variables
    $_SESSION = [
        'driver_id' => $driverId,
        'driver_name' => $driver['firstname'] . ' ' . $driver['lastname'],
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'],
        'created_at' => time()
    ];

    // Record successful login
    recordLoginSession($conn, $driverId, session_id());
    
    // Clear failed attempts
    clearFailedAttempts($conn, $driverId);

    logActivity("Login successful for driver ID: $driverId");
    echo json_encode(['success' => true, 'message' => 'Login successful']);

} catch (Exception $e) {
    logActivity("System error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}

// Helper functions
function checkLockoutStatus($conn, $driverId) {
    $maxAttempts = 3;
    $lockoutMinutes = 60;
    
    $stmt = $conn->prepare("SELECT attempts, locked_until FROM driver_login_attempts WHERE driver_id = ?");
    $stmt->bind_param("i", $driverId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $status = [
        'is_locked' => false,
        'remaining_time' => null,
        'remaining_attempts' => $maxAttempts
    ];
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $status['remaining_attempts'] = max(0, $maxAttempts - $row['attempts']);
        
        if ($row['locked_until'] && new DateTime($row['locked_until']) > new DateTime()) {
            $status['is_locked'] = true;
            $status['remaining_time'] = (new DateTime($row['locked_until']))->diff(new DateTime())->format('%i minutes %s seconds');
        } elseif ($row['locked_until']) {
            // Lock expired, clear it
            clearFailedAttempts($conn, $driverId);
        }
    }
    
    return $status;
}

function handleFailedLogin($conn, $driverId) {
    $maxAttempts = 3;
    $lockoutMinutes = 60;
    
    $stmt = $conn->prepare("INSERT INTO driver_login_attempts (driver_id, attempts, last_attempt) 
                          VALUES (?, 1, NOW()) 
                          ON DUPLICATE KEY UPDATE 
                          attempts = IF(locked_until IS NULL OR locked_until < NOW(), 
                                       LEAST(attempts + 1, ?), 
                                       attempts),
                          locked_until = IF(attempts + 1 >= ?, 
                                          DATE_ADD(NOW(), INTERVAL ? MINUTE), 
                                          locked_until),
                          last_attempt = NOW()");
    
    $stmt->bind_param("iiii", $driverId, $maxAttempts, $maxAttempts, $lockoutMinutes);
    $stmt->execute();
    
    // Record lock if maximum attempts reached
    $stmt = $conn->prepare("SELECT attempts FROM driver_login_attempts WHERE driver_id = ?");
    $stmt->bind_param("i", $driverId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0 && $result->fetch_assoc()['attempts'] >= $maxAttempts) {
        $lockReason = "Too many failed login attempts";
        $stmt = $conn->prepare("INSERT INTO driver_lock_history 
                              (driver_id, status, locked_by, lock_reason, lock_method, locked_at) 
                              VALUES (?, 'locked', 0, ?, 'System Lock', NOW())");
        $stmt->bind_param("is", $driverId, $lockReason);
        $stmt->execute();
    }
}

function destroyExistingSessions($conn, $driverId) {
    // Get all active sessions
    $stmt = $conn->prepare("SELECT session_id FROM driver_active_sessions WHERE driver_id = ?");
    $stmt->bind_param("i", $driverId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // Destroy each session
        // session_id($row['session_id']);
        // session_start();
        // session_destroy();
    }
    
    // Remove from database
    $stmt = $conn->prepare("DELETE FROM driver_active_sessions WHERE driver_id = ?");
    $stmt->bind_param("i", $driverId);
    $stmt->execute();
}

function recordLoginSession($conn, $driverId, $sessionId) {
    $stmt = $conn->prepare("INSERT INTO driver_active_sessions 
                          (driver_id, session_id, login_time, status) 
                          VALUES (?, ?, NOW(), 'Active')");
    $stmt->bind_param("is", $driverId, $sessionId);
    $stmt->execute();
}

function clearFailedAttempts($conn, $driverId) {
    $stmt = $conn->prepare("DELETE FROM driver_login_attempts WHERE driver_id = ?");
    $stmt->bind_param("i", $driverId);
    $stmt->execute();
}
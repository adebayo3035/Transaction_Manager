<?php
header('Content-Type: application/json');
include 'config.php';
session_start();

// Constants
const MAX_ATTEMPTS = 3;


// Enable logging
ini_set('log_errors', 1);

// Validate session and permissions
if (empty($_SESSION['unique_id']) || $_SESSION['role'] !== 'Super Admin') {
    logActivity("Unauthorized access attempt" . (isset($_SESSION['unique_id']) ? " by Admin ID: " . $_SESSION['unique_id'] : ""));
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized! Contact your Admin.']);
    exit();
}

$adminID = $_SESSION['unique_id'];

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logActivity("Invalid request method used");
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Access Denied! Invalid method.']);
    exit();
}

// Process request
$data = json_decode(file_get_contents('php://input'), true);
logActivity("Unlock attempt by Admin ID: $adminID | Data: " . json_encode($data));

if (empty($data['userID']) || empty($data['accountType'])) {
    logActivity("Missing required parameters");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
    exit();
}

$user_id = $data['userID'];
$account_type = $data['accountType'];

try {
    switch ($account_type) {
        case 'Staff':
            handleStaffUnlock($conn, $user_id, $adminID);
            break;
        case 'Customer':
            handleCustomerUnlock($conn, $user_id, $adminID);
            break;
        case 'Driver':
            handleDriverUnlock($conn, $user_id, $adminID);
            break;
        default:
            logActivity("Invalid account type provided: $account_type");
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid account Type.']);
            exit();
    }
} catch (Exception $e) {
    logActivity("Error processing unlock: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred while processing your request.']);
}

function handleStaffUnlock(mysqli $conn, string $username, int $adminID): void {
    logActivity("Processing Staff unlock for: $username");

    $stmt = $conn->prepare("SELECT unique_id FROM admin_tbl WHERE email = ? OR phone = ? OR unique_id = ?");
    $stmt->bind_param("sss", $username, $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        logActivity("No Staff found matching: $username");
        echo json_encode(['success' => false, 'message' => 'Staff ID does not exist.']);
        return;
    }

    $row = $result->fetch_assoc();
    $unique_id = $row['unique_id'];
    logActivity("Staff unique_id found: $unique_id");

    $stmt = $conn->prepare("SELECT attempts FROM admin_login_attempts WHERE unique_id = ?");
    $stmt->bind_param("i", $unique_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        logActivity("No login attempt record found for Staff $unique_id");
        echo json_encode(['success' => false, 'message' => 'No record found for this account.']);
        return;
    }

    $row = $result->fetch_assoc();
    $attempts = $row['attempts'];
    logActivity("Staff $unique_id has $attempts failed attempts");

    if ($attempts < MAX_ATTEMPTS) {
        $attempts_left = MAX_ATTEMPTS - $attempts;
        logActivity("Staff $unique_id has $attempts_left attempts left");
        $message = $attempts_left === 1 
            ? "You still have 1 more attempt." 
            : "You still have $attempts_left more attempts.";
        echo json_encode(['success' => false, 'message' => $message]);
        return;
    }

    // Begin transaction
    $conn->begin_transaction();

    try {
        // Reset attempts
        $stmt = $conn->prepare("DELETE FROM admin_login_attempts WHERE unique_id = ?");
        $stmt->bind_param("i", $unique_id);
        $stmt->execute();

        // Unlock account
        $block_id = 0;
        $stmt = $conn->prepare("UPDATE admin_tbl SET block_id = ? WHERE unique_id = ?");
        $stmt->bind_param("ii", $block_id, $unique_id);
        $stmt->execute();

        // Update lock history
        $status = 'unlocked';
        $unlock_method = 'Manual unlock by Super Admin';
        $stmt = $conn->prepare("UPDATE admin_lock_history SET status = ?, unlocked_by = ?, unlock_method = ?, unlocked_at = NOW() WHERE unique_id = ? AND status = 'locked'");
        $stmt->bind_param("sisi", $status, $adminID, $unlock_method, $unique_id);
        $stmt->execute();

        $conn->commit();
        logActivity("Staff account $unique_id successfully unlocked by Admin $adminID");
        echo json_encode(['success' => true, 'message' => 'Staff account has been unlocked successfully.']);
    } catch (Exception $e) {
        $conn->rollback();
        logActivity("Failed to unlock Staff account $unique_id: " . $e->getMessage());
        throw $e;
    }
}

function handleCustomerUnlock(mysqli $conn, string $username, int $adminID): void {
    logActivity("Processing Customer unlock for: $username");

    $stmt = $conn->prepare("SELECT customer_id FROM customers WHERE email = ? OR mobile_number = ?");
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        logActivity("Customer not found for identifier: $username");
        echo json_encode(['success' => false, 'message' => 'Customer ID does not exist.']);
        return;
    }

    $row = $result->fetch_assoc();
    $customer_id = $row['customer_id'];
    logActivity("Customer ID found: $customer_id");

    $stmt = $conn->prepare("SELECT attempts FROM customer_login_attempts WHERE customer_id = ?");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        logActivity("No login attempt record for customer $customer_id");
        echo json_encode(['success' => false, 'message' => 'No record found for this account.']);
        return;
    }

    $row = $result->fetch_assoc();
    $attempts = $row['attempts'];
    logActivity("Customer $customer_id has $attempts failed attempts");

    if ($attempts < MAX_ATTEMPTS) {
        $attempts_left = MAX_ATTEMPTS - $attempts;
        logActivity("Customer $customer_id has $attempts_left attempts left");
        $message = $attempts_left === 1 
            ? "You still have 1 more attempt." 
            : "You still have $attempts_left more attempts.";
        echo json_encode(['success' => false, 'message' => $message]);
        return;
    }

    // Begin transaction
    $conn->begin_transaction();

    try {
        // Reset attempts
        $stmt = $conn->prepare("DELETE FROM customer_login_attempts WHERE customer_id = ?");
        $stmt->bind_param("i", $customer_id);
        $stmt->execute();

        // Update lock history
        $status = 'unlocked';
        $unlock_method = 'Manual unlock by Super Admin';
        $stmt = $conn->prepare("UPDATE customer_lock_history SET status = ?, unlocked_by = ?, unlock_method = ?, unlocked_at = NOW() WHERE customer_id = ? AND status = 'locked'");
        $stmt->bind_param("sisi", $status, $adminID, $unlock_method, $customer_id);
        $stmt->execute();

        $conn->commit();
        logActivity("Customer $customer_id successfully unlocked by Admin $adminID");
        echo json_encode(['success' => true, 'message' => 'Customer account has been unlocked successfully.']);
    } catch (Exception $e) {
        $conn->rollback();
        logActivity("Failed to unlock Customer $customer_id: " . $e->getMessage());
        throw $e;
    }
}

function handleDriverUnlock(mysqli $conn, string $username, int $adminID): void {
    logActivity("Processing Driver unlock for: $username");

    $stmt = $conn->prepare("SELECT id FROM driver WHERE email = ? OR phone_number = ?");
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        logActivity("Driver not found for: $username");
        echo json_encode(['success' => false, 'message' => 'Driver ID does not exist.']);
        return;
    }

    $row = $result->fetch_assoc();
    $driver_id = $row['id'];
    logActivity("Driver ID found: $driver_id");

    $stmt = $conn->prepare("SELECT attempts FROM driver_login_attempts WHERE driver_id = ?");
    $stmt->bind_param("i", $driver_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        logActivity("No login attempt record for driver $driver_id");
        echo json_encode(['success' => false, 'message' => 'No record found for this account.']);
        return;
    }

    $row = $result->fetch_assoc();
    $attempts = $row['attempts'];
    logActivity("Driver $driver_id has $attempts failed attempts");

    if ($attempts < MAX_ATTEMPTS) {
        $attempts_left = MAX_ATTEMPTS - $attempts;
        logActivity("Driver $driver_id has $attempts_left attempts left");
        $message = $attempts_left === 1 
            ? "You still have 1 more attempt." 
            : "You still have $attempts_left more attempts.";
        echo json_encode(['success' => false, 'message' => $message]);
        return;
    }

    // Begin transaction
    $conn->begin_transaction();

    try {
        // Reset attempts
        $stmt = $conn->prepare("DELETE FROM driver_login_attempts WHERE driver_id = ?");
        $stmt->bind_param("i", $driver_id);
        $stmt->execute();

        // Update lock history
        $status = 'unlocked';
        $unlock_method = 'Manual unlock by Super Admin';
        $stmt = $conn->prepare("UPDATE driver_lock_history SET status = ?, unlocked_by = ?, unlock_method = ?, unlocked_at = NOW() WHERE driver_id = ? AND status = 'locked'");
        $stmt->bind_param("sisi", $status, $adminID, $unlock_method, $driver_id);
        $stmt->execute();

        $conn->commit();
        logActivity("Driver $driver_id successfully unlocked by Admin $adminID");
        echo json_encode(['success' => true, 'message' => 'Driver account has been unlocked successfully.']);
    } catch (Exception $e) {
        $conn->rollback();
        logActivity("Failed to unlock Driver $driver_id: " . $e->getMessage());
        throw $e;
    }
}
<?php
// Start session at the very top
session_start();

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

include 'config.php';
require 'sendOTPGmail.php';

$admin_id = $_SESSION['unique_id'] ?? null;
$data = json_decode(file_get_contents("php://input"), true);

// Set default response headers
http_response_code(400); // Bad Request by default

// Step 1: Initial validation
if (!isset($data['staff_id']) || !isset($data['action']) || !isset($data['comment'])) {
    logActivity("Reactivation attempt failed: Missing required fields.");
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit;
}

$validActions = ['Reactivated', 'Declined'];
if (!in_array($data['action'], $validActions)) {
    logActivity("Reactivation attempt failed: Invalid action status passed from client. Value: " . $data['action']);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$staff_id = $data['staff_id'];
$action = $data['action'];
$comment = $data['comment'] ?? '';
$date = date('Y-m-d H:i:s');

// Step 2: Ensure admin session exists
if (!$admin_id) {
    http_response_code(401); // Unauthorized
    logActivity("Reactivation attempt failed: No logged-in Super Admin.");
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

logActivity("Super Admin ID $admin_id initiated a reactivation action: $action for Staff ID $staff_id.");

try {
    // Verify database connection
    if (!$conn || $conn->connect_error) {
        http_response_code(503); // Service Unavailable
        throw new Exception('Database connection error');
    }

    // Start transaction
    $conn->begin_transaction();
    logActivity("Transaction started for Staff ID $staff_id reactivation.");

    // Step 3: Fetch pending or declined reactivation request
    $stmt = $conn->prepare("
    SELECT id, deactivation_log_id, status, date_last_updated 
    FROM admin_reactivation_logs 
    WHERE admin_id = ? 
    AND (status = 'Pending' OR status = 'Declined') 
    ORDER BY id DESC 
    LIMIT 1
");

    $stmt->bind_param("s", $staff_id);

    if (!$stmt->execute()) {
        throw new Exception('Database query error');
    }

    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        http_response_code(404); // Not Found
        logActivity("No reactivation request found for Staff ID $staff_id.");
        echo json_encode(['status' => 'error', 'message' => 'No reactivation request found']);
        exit;
    }

    $log = $result->fetch_assoc();
    $reactivation_id = $log['id'];
    $deactivation_id = $log['deactivation_log_id'];
    $previous_status = $log['status'];
    $date_last_updated = strtotime($log['date_last_updated']);

    if ($previous_status === 'Declined' && $action === 'Reactivated') {
        $now = time();
        //prevent reactivation if last request was declined within last 24 hours
        if (($now - $date_last_updated) < 86400) {
            http_response_code(403);
            logActivity("Attempted reactivation within 24 hours of a decline for Staff ID $staff_id.");
            echo json_encode([
                'status' => 'error',
                'message' => 'Reactivation not allowed within 24 hours of a decline'
            ]);
            exit;
        }
    }


    logActivity("Fetched reactivation log. Reactivation ID: $reactivation_id, Deactivation ID: $deactivation_id for Staff ID $staff_id.");

    // Step 4: Update reactivation log
    $stmt = $conn->prepare("UPDATE admin_reactivation_logs SET reactivated_by = ?, comment = ?, status = ?, date_last_updated = ? WHERE id = ? AND deactivation_log_id = ?");
    $stmt->bind_param("isssii", $admin_id, $comment, $action, $date, $reactivation_id, $deactivation_id);

    if (!$stmt->execute()) {
        throw new Exception('Database update error');
    }

    logActivity("Reactivation log updated with status '$action' for Staff ID $staff_id.");

    // Step 5: If Approved, update admin_tbl
    if ($action === 'Reactivated') {
        $stmt = $conn->prepare("UPDATE admin_tbl SET delete_status = null, last_updated_by = ? WHERE unique_id = ?");
        $stmt->bind_param("is", $admin_id, $staff_id);

        if (!$stmt->execute()) {
            throw new Exception('Database update error');
        }

        logActivity("Account reactivated in admin_tbl for Staff ID $staff_id.");
    }

    // Send email notification after reactivation/decline
    sendReactivationEmail($staff_id, $action, $comment, $conn);

    // Step 6: Commit transaction
    $conn->commit();
    http_response_code(200); // OK

    logActivity("Transaction committed for Staff ID $staff_id reactivation.");
    logActivity("Super Admin ID $admin_id successfully completed $action for Staff ID $staff_id.");

    echo json_encode(['status' => 'success', 'message' => "Request processed successfully"]);

} catch (Exception $e) {
    // Handle transaction rollback safely
    if (isset($conn)) {
        try {
            // This will work whether or not we're in a transaction
            $conn->query('ROLLBACK');
        } catch (Exception $rollbackEx) {
            // Ignore rollback errors - we're already handling an exception
        }
    }

    http_response_code(500);
    logActivity("Exception occurred: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Operation failed']);
}

// Function to fetch staff email from the admin_tbl table
function getStaffEmail($staff_id, $conn)
{
    $stmt = $conn->prepare("SELECT email FROM admin_tbl WHERE unique_id = ?");
    $stmt->bind_param("s", $staff_id);

    if (!$stmt->execute()) {
        logActivity("Failed to execute email query for Staff ID $staff_id.");
        return null;
    }

    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $staff = $result->fetch_assoc();
        return $staff['email'];
    }

    logActivity("No email found for Staff ID $staff_id.");
    return null;
}

// function to send email notification after Reactivation or Decline
function sendReactivationEmail($staff_id, $action, $comment, $conn)
{
    $staff_email = getStaffEmail($staff_id, $conn);

    if (!$staff_email) {
        logActivity("Failed to retrieve email for Staff ID $staff_id.");
        return false;
    }

    $subject = "Your Account Reactivation Request Status";

    if ($action === 'Reactivated') {
        $body = "Dear Staff,\n\nWe are pleased to inform you that your account reactivation request has been successfully approved.";
    } else {
        $body = "Dear Staff,\n\nWe regret to inform you that your account reactivation request has been declined.";
    }

    $emailStatus = sendEmailWithGmailSMTP($staff_email, $body, $subject);

    if ($emailStatus) {
        logActivity("Reactivation email sent to Staff ID $staff_id with status: $action");
    } else {
        logActivity("Failed to send reactivation email to Staff ID $staff_id.");
    }

    return $emailStatus;
}
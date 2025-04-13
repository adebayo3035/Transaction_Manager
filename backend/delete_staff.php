<?php
session_start();
require 'config.php'; // Include database connection file
require 'sendOTPGmail.php'; // Include PHPMailer function
// Ensure request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

function destroyAdminSession($uniqueId)
{
    $logoutEndpoint = "http://localhost/transaction_manager/backend/logout.php"; // Ensure this URL is correct

    $data = ['logout_id' => $uniqueId];
    $options = [
        'http' => [
            'header'  => "Content-Type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($data),
        ],
    ];

    $context  = stream_context_create($options);
    $response = file_get_contents($logoutEndpoint, false, $context);

    if ($response === FALSE) {
        logActivity("Failed to call logout endpoint for User ID: $uniqueId");
    } else {
        logActivity("Logout request sent for User ID: $uniqueId");
    }
}

// Get JSON input
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['staff_id'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing staff_id"]);
    exit;
}
if (!isset($data['reason'])) {
    http_response_code(400);
    echo json_encode(["error" => "Deactivation Reason is Required"]);
    exit;
}

$staff_id_to_delete = intval($data['staff_id']);
$reason = $data['reason'];
$admin_id = $_SESSION['unique_id']; // ID of the person making the request
$admin_role = $_SESSION['role']; // Role of the person making the request

// Validate if requester is Super Admin
if ($admin_role !== 'Super Admin') {
    logActivity("Unauthorized deletion attempt by Admin ID: $admin_id");
    http_response_code(403);
    echo json_encode(["error" => "Only Super Admins can delete accounts"]);
    exit;
}

// Ensure the admin is not deleting their own account
if ($staff_id_to_delete == $admin_id) {
    logActivity("Super Admin (ID: $admin_id) attempted self-deletion.");
    http_response_code(403);
    echo json_encode(["error" => "You cannot delete your own account"]);
    exit;
}

// Check if the user exists and retrieve their details
$conn->begin_transaction();

try {
    $query = "SELECT role, delete_status, restriction_id, block_id, email, firstname, lastname FROM admin_tbl WHERE unique_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $staff_id_to_delete);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        logActivity("Attempt to delete non-existent Staff ID: $staff_id_to_delete");
        http_response_code(404);
        echo json_encode(["error" => "Staff record cannot be found"]);
        exit;
    }

    // Ensure the user to be deleted is not a Super Admin
    if ($user['role'] === 'Super Admin') {
        logActivity("Attempt to delete another Super Admin (ID: $staff_id_to_delete) by Admin ID: $admin_id");
        http_response_code(403);
        echo json_encode(["error" => "Cannot delete another Super Admin"]);
        exit;
    }

    // Ensure the account is not restricted or blocked
    if ($user['restriction_id'] == 1 || $user['block_id'] == 1) {
        logActivity("Attempt to delete a restricted/blocked user (ID: $staff_id_to_delete) by Admin ID: $admin_id");
        http_response_code(403);
        echo json_encode(["error" => "Cannot delete a restricted or blocked account"]);
        exit;
    }

    // Check if account has been deleted before
    if ($user['delete_status'] === 'Yes') {
        logActivity("Attempt to delete an already deleted Account (ID: $staff_id_to_delete) by Admin ID: $admin_id");
        http_response_code(403);
        echo json_encode(["error" => "Account is currently Inactive. Please Try Again"]);
        exit;
    }

    // Check for active session
    $query = "SELECT status FROM admin_active_sessions WHERE unique_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $staff_id_to_delete);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_session = $result->fetch_assoc();
    $stmt->close();

    if ($user_session && $user_session['status'] === 'Active') {
        logActivity("Destroying active session for Staff ID: $staff_id_to_delete");
        destroyAdminSession($staff_id_to_delete);
    }

    // Perform soft delete (set `delete_status` to 'Yes' or 1 depending on column type)
    $updateQuery = "UPDATE admin_tbl SET delete_status = 'Yes', last_deactivated_by = ? WHERE unique_id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("ii", $admin_id, $staff_id_to_delete);
    if (!$stmt->execute()) {
        throw new Exception("Failed to update delete status", 500);
    }
    $stmt->close();

    // Insert into admin_deactivation_logs table
    $insertLogQuery = "INSERT INTO admin_deactivation_logs (admin_id, deactivated_by, reason, status) VALUES (?, ?, ?, 'Deactivated')";
    $stmt = $conn->prepare($insertLogQuery);
    $stmt->bind_param("iis", $staff_id_to_delete, $admin_id, $reason);
    if (!$stmt->execute()) {
        throw new Exception("Failed to insert deactivation log", 500);
    }
    $stmt->close();

    // Send email notification to the user
   // Send email notification to the user
   $to = $user['email'];
//    $to = 'abdulrahmonadebayo@gmail.com';
   $subject = "Your Account Has Been Deactivated";
   $body = "
       Dear " . $user['firstname'] . " " . $user['lastname'] . ",\n\n
       We are writing to inform you that your account has been deactivated due to the following reason:\n
       " . $reason . "\n\n
       If you believe this action was taken in error or wish to discuss your account, please contact our support team at support@example.com or call us at [Support Phone Number].\n\n
       Thank you for your understanding.\n\n
       Best regards,\n
       The Support Team
   ";

   // Use the sendEmailWithGmailSMTP function to send the email
   $emailStatus = sendEmailWithGmailSMTP($to, $body, $subject);

   if ($emailStatus) {
       logActivity("Deactivation email sent to user ID: $staff_id_to_delete");
   } else {
       logActivity("Failed to send deactivation email to user ID: $staff_id_to_delete");
   }

    // Commit transaction
    $conn->commit();

    logActivity("Staff ID: $staff_id_to_delete successfully soft deleted by Super Admin ID: $admin_id");
    echo json_encode(["success" => "Staff record has been successfully deleted"]);
} catch (Exception $e) {
    $conn->rollback(); // Rollback if any step fails
    logActivity("Failed to delete Staff ID: $staff_id_to_delete. Error: " . $e->getMessage());
    http_response_code($e->getCode());
    echo json_encode(["error" => $e->getMessage()]);
}
$conn->close();

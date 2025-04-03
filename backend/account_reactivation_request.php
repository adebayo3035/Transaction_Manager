<?php
include "config.php";

$data = json_decode(file_get_contents("php://input"), true);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    logActivity("Invalid Request method sent from client");
    exit();
}

// Validate required fields
if (!isset($data["emailOrPhone"], $data["reason"], $data["secretAnswer"])) {
    echo json_encode(["success" => false, "message" => "All fields are required."]);
    exit();
}

$emailOrPhone = trim($data["emailOrPhone"]);
$reason = trim($data["reason"]);
$secretAnswer = trim($data["secretAnswer"]);
$encryptedSecretAnswer = md5($secretAnswer);

// Log request attempt
logActivity("Account reactivation request received for: $emailOrPhone");

// Step 1: Fetch user details from admin_tbl (assuming all account types are in this table)
$stmt = $conn->prepare("SELECT unique_id, email, phone, secret_answer, delete_status FROM admin_tbl WHERE email = ? OR phone = ?");
$stmt->bind_param("ss", $emailOrPhone, $emailOrPhone);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    logActivity("Reactivation request failed - No account found for: $emailOrPhone");
    echo json_encode(["success" => false, "message" => "Account not found."]);
    exit();
}

$user = $result->fetch_assoc();
$userID = $user["unique_id"];
$email = $user['email'];
$storedSecretAnswer = $user["secret_answer"];
$deleteStatus = $user["delete_status"];

if ($deleteStatus !== "Yes") {
    logActivity("Reactivation request failed - Account for $emailOrPhone is not deactivated.");
    echo json_encode(["success" => false, "message" => "Your account is not deactivated."]);
    exit();
}

// Step 2: Validate the secret answer
if ($encryptedSecretAnswer !== $storedSecretAnswer) {
    logActivity("Reactivation request failed - Incorrect secret answer for $emailOrPhone.");
    echo json_encode(["success" => false, "message" => "Account Validation Failed! Please Try Again."]);
    exit();
}

// Step 3: Check for duplicate request with pending status
$stmt = $conn->prepare("SELECT id FROM account_reactivation_requests WHERE user_id = ? AND status = 'pending'");
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    logActivity("Reactivation request failed - Duplicate pending request for user $userID.");
    echo json_encode(["success" => false, "message" => "You already have a pending reactivation request."]);
    exit();
}

// Step 4: Insert request into `account_reactivation_requests` table
$account_type = "admin";
$stmt = $conn->prepare("INSERT INTO account_reactivation_requests (user_id, account_type, email, reason, status, requested_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
$stmt->bind_param("isss", $userID, $account_type, $email,  $reason);

if ($stmt->execute()) {
    logActivity("Reactivation request successfully raised for user $userID.");
    echo json_encode(["success" => true, "message" => "Your reactivation request has been submitted."]);
} else {
    logActivity("Failed to insert reactivation request for user $userID. Error: " . $conn->error);
    echo json_encode(["success" => false, "message" => "Failed to submit request. Please try again."]);
}


<?php
include ('config.php');
session_start();
header('Content-Type: application/json');

logActivity("Starting Session check for User");

$customerId = $_SESSION["customer_id"] ?? null;
checkSession($customerId);
logActivity("Session validated for customer ID: $customerId");


// Generate a random token
$tokenOld = bin2hex(random_bytes(16)); // 32-character hex string
logActivity("Generated old token: $tokenOld");
$token = str_pad(random_int(0, 9999999999), 10, '0', STR_PAD_LEFT); // Ensures 10 digits
logActivity("Generated new token: $token");

// Store token and its expiration in the session
$_SESSION['token'] = [
    'value' => $token,
    'expires_at' => time() + 180 // Token expires in 180 seconds
];
logActivity("Token stored in session with expiration: " . $_SESSION['token']['expires_at']);

// Send token as JSON response
echo json_encode(['success' => true, 'token' => $token]);
logActivity("Token response sent successfully.");

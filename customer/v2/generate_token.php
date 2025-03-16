<?php
include 'config.php';
session_start();
header('Content-Type: application/json');

logActivity("Starting Session check for User");

$customerId = $_SESSION["customer_id"] ?? null;
checkSession($customerId);
logActivity("Session validated for customer ID: $customerId");

// Generate a random token
$token = bin2hex(random_bytes(16)); // 32-character hex string
logActivity("Generated token: $token");

// Create a token tied to the user
$tokenUser = hash('sha256', $token . $customerId); // Hash the token with the user ID
logActivity("Generated token_user: $tokenUser");

// Store token, token_user, and its expiration in the session
$_SESSION['token'] = [
    'value' => $token,
    'expires_at' => time() + 180, // Token expires in 180 seconds
    // 'token_user' => $tokenUser // Token tied to the user
];
logActivity("Token stored in session with expiration: " . $_SESSION['token']['expires_at']);

// Send token as JSON response
echo json_encode(['success' => true, 'token' => $token]);
logActivity("Token response sent successfully.");

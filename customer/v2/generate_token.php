<?php
include ('config.php');
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['customer_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}

// Generate a random token
$token = bin2hex(random_bytes(16)); // 32-character hex string

// Store token and its expiration in the session
$_SESSION['token'] = [
    'value' => $token,
    'expires_at' => time() + 60 // Token expires in 60 seconds
];

// Send token as JSON response
echo json_encode(['success' => true, 'token' => $token]);


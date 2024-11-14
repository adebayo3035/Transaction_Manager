<?php
include ('config.php');
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['customer_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}

// Generate a random token
$tokenOld = bin2hex(random_bytes(16)); // 32-character hex string
$token = str_pad(random_int(0, 9999999999), 10, '0', STR_PAD_LEFT); // Ensures 10 digits


// Store token and its expiration in the session
$_SESSION['token'] = [
    'value' => $token,
    'expires_at' => time() + 180 // Token expires in 60 seconds
];

// Send token as JSON response
echo json_encode(['success' => true, 'token' => $token]);


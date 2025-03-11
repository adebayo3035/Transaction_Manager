<?php
include 'config.php';
session_start();
header('Content-Type: application/json');

if (isset($_SESSION['driver_id'])) {
    logActivity("Session check successful for driver ID: {$_SESSION['driver_id']}");
    echo json_encode([
        'success' => true,
        'response_code' => 200,
        'driver_id' => $_SESSION['driver_id']
    ]);
} else {
    logActivity("Session check failed: No active session or session expired.");
    echo json_encode([
        'success' => false,
        'response_code' => 401, // Unauthorized
        'driver_id' => null,
        'message' => 'Session expired or not set.'
    ]);
}

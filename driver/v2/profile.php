<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
session_start();
include 'config.php'; // Adjust the path as needed


$driver_id = $_SESSION['driver_id'];
logActivity("Driver ID: $driver_id requested their profile details.");
checkDriverSession($driver_id);
logActivity("Session validated successfully for Driver ID: $driver_id.");

$sql = "SELECT * FROM driver WHERE id = ?";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    logActivity("Failed to prepare the SQL statement to fetch driver details.");
    http_response_code(500);  // Internal Server Error
    echo json_encode(['error' => 'Database error']);
    exit();
}

$stmt->bind_param('i', $driver_id);
if (!$stmt->execute()) {
    logActivity("Failed to execute the SQL statement to fetch driver details.");
    http_response_code(500);  // Internal Server Error
    echo json_encode(['error' => 'Database error']);
    exit();
}

$result = $stmt->get_result();
if ($result->num_rows === 0) {
    logActivity("No driver found with ID: $driver_id.");
    http_response_code(404);  // Not Found
    echo json_encode(['error' => 'Driver not found']);
    exit();
}

$driver = $result->fetch_assoc();
logActivity("Successfully fetched driver details for driver ID: $driver_id.");

echo json_encode($driver);

$stmt->close();
$conn->close();
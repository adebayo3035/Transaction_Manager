<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
include 'config.php'; // Adjust the path as needed

$driver_id = $_SESSION['driver_id'];

$sql = "SELECT * FROM driver WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $driver_id);
$stmt->execute();
$result = $stmt->get_result();
$driver = $result->fetch_assoc();

echo json_encode($driver);

$stmt->close();
$conn->close();
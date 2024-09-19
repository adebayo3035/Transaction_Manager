<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
include 'config.php'; // Adjust the path as needed

$admin_id = $_SESSION['unique_id'];

$sql = "SELECT * FROM admin_tbl WHERE unique_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();

echo json_encode($admin);

$stmt->close();
$conn->close();
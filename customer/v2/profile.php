<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
include 'config.php'; // Adjust the path as needed

$customer_id = $_SESSION['customer_id'];

$sql = "SELECT * FROM Customers WHERE customer_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $customer_id);
$stmt->execute();
$result = $stmt->get_result();
$customer = $result->fetch_assoc();

echo json_encode($customer);

$stmt->close();
$conn->close();
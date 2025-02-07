<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
include 'config.php'; // Adjust the path as needed

$customer_id = $_SESSION["customer_id"];

// Log the session activity
logActivity("Request to fetch customer details for Customer ID: $customer_id.");

checkSession($customer_id);

$sql = "SELECT * FROM Customers WHERE customer_id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    logActivity("Error preparing statement: " . $conn->error);
    echo json_encode(["success" => false, "message" => "Database error."]);
    exit();
}

$stmt->bind_param('i', $customer_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $customer = $result->fetch_assoc();
    echo json_encode($customer);
    logActivity("Customer details fetched successfully for Customer ID: $customer_id.");
} else {
    logActivity("No customer found with ID: $customer_id.");
    echo json_encode(["success" => false, "message" => "Customer not found."]);
}

$stmt->close();
$conn->close();


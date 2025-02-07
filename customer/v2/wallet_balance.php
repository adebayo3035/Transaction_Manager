<?php
session_start();
include ("config.php");
// Logging function
logActivity("Script execution started.");

// Validate session
$customerId = $_SESSION["customer_id"];
checkSession($customerId);
logActivity("Session validated successfully for Customer ID: $customerId.");

$stmt = $conn->prepare("SELECT balance FROM wallets WHERE customer_id = ?");
$stmt->bind_param("i", $customerId);

if (!$stmt->execute()) {
    logActivity("Database error: Failed to fetch balance for Customer ID: $customerId.");
    echo json_encode(['success' => false, 'message' => 'Failed to retrieve balance.']);
    exit;
}

$stmt->bind_result($balance);
$stmt->fetch();
$stmt->close();
logActivity("Balance retrieved successfully for Customer ID: $customerId. Balance: $balance");

$response = [
    'success' => true,
    'customer_name' => $_SESSION['customer_name'],
    'balance' => $balance
];

$conn->close();
logActivity("Script execution completed for Customer ID for Wallet Balance: $customerId.");
echo json_encode($response);
exit();

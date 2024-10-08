<?php
session_start();
include ("../v2/config.php");

if (!isset($_SESSION['customer_id'])) {
    http_response_code(401);  // Unauthorized
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

$customerId = $_SESSION["customer_id"];
$stmt = $conn->prepare("SELECT balance FROM wallets WHERE customer_id = ?");
$stmt->bind_param("i", $customerId);
$stmt->execute();
$stmt->bind_result($balance);
$stmt->fetch();
$stmt->close();

echo json_encode(['customer_name' => $_SESSION['customer_name'], 'balance' => $balance]);
exit();


<?php
include 'config.php';
session_start();
header('Content-Type: application/json');

$customerId = $_SESSION["customer_id"] ?? null;
checkSession($customerId);

logActivity("Session validated for customer ID: $customerId");
$status = "Active";
$query = "SELECT * FROM cards WHERE customer_id = ? AND status = ?";
$stmt = $conn->prepare($query);
if (!$stmt) {
    logActivity("Failed to prepare statement: " . $conn->error);
    echo json_encode(["success" => false, "message" => "Database error."]);
    exit();
}

$stmt->bind_param("is", $customerId, $status);
$stmt->execute();
$result = $stmt->get_result();
logActivity("Executed query to fetch cards for customer ID: $customerId");

$cards = [];
while ($row = $result->fetch_assoc()) {
    logActivity("Decrypting card details for Customer: " . $row['card_holder']);
    $row['card_number'] = decrypt($row['card_number'], $encryption_key, $encryption_iv);
    $row['cvv'] = decrypt($row['cvv'], $encryption_key, $encryption_iv);
    $row['card_holder'] = strtoupper($row['card_holder']);
    $cards[] = $row;
}

$stmt->close();
$conn->close();
logActivity("Successfully retrieved and decrypted cards for customer ID: $customerId");

echo json_encode(["success" => true, "cards" => $cards]);
logActivity("Response sent successfully.");

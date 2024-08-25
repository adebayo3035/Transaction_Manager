<?php
include 'config.php';
session_start();

if (!isset($_SESSION['customer_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}

// echo $customerId;

$customerId = $_SESSION['customer_id'];

$query = "SELECT * FROM cards WHERE customer_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $customerId);
$stmt->execute();
$result = $stmt->get_result();

$cards = [];
while ($row = $result->fetch_assoc()) {
    // $row['card_number'] = openssl_decrypt($row['card_number'], $cipher, $encryption_key, 0, $iv); 
    $row['card_number'] = decrypt($row['card_number'], $encryption_key, $encryption_iv);
    $row['cvv'] = decrypt($row['cvv'], $encryption_key, $encryption_iv);
    $row['card_holder'] =  strtoupper($row['card_holder']);

    $cards[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode(["success" => true, "cards" => $cards]);


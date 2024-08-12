<?php
session_start();
include('config.php');
header('Content-Type: application/json');

if (!isset($_SESSION['customer_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}

function getStoredPinHash($customerId, $conn) {
    $stmt = $conn->prepare("SELECT card_pin FROM cards WHERE customer_id = ?");
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ? $row['card_pin'] : null;
}

function getCumulativeDeposit($customerId, $conn) {
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT SUM(amount) AS total FROM customer_transactions WHERE customer_id = ? AND DATE(date_created) = ? AND transaction_type = 'credit'");
    $stmt->bind_param("is", $customerId, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ? $row['total'] : 0;
}

$token = $_POST['token'] ?? '';
$pin = $_POST['pin'] ?? '';
$amount = $_POST['amount'] ?? '';
$cardNumber = $_POST['card_number'] ?? '';
$customerId = $_SESSION['customer_id'] ?? '';

// $encryption_key = "your_encryption_key_here";
// $encryption_iv = "your_encryption_iv_here";
$encrypted_pin = md5($pin);
$encrypted_cardNumber = encrypt($cardNumber, $encryption_key, $encryption_iv);

if (!isset($_SESSION['token']) || $_SESSION['token']['value'] !== $token || time() > $_SESSION['token']['expires_at']) {
    echo json_encode(['success' => false, 'message' => 'Invalid or expired token']);
    exit;
}

if (!is_numeric($amount) || $amount <= 0 || $amount > 100000) {
    echo json_encode(['success' => false, 'message' => 'Invalid amount or exceeds maximum limit']);
    exit;
}

$cumulativeDeposit = getCumulativeDeposit($customerId, $conn);
if (($cumulativeDeposit + $amount) > 300000) {
    echo json_encode(['success' => false, 'message' => 'Cumulative deposit limit for the day exceeded']);
    exit;
}

$storedPinHash = getStoredPinHash($customerId, $conn);
if ($encrypted_pin !== $storedPinHash) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid PIN. Entered PIN (hashed): ' . $encrypted_pin ."  Stored Pin Hash is: ". $storedPinHash
    ]);
    exit;
}

$description = "Wallet Funding";
$stmt = $conn->prepare("INSERT INTO customer_transactions (customer_id, amount, date_created, transaction_type, description) VALUES (?, ?, NOW(), 'credit', ?)");
$stmt->bind_param("ids", $customerId, $amount, $description);
$stmt->execute();
$stmt->close();

$stmt = $conn->prepare("UPDATE wallets SET balance = balance + ? WHERE customer_id = ?");
$stmt->bind_param("di", $amount, $customerId);
$stmt->execute();
$stmt->close();

// Set token value to empty and set expiry time to current time.
echo json_encode(['success' => true, 'message' => 'Transaction successful']);
$_SESSION['token'] = [
    'value' => '',
    'expires_at' => time() // Token expires in 60 seconds
];
$conn->close();


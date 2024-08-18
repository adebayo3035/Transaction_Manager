<?php
session_start();
include('config.php');
header('Content-Type: application/json');

if (!isset($_SESSION['customer_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}

function getStoredPinHash($customerId, $cardNumber, $conn) {
    $stmt = $conn->prepare("SELECT card_pin FROM cards WHERE customer_id = ? and card_number = ?");
    $stmt->bind_param("is", $customerId, $cardNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ? $row['card_pin'] : null;
}

// function to check if customer has wallet
function checkWalletExists($customerId, $conn) {
    $walletId = '';
    $balance = '';
    $stmt = $conn->prepare("SELECT wallet_id, balance FROM wallets WHERE customer_id = ?");
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $stmt->bind_result($walletId, $balance);
    $walletExists = $stmt->fetch();
    $stmt->close();
    return $walletExists ? ['wallet_id' => $walletId, 'balance' => $balance] : null;
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
    echo json_encode(['success' => false, 'message' => 'This transaction will make you exceed the Cumulative deposit limit today']);
    exit;
}

$storedPinHash = getStoredPinHash($customerId,$encrypted_cardNumber, $conn);
if ($encrypted_pin !== $storedPinHash) {
    echo json_encode([
        'success' => false,
        'message' => 'Error Occured.Invalid Card Details'
    ]);
    exit;
}

$walletData = checkWalletExists($customerId, $conn);
$description = "Wallet Funding";
$paymentMethod = 'Card';

if ($walletData) {
    // Wallet exists, update the balance
    $stmt = $conn->prepare("UPDATE wallets SET balance = balance + ?, date_last_updated = NOW() WHERE wallet_id = ?");
    $stmt->bind_param("di", $amount, $walletData['wallet_id']);
    $stmt->execute();
    $stmt->close();
} else {
    // Wallet does not exist, create a new wallet
    $stmt = $conn->prepare("INSERT INTO wallets (customer_id, balance, date_last_updated) VALUES (?, ?, NOW())");
    $stmt->bind_param("id", $customerId, $amount);
    $stmt->execute();
    $walletId = $stmt->insert_id;
    $stmt->close();
}
$stmt = $conn->prepare("INSERT INTO customer_transactions (customer_id, amount, date_created, transaction_type, payment_method, description) VALUES (?, ?, NOW(), 'credit', ?, ?)");
$stmt->bind_param("idss", $customerId, $amount, $paymentMethod, $description);
$stmt->execute();
$stmt->close();

// Set token value to empty and set expiry time to current time.
echo json_encode(['success' => true, 'message' => 'Transaction successful', 'card_number' => $cardNumber]);
$_SESSION['token'] = [
    'value' => '',
    'expires_at' => time() // Token expires in 60 seconds
];
$conn->close();




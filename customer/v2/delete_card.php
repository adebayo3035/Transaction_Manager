<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
include_once "config.php";
session_start();

if (!isset($_SESSION['customer_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated.']);
    exit;
}

// Decode the JSON input
$data = json_decode(file_get_contents('php://input'), true);

// Check if JSON decoding was successful
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

// Function to sanitize inputs
function sanitizeInput($data)
{
    return htmlspecialchars(strip_tags($data));
}

// Retrieve POST data
// $card_number = isset($_POST['card_number']) ? sanitizeInput($_POST['card_number']) : '';
$card_number = mysqli_real_escape_string($conn, $data['card_number']) ?? null;
$secret_answer = mysqli_real_escape_string($conn, $data['secret_answer']) ?? null;
$token = mysqli_real_escape_string($conn, $data['token']) ?? null;
$customer_id = $_SESSION['customer_id'];

// encrypt the card number
$encrypted_card_number = encrypt($card_number, $encryption_key, $encryption_iv);

/// Validate token (assuming a session-based token for CSRF protection)
if (!isset($_SESSION['token']) || $_SESSION['token']['value'] !== $token || time() > $_SESSION['token']['expires_at']) {
    echo json_encode(['success' => false, 'message' => 'Invalid or expired token']);
    exit;
}

// check if card number exist
function checkDuplicateCardNumber($conn, $encrypted_cardNumber, $customerId)
{
    $sql = "SELECT COUNT(*) AS count FROM cards WHERE card_number = ? AND customer_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $encrypted_cardNumber, $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['count'] > 0;
}

// Check if selected Card is Profiled for customer
if (!checkDuplicateCardNumber($conn, $encrypted_card_number, $customer_id)) {
    echo json_encode(['success' => false, 'message' => 'This Card does not Exist.']);
    exit();
}

// Select and Validate Secret Answer

$stmt = $conn->prepare("SELECT secret_answer FROM customers WHERE customer_id = ?");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$result = $stmt->get_result();
$customer = $result->fetch_assoc();

if (!$customer) {
    echo json_encode(['success' => false, 'message' => 'Customer not found.']);
    exit;
}
// Validate secret answer
$hashedSecretAnswer = md5($secret_answer);
if ($customer['secret_answer'] !== $hashedSecretAnswer) {
    echo json_encode(['success' => false, 'message' => 'Invalid Secret Answer.']);
    exit;
}


// Prepare SQL statement to delete the card
$sql = "DELETE FROM cards WHERE customer_id = ? AND card_number = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ss', $customer_id, $encrypted_card_number);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Card deleted successfully.']);
    $_SESSION['token'] = [
        'value' => '',
        'expires_at' => time() // Token expires in 60 seconds
    ];
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete the card.']);
}

$stmt->close();
$conn->close();


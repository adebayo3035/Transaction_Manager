<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
include_once "config.php";
session_start();

logActivity("Script execution started.");

// Check if the customer is logged in
$customerId = $_SESSION["customer_id"] ?? null;
checkSession($customerId);

logActivity("Customer ID: $customerId - Session validated.");

// Decode the JSON input
$data = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    logActivity("Invalid JSON input received.");
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

logActivity("JSON input successfully decoded.");

// Function to sanitize inputs
function sanitizeInput($data)
{
    return htmlspecialchars(strip_tags($data));
}

// Retrieve POST data
$card_number = mysqli_real_escape_string($conn, $data['card_number'] ?? '');
$secret_answer = mysqli_real_escape_string($conn, $data['secret_answer'] ?? '');
$token = mysqli_real_escape_string($conn, $data['token'] ?? '');

logActivity("Received card number: $card_number, secret answer, and token.");

// Encrypt the card number
$encrypted_card_number = encrypt($card_number, $encryption_key, $encryption_iv);
logActivity("Card number encrypted.");

// Validate token (assuming a session-based token for CSRF protection)
if (!isset($_SESSION['token']) || $_SESSION['token']['value'] !== $token || time() > $_SESSION['token']['expires_at']) {
    logActivity("Invalid or expired token for customer ID: $customerId.");
    echo json_encode(['success' => false, 'message' => 'Invalid or expired token']);
    exit;
}

logActivity("Token validation passed.");

// Check if card number exists
function checkDuplicateCardNumber($conn, $encrypted_cardNumber, $customerId)
{
    logActivity("Checking if card exists for customer ID: $customerId.");
    $sql = "SELECT COUNT(*) AS count FROM cards WHERE card_number = ? AND customer_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $encrypted_cardNumber, $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    $exists = $row['count'] > 0;
    logActivity($exists ? "Card found for customer ID: $customerId." : "Card not found for customer ID: $customerId.");
    return $exists;
}

// Check if selected Card is Profiled for customer
if (!checkDuplicateCardNumber($conn, $encrypted_card_number, $customerId)) {
    logActivity("Card does not exist for customer ID: $customerId.");
    echo json_encode(['success' => false, 'message' => 'This Card does not Exist.']);
    exit();
}

logActivity("Card validation passed for customer ID: $customerId.");

// Select and validate secret answer
$stmt = $conn->prepare("SELECT secret_answer FROM customers WHERE customer_id = ?");
$stmt->bind_param("i", $customerId);
$stmt->execute();
$result = $stmt->get_result();
$customer = $result->fetch_assoc();
$stmt->close();

if (!$customer) {
    logActivity("Customer ID: $customerId not found in the database.");
    echo json_encode(['success' => false, 'message' => 'Customer not found.']);
    exit;
}

logActivity("Customer record found for ID: $customerId.");

// Validate secret answer
$hashedSecretAnswer = md5($secret_answer);
if ($customer['secret_answer'] !== $hashedSecretAnswer) {
    logActivity("Invalid secret answer for customer ID: $customerId.");
    echo json_encode(['success' => false, 'message' => 'Invalid Secret Answer.']);
    exit;
}

logActivity("Secret answer validated successfully for customer ID: $customerId.");

$status = 'Inactive';
// Prepare SQL statement to update the card status to "Inactive"
$sql = "UPDATE cards SET status = ? WHERE customer_id = ? AND card_number = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('sss', $status, $customerId, $encrypted_card_number);

if ($stmt->execute()) {
    logActivity("Card status updated to 'Inactive' for customer ID: $customerId.");
    echo json_encode(['success' => true, 'message' => 'Card has been successfully Deleted.']);
    
    // Invalidate the token after successful operation
    $_SESSION['token'] = [
        'value' => '',
        'expires_at' => time() // Token expired immediately
    ];
    logActivity("Token invalidated for customer ID: $customerId.");
} else {
    logActivity("Failed to update card status for customer ID: $customerId.");
    echo json_encode(['success' => false, 'message' => 'Failed to Delete Card.']);
}


$stmt->close();
$conn->close();
logActivity("Script execution completed.");

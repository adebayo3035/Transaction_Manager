<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
include_once "config.php";
include 'auth_utils.php';
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

// Retrieve POST data
$card_number = mysqli_real_escape_string($conn, $data['card_id'] ?? '');
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

// Verify that the token is tied to the current user
// $expectedTokenUser = hash('sha256', $token . $customerId);
// if ($_SESSION['token']['token_user'] !== $expectedTokenUser) {
//     logActivity("Token does not belong to customer ID: $customerId");
//     echo json_encode(['success' => false, 'message' => 'Token does not belong to this user']);
//     exit;
// }


logActivity("Token validation passed.");

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

// // Validate secret answer
// $hashedSecretAnswer = md5($secret_answer);
// if ($customer['secret_answer'] !== $hashedSecretAnswer) {
//     logActivity("Invalid secret answer for customer ID: $customerId.");
//     echo json_encode(['success' => false, 'message' => 'Invalid Secret Answer.']);
//     exit;
// }
// Validate secret answer
if (!verifyAndUpgradeSecretAnswer($conn, $customerId, $secret_answer, $customer['secret_answer'])) {
    logActivity("Invalid secret answer for Customer ID: $customerId.");
    echo json_encode(['success' => false, 'message' => 'Account Validation Failed.']);
    exit();
}

logActivity("Secret answer validated successfully for customer ID: $customerId.");

// Prepare SQL statement to fetch card details
$sql = "SELECT * FROM cards WHERE customer_id = ? AND card_number = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ss', $customerId, $encrypted_card_number);

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $CardDetails = $result->fetch_assoc();

    // Decrypt sensitive fields
    $CardDetails['card_number'] = decrypt($CardDetails['card_number'], $encryption_key, $encryption_iv);
    $CardDetails['cvv'] = decrypt($CardDetails['cvv'], $encryption_key, $encryption_iv);
   

    // // Mask sensitive data before sending to the client
    // $CardDetails['card_number'] = maskCardNumber($CardDetails['card_number']);
    // unset($CardDetails['cvv']); // Do not send CVV to the client

    // Return the decrypted and masked card details
    echo json_encode([
        'success' => true,
        'cardDetails' => $CardDetails
    ]);
    logActivity("Customer Card details fetched and decrypted successfully for Customer ID: $customerId.");

    logActivity("Attempting to Invalidate Token from Session to prevent token reusae");
    // Invalidate the token after successful operation
    $_SESSION['token'] = [
        'value' => '',
        'expires_at' => time() // Token expired immediately
    ];
    logActivity("Token invalidated for customer ID: $customerId.");
} else {
    logActivity("No Card Details found for Customer ID: $customerId.");
    echo json_encode(["success" => false, "message" => "Card Details Cannot be Found."]);
}
$conn->close();
logActivity("Script execution completed.");


// Fix issue with error messages displayed when getting Card Details.
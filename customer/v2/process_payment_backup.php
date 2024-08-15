<?php
session_start();
include 'config.php'; // Ensure this file has the database connection and encryption details
include 'payment_validations.php'; // Include the modular validation functions

$customerId = $_SESSION['customer_id'];

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

// Check if decoding was successful
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}
$response = [];

$paymentMethod = isset($data['payment_method']) ? $data['payment_method'] : null;
$cardNumber = isset($data['card_number']) ? $data['card_number'] : null;
$cardExpiry = isset($data['card_expiry']) ? $data['card_expiry'] : null;
$cardCvv = isset($data['card_cvv']) ? $data['card_cvv'] : null;
$cardPin = isset($data['card_pin']) ? $data['card_pin'] : null;
$paypalEmail = isset($data['paypal_email']) ? $data['paypal_email'] : null;
$bankName = isset($data['bank_name']) ? $data['bank_name'] : null;
$bankAccount = isset($data['bank_account']) ? $data['bank_account'] : null;
$encrypted_card_number = encrypt($cardNumber, $encryption_key, $encryption_iv);
$encrypted_CVV = encrypt($cardCvv, $encryption_key, $encryption_iv);
$encrypted_Pin = md5($cardPin);
// Initialize response array

// Validate based on payment method
switch ($paymentMethod) {
    case 'credit_card':
        if ($cardNumber && $cardExpiry && $cardCvv) {
            // Check for Card Number Duplicates
            if (!checkDuplicateCardNumber($conn, $encrypted_card_number,$encryption_key, $encryption_iv)) {
                echo json_encode(['success' => false, 'message' => 'Card Record does not exist.']);
            }

            // Validate the card payment
            // $validationResult = validateCardPayment(
            //     $customerId,
            //     $cardNumber,
            //     $cardCvv,
            //     $cardExpiry,
            //     $cardPin,
            //     $conn,
            //     $encryption_iv, // From config.php
            //     $encryption_key // From config.php
            // );

            // Return the validation result
            // echo json_encode($validationResult);
            // exit; // Make sure to exit after sending the response
        } else {
            echo json_encode(['success' => true]);
        }
        break;

    case 'paypal':
        $paypalEmail = filter_var(trim($data['paypal_email']), FILTER_SANITIZE_EMAIL);

        // Validate PayPal email format
        if (filter_var($paypalEmail, FILTER_VALIDATE_EMAIL)) {
            $response = ['success' => true, 'message' => 'PayPal details are valid'];
        } else {
            $response['message'] = 'Invalid PayPal email address';
        }
        break;

    case 'bank_transfer':
        $bankName = trim($data['bank_name']);
        $bankAccount = trim($data['bank_account']);

        if ($bankName && $bankAccount) {
            // Validate bank account number format if needed
            $response['success'] = true;
            $response['message'] = 'Bank transfer validation successful';
        } else {
            $response['message'] = 'Bank details are incomplete';
        }
        break;

    default:
        $response['message'] = 'Invalid payment method';
        break;
}

// Output the response
echo json_encode($response);


function getDecryptedCardDetails($customerId, $dbConnection, $key, $iv)
{
    $cardDetails = [];
    $validCard = null;
    $decryptedCardNumber = '';
    $decryptedCVV = '';
    $encryptedCardPIN = '';
    $expiryDate = '';
    $encryptedCardNumber = '';
    $encryptedCVV = '';
    $query = "SELECT card_number, cvv, expiry_date, card_pin FROM cards WHERE customer_id = ?";
    $stmt = $dbConnection->prepare($query);
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $stmt->bind_result($encryptedCardNumber, $encryptedCVV, $expiryDate, $encryptedCardPIN);

    while ($stmt->fetch()) {
        $decryptedCardNumber = decrypt($encryptedCardNumber, $key, $iv);
        $decryptedCVV = decrypt($encryptedCVV, $key, $iv);
        $cardDetails[] = [
            'card_number' => $decryptedCardNumber,
            'cvv' => $decryptedCVV,
            'expiry_date' => $expiryDate,
            'encryptedCardPIN' => $encryptedCardPIN
        ];
    }
    $stmt->close();
    return $cardDetails;
}

// Define the function to validate card ownership
function checkDuplicateCardNumber($conn, $encrypted_cardNumber, $encryptedCVV, $encryptedPin)
{
    $sql = "SELECT COUNT(*) AS count FROM cards WHERE card_number = ? and cvv = ? ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $encrypted_cardNumber, $encryptedCVV);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['count'] > 0;
}
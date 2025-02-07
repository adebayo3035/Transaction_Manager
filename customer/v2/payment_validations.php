<?php
include('config.php');

// Initialize variables
$validCard = null;
$decryptedCardNumber = '';
$decryptedCVV = '';
$encryptedCardPIN = '';
$expiryDate = '';
$encryptedCardNumber = '';
$encryptedCVV = '';

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
    
    // Logging start of decryption process
    logActivity("Fetching encrypted card details for customer ID: $customerId");

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

    // Log successful decryption
    logActivity("Decrypted card details for customer ID: $customerId");
    
    return $cardDetails;
}

// Define the function to validate card ownership
function validateCardOwnership($cardDetails, $inputCardNumber, $inputCVV)
{
    // Log the validation attempt
    logActivity("Validating card ownership for input card number: $inputCardNumber");

    foreach ($cardDetails as $card) {
        if ($card['card_number'] === $inputCardNumber && $card['cvv'] === $inputCVV) {
            // Log the successful validation
            logActivity("Card ownership validated successfully for card number: $inputCardNumber");
            return $card;
        }
    }
    
    // Log the failure
    logActivity("Card ownership validation failed for card number: $inputCardNumber");
    return false;
}

// Define the function to validate expiry date
function validateExpiryDate($expiryDate)
{
    // Log the expiry date validation
    logActivity("Validating expiry date: $expiryDate");

    $expiryParts = explode('-', $expiryDate);
    $expiryMonth = $expiryParts[0];
    $expiryYear = $expiryParts[1];
    $currentMonth = date('m');
    $currentYear = date('Y');

    if ($expiryYear < $currentYear || ($expiryYear == $currentYear && $expiryMonth < $currentMonth)) {
        // Log expiry failure
        logActivity("Card has expired. Expiry date: $expiryDate");
        return false;
    }

    // Log successful expiry check
    logActivity("Card expiry date is valid: $expiryDate");
    return true;
}

// Define the function to validate card PIN
function validateCardPIN($inputCardPIN, $encryptedCardPIN)
{
    // Log the card PIN validation attempt
    logActivity("Validating card PIN");

    if (md5($inputCardPIN) === $encryptedCardPIN) {
        // Log the successful validation
        logActivity("Card PIN validated successfully.");
        return true;
    }

    // Log failure
    logActivity("Invalid card PIN.");
    return false;
}

function validateCardPayment($customerId, $inputCardNumber, $inputCVV, $inputExpiryDate, $inputCardPIN, $dbConnection, $iv, $key)
{
    // Log the start of payment validation
    logActivity("Starting card payment validation for customer ID: $customerId");

    // Step 1: Fetch decrypted card details
    $cardDetails = getDecryptedCardDetails($customerId, $dbConnection, $key, $iv);
    
    if (isset($cardDetails['success']) && !$cardDetails['success']) {
        logActivity("Failed to fetch card details for customer ID: $customerId");
        return $cardDetails; // Return error if fetching card details failed
    }

    // Step 2: Validate card ownership (check if input matches the stored card details)
    $validCard = validateCardOwnership($cardDetails, $inputCardNumber, $inputCVV);
    
    if (!$validCard) {
        logActivity("Card ownership validation failed for card number: $inputCardNumber");
        return ['success' => false, 'message' => 'Card details do not match our records.'];
    }

    // Step 3: Validate expiry date
    if (!validateExpiryDate($validCard['expiry_date'])) {
        logActivity("Card expiry validation failed for card number: $inputCardNumber");
        return ['success' => false, 'message' => 'Card has expired.'];
    }

    // Step 4: Validate card PIN
    if (!validateCardPIN($inputCardPIN, $validCard['encryptedCardPIN'])) {
        logActivity("Card PIN validation failed for card number: $inputCardNumber");
        return ['success' => false, 'message' => 'Invalid card PIN.'];
    }

    // Log successful payment validation
    logActivity("Card payment validation successful for customer ID: $customerId");

    return ['success' => true, 'message' => 'Card validated successfully.'];
}


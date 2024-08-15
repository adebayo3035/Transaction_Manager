<?
include ('config.php');

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
function validateCardOwnership($cardDetails, $inputCardNumber, $inputCVV)
{
    foreach ($cardDetails as $card) {
        if ($card['card_number'] === $inputCardNumber && $card['cvv'] === $inputCVV) {
            return $card;
        }
    }
    return false;
}

// Define the function to validate expiry date
function validateExpiryDate($expiryDate)
{
    $expiryParts = explode('-', $expiryDate);
    $expiryMonth = $expiryParts[0];
    $expiryYear = $expiryParts[1];
    $currentMonth = date('m');
    $currentYear = date('Y');
    return !($expiryYear < $currentYear || ($expiryYear == $currentYear && $expiryMonth < $currentMonth));
}

// Define the function to validate card PIN
function validateCardPIN($inputCardPIN, $encryptedCardPIN)
{
    return md5($inputCardPIN) === $encryptedCardPIN;
}
function validateCardPayment($customerId, $inputCardNumber, $inputCVV, $inputExpiryDate, $inputCardPIN, $dbConnection, $iv, $key)
{
    $cardDetails = getDecryptedCardDetails($customerId, $dbConnection, $key, $iv);
    $validCard = validateCardOwnership($cardDetails, $inputCardNumber, $inputCVV);
    
    if (!$validCard) {
        return ['success' => false, 'message' => 'Card details do not match our records.'];
    }

    if (!validateExpiryDate($validCard['expiry_date'])) {
        return ['success' => false, 'message' => 'Card has expired.'];
    }

    if (!validateCardPIN($inputCardPIN, $validCard['encryptedCardPIN'])) {
        return ['success' => false, 'message' => 'Invalid card PIN.'];
    }

    return ['success' => true, 'message' => 'Card validated successfully.'];
}


<?php
header('Content-Type: application/json');
include_once "config.php";
session_start();
//Function to check if a card number already exists
function checkDuplicateCardNumber($conn, $encrypted_cardNumber)
{
    $sql = "SELECT COUNT(*) AS count FROM cards WHERE card_number = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $encrypted_cardNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['count'] > 0;
}
// Function to check if the card is expired
function isCardExpired($expiry_date)
{
    $expiryDate = DateTime::createFromFormat('m-Y', $expiry_date);
    if (!$expiryDate) {
        return true; // Treat invalid format as expired
    }
    $currentDate = new DateTime();
    return $currentDate > $expiryDate->modify('last day of this month');
}


// Function to check if a user already has 2 cards
function userHasTwoCards($conn, $customerId)
{
    $sql = "SELECT COUNT(*) AS count FROM cards WHERE customer_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['count'] >= 2;
}

// Variables Declaration
$bank_name = mysqli_real_escape_string($conn, $_POST['bank_name']);
$card_number = mysqli_real_escape_string($conn, $_POST['card_number']);
$card_holder = mysqli_real_escape_string($conn, $_POST['card_holder']);
$expiry_date = mysqli_real_escape_string($conn, $_POST['formatted_expiry_date']);
$pin = mysqli_real_escape_string($conn, $_POST['pin']);
$cvv = mysqli_real_escape_string($conn, $_POST['cvv']);
$customerId = $_SESSION['customer_id'];
$date_created = date('Y-m-d H:i:s');
$date_updated = date('Y-m-d H:i:s');
$maxCardNumber = 16;
$maxCVVNumber = 3;
$maxPIN = 4;
$hasDigitCard = preg_match('/\d/', $card_number);
$hasDigitCVV = preg_match('/\d/', $cvv);
$encrypted_card_number = encrypt($card_number, $encryption_key, $encryption_iv);
$encrypted_CVV = encrypt($cvv, $encryption_key, $encryption_iv);
$encrypted_Pin = md5($pin);


if (!empty($card_number) && !empty($card_holder) && !empty($expiry_date) && !empty($cvv) && !empty($customerId) && !empty($date_created) && !empty($date_updated)) {


    // Check card number length
    if (strlen($card_number) != $maxCardNumber) {
        echo json_encode(['success' => false, 'message' => 'Card must be ' . $maxCardNumber . ' digits long']);
        exit();
    }

    // Check CVV length
    if (strlen($cvv) != $maxCVVNumber) {

        echo json_encode(['success' => false, 'message' => 'CVV must be ' . $maxCVVNumber . ' digits long']);
        exit();
    }
    // Check pin length
    if (strlen($pin) != $maxPIN) {

        echo json_encode(['success' => false, 'message' => 'PIN must be ' . $maxPIN . ' digits long']);
        exit();
    }

    // Check if card number contains only digits
    if (!ctype_digit($card_number)) {
        echo json_encode(['success' => false, 'message' => 'Card Number must contain only digits']);
        exit();
    }

    // Check if CVV contains only digits
    if (!ctype_digit($cvv)) {
        echo json_encode(['success' => false, 'message' => 'CVV number must contain only digits.']);
        exit();
    }
    // Check if pin contains only digits
    if (!ctype_digit($pin)) {
        echo json_encode(['success' => false, 'message' => 'PIN number must contain only digits.']);
        exit();
    }

    // Validate expiry date
    if (isCardExpired($expiry_date)) {
        echo json_encode(['success' => false, 'message' => 'This Card has expired.']);
        exit();
    }

    // Check for Card Number Duplicates
    if (checkDuplicateCardNumber($conn, $encrypted_card_number)) {
        echo json_encode(['success' => false, 'message' => 'Card Already Exists.']);
        exit();
    }
    if (userHasTwoCards($conn, $customerId)) {
        echo json_encode(['success' => false, 'message' => 'You have reached Card Limit.']);
        exit();
    }

    // Get and validatethe customer's name with card name from the database
    $sql = "SELECT firstname, lastname FROM customers WHERE customer_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $customer = $result->fetch_assoc();
    $stmt->close();

    if ($customer) {
        $customerName = $customer['firstname'] . " " . $customer['lastname'];

        // Check if the cardholder's name matches the customer's name
        if ($card_holder !== $customerName) {
            echo json_encode(['success' => false, 'message' => 'Cardholder name does not match your account name.'. $customerName. " Card Holder: " . $card_holder]);
            exit();
        }
    }

    // Insert new card into the table

    $sql = "INSERT INTO cards (card_number, bank_name, card_holder, expiry_date, cvv, card_pin, customer_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssi", $encrypted_card_number, $bank_name, $card_holder, $expiry_date, $encrypted_CVV, $encrypted_Pin, $customerId);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Something went wrong, please try again.']);
        exit();
    }

    $stmt->close();

} else {
    echo json_encode(['success' => false, 'message' => 'Kindly input all fields.']);
    exit();
}

$conn->close();
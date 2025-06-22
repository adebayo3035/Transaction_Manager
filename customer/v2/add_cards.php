<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
include_once "config.php";
session_start();

logActivity("Script execution started");

$customerId = $_SESSION["customer_id"];
logActivity("Checking if customer is logged in. Customer ID: $customerId");
checkSession($customerId);

// ✅ CALL EXPIRED CARD UPDATER HERE
updateExpiredCards($customerId, $conn);
logActivity("Expired cards have been checked and updated before adding a new card");



$data = json_decode(file_get_contents('php://input'), true);
logActivity("Received JSON input");

if (json_last_error() !== JSON_ERROR_NONE) {
    logActivity("Invalid JSON received");
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

function checkDuplicateCardNumber($conn, $encrypted_cardNumber)
{
    logActivity("Checking for duplicate card number");
    $sql = "SELECT COUNT(*) AS count FROM cards WHERE card_number = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $encrypted_cardNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['count'] > 0;
}

function isCardExpired($expiry_date)
{
    logActivity("Checking if card is expired");
    $expiryDate = DateTime::createFromFormat('m-Y', $expiry_date);
    if (!$expiryDate) {
        return true;
    }
    $currentDate = new DateTime();
    return $currentDate > $expiryDate->modify('last day of this month');
}

function userHasTwoCards($conn, $customerId) {
    logActivity("Checking if customer already has two active (non-expired) cards");

    // Get current year and month
    $currentMonth = date('m');
    $currentYear = date('Y');

    // Updated query with status check
    $sql = "
        SELECT COUNT(*) AS count 
        FROM cards 
        WHERE customer_id = ? 
          AND status = 'Active'
          AND (
            CAST(SUBSTRING_INDEX(expiry_date, '-', -1) AS UNSIGNED) > ?
            OR (
                CAST(SUBSTRING_INDEX(expiry_date, '-', -1) AS UNSIGNED) = ?
                AND CAST(SUBSTRING_INDEX(expiry_date, '-', 1) AS UNSIGNED) >= ?
            )
          )
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiii", $customerId, $currentYear, $currentYear, $currentMonth);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return $row['count'] >= 2;
}

function updateExpiredCards($customerId, $conn)
{
    logActivity("Fetching Expired Cards for customer ID: $customerId and deactivating expired cards...");

    // 1. Fetch all cards belonging to the customer
    $stmt = $conn->prepare("SELECT id, card_number, expiry_date, status FROM cards WHERE customer_id = ?");
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();

    $currentMonth = date('m');
    $currentYear = date('Y');

    while ($row = $result->fetch_assoc()) {
        $cardId = $row['id'];
        $expiryDate = $row['expiry_date']; // Format: MM-YYYY
        $cardStatus = $row['status'];
        $cardNum = $row['card_number'];

        // Split expiry date
        list($expMonth, $expYear) = explode('-', $expiryDate);
        $isExpired = ($expYear < $currentYear) || ($expYear == $currentYear && $expMonth < $currentMonth);

        // 2. If expired and still marked active, update status to inactive
        if ($isExpired && strtolower($cardStatus) === 'active') {
            $update = $conn->prepare("UPDATE cards SET status = 'Inactive' WHERE id = ?");
            $update->bind_param("i", $cardId);
            if ($update->execute()) {
                logActivity("Card $cardNum expired — status changed to 'inactive'");
            } else {
                logActivity("Failed to update status for expired card $cardNum");
            }
            $update->close();
        }
    }
    $stmt->close();
}


logActivity("Extracting variables from input data");
$bank_name = mysqli_real_escape_string($conn, $data['bank_name']) ?? null;
$card_number = mysqli_real_escape_string($conn, $data['card_number']) ?? null;
$card_holder = mysqli_real_escape_string($conn, $data['card_holder']) ?? null;
$expiry_date = mysqli_real_escape_string($conn, $data['expiry_date']) ?? null;
$pin = mysqli_real_escape_string($conn, $data['card_pin']) ?? null;
$cvv = mysqli_real_escape_string($conn, $data['card_cvv']) ?? null;
$customerId = $_SESSION['customer_id'];
$date_created = date('Y-m-d H:i:s');
$date_updated = date('Y-m-d H:i:s');
$encrypted_card_number = encrypt($card_number, $encryption_key, $encryption_iv);
$encrypted_CVV = encrypt($cvv, $encryption_key, $encryption_iv);
$encrypted_Pin = md5($pin);

logActivity("Validating input data");
if (!empty($card_number) && !empty($card_holder) && !empty($expiry_date) && !empty($cvv) && !empty($customerId)) {
    logActivity("Checking card details format and limits");

    if (strlen($card_number) != 16) {
        logActivity("Card number length invalid");
        echo json_encode(['success' => false, 'message' => 'Card must be 16 digits long']);
        exit();
    }

    if (strlen($cvv) != 3) {
        logActivity("CVV length invalid");
        echo json_encode(['success' => false, 'message' => 'CVV must be 3 digits long']);
        exit();
    }

    if (strlen($pin) != 4) {
        logActivity("PIN length invalid");
        echo json_encode(['success' => false, 'message' => 'PIN must be 4 digits long']);
        exit();
    }

    if (!ctype_digit($card_number) || !ctype_digit($cvv) || !ctype_digit($pin)) {
        logActivity("Invalid character detected in card details");
        echo json_encode(['success' => false, 'message' => 'Card details must contain only digits']);
        exit();
    }

    if (isCardExpired($expiry_date)) {
        logActivity("Card has expired");
        echo json_encode(['success' => false, 'message' => 'This Card has expired.']);
        exit();
    }

    if (checkDuplicateCardNumber($conn, $encrypted_card_number)) {
        logActivity("Duplicate card detected");
        echo json_encode(['success' => false, 'message' => 'Card Already Exists.']);
        exit();
    }

    if (userHasTwoCards($conn, $customerId)) {
        logActivity("User has reached card limit");
        echo json_encode(['success' => false, 'message' => 'You have reached Card Limit.']);
        exit();
    }

    logActivity("All validation checks are successful. Inserting new card into database");
    $sql = "INSERT INTO cards (card_number, bank_name, card_holder, expiry_date, cvv, card_pin, customer_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssi", $encrypted_card_number, $bank_name, $card_holder, $expiry_date, $encrypted_CVV, $encrypted_Pin, $customerId);

    if ($stmt->execute()) {
        logActivity("Card successfully added for customer ID: $customerId");
        echo json_encode(['success' => true, 'message' => 'Customer Card Details has been successfully submitted.', 'customer_id' => $customerId]);
    } else {
        logActivity("Failed to insert card details");
        echo json_encode(['success' => false, 'message' => 'Something went wrong, please try again.']);
    }
    $stmt->close();
} else {
    logActivity("Required fields missing in input data");
    echo json_encode(['success' => false, 'message' => 'Kindly input all fields.']);
    exit();
}

logActivity("Closing database connection");
$conn->close();
logActivity("Script execution completed");

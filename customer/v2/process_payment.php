<?php
session_start();
include 'config.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');


// Fetch customer ID from session
$customerId = $_SESSION['customer_id'] ?? null;
if (!$customerId) {
    echo json_encode(['success' => false, 'message' => 'Customer ID not found.']);
    exit;
}

// Decode the JSON input from request body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Check if JSON decoding was successful
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

// Extract payment data from the received JSON
$paymentMethod = $data['payment_method'] ?? null;
$cardNumber = $data['card_number'] ?? null;
$cardExpiry = $data['card_expiry'] ?? null;
$cardCvv = $data['card_cvv'] ?? null;
$cardPin = $data['card_pin'] ?? null;
$orderItems = $data['order_items'] ?? [];
// $totalAmount = $data['total_amount'] ?? 0;
$serviceFee = $data['service_fee'] ?? 0;
$deliveryFee = $data['delivery_fee'] ?? 0;
$totalOrder = $data['total_order'] ?? 0;


$bankName = $data['bank_name'] ?? null;
$bankAccount = $data['bank_account'] ?? null;
$paypalEmail = $data['paypal_email'] ?? null;

$usingPromo = $data['using_promo'] ?? false;
$promo_code = $data['promo_code'] ?? null;
$discount = $data['discount'] ?? 0;
$discount_percent = $data['discount_percent'] ?? 0;

// Apply the discount if promo is used
if ($usingPromo) {
    $totalAmount = $data['total_amount'] - $discount;
} else {
    $totalAmount = $data['total_amount'] ?? 0;
}
// Initialize the response
$response = ['success' => false, 'message' => 'Unknown error occurred'];

// Handle payment validation based on the payment method
switch ($paymentMethod) {
    case 'Card':
        if ($cardNumber && $cardExpiry && $cardCvv && $cardPin) {
            // Validate credit card details
            $validationResult = validateCardPayment($customerId, $cardNumber, $cardCvv, $cardExpiry, $cardPin, $conn, $encryption_iv, $encryption_key);
            if ($validationResult['success']) {
                // Proceed with order processing
                $response = processOrder($customerId, $orderItems, $totalAmount, $serviceFee, $deliveryFee, $totalOrder, $discount, $discount_percent, $paymentMethod, $promo_code, $conn);
            } else {
                $response = $validationResult;
            }
        } else {
            $response['message'] = 'Missing credit card details';
        }
        break;

    case 'bank_transfer':
        // Define allowed banks and accounts
        $allowedBanks = [
            "providus",
            "wema",
        ];
        if ($bankName && $bankAccount) {
            // Check if the bank is allowed
            if (!in_array($bankName, $allowedBanks)) {
                $response['message'] = 'Unknown Bank Account';
            } else {
                // Validate bank transfer and process the order
                $response = processOrder($customerId, $orderItems, $totalAmount, $serviceFee, $deliveryFee, $totalOrder, $discount, $discount_percent, $paymentMethod, $promo_code, $conn);
            }
        } else {
            $response['message'] = 'Missing bank transfer details';
        }
        break;

    case 'paypal':
        if ($paypalEmail) {
            // Process PayPal payment and the order
            $response = processOrder($customerId, $orderItems, $totalAmount, $serviceFee, $deliveryFee, $totalOrder, $discount, $discount_percent, $paymentMethod, $promo_code, $conn);
        } else {
            $response['message'] = 'Missing PayPal details';
        }
        break;

    default:
        $response['message'] = 'Invalid payment method';
        break;
}

// Return the final response as JSON
echo json_encode($response);

function processOrder($customerId, $orderItems, $totalAmount, $serviceFee, $deliveryFee, $totalOrder, $discount, $discount_percent, $paymentMethod, $promo_code, $conn)
{
    $response = ['success' => false, 'message' => 'Unknown error occurred'];
    $conn->begin_transaction();

    try {
        // Validate total amount
        if ($totalAmount == 0) {
            throw new Exception("Your Order Cart is empty.");
        }
        $balance = null;
        // Check wallet balance
        $stmt = $conn->prepare("SELECT balance FROM wallets WHERE customer_id = ?");
        $stmt->bind_param("i", $customerId);
        $stmt->execute();
        $stmt->bind_result($balance);
        $stmt->fetch();
        $stmt->close();

        if ($balance === null || $balance < $totalAmount) {
            throw new Exception("Insufficient balance in wallet.");
        }

        // Select a random Admin
        // Step 1: Get the minimum and maximum unique_id for eligible admins
        $idRangeQuery = "SELECT MIN(unique_id) AS min_id, MAX(unique_id) AS max_id FROM admin_tbl WHERE role = 'Admin' AND restriction_id = 0 AND block_id = 0";
        $idRangeResult = $conn->query($idRangeQuery);
        $idRangeRow = $idRangeResult->fetch_assoc();
        $minId = $idRangeRow['min_id'];
        $maxId = $idRangeRow['max_id'];

        if ($minId !== null && $maxId !== null) {
            // Step 2: Generate a random unique_id within this range
            $randomId = rand($minId, $maxId);

            // Step 3: Find the first admin with an ID greater than or equal to this random ID
            $superAdminQuery = "SELECT unique_id FROM admin_tbl WHERE unique_id >= $randomId AND role = 'Admin' AND restriction_id = 0 AND block_id = 0 LIMIT 1";
            $superAdminResult = $conn->query($superAdminQuery);

            if ($superAdminResult->num_rows > 0) {
                $superAdmin = $superAdminResult->fetch_assoc();
                $superAdminUniqueId = $superAdmin['unique_id'];
            } else {
                throw new Exception("No eligible Super Admin found.");
            }
        } else {
            throw new Exception("No eligible Super Admin available.");
        }

        // Deduct amount from wallet
        $newBalance = $balance - $totalAmount;
        $stmt = $conn->prepare("UPDATE wallets SET balance = ? WHERE customer_id = ?");
        $stmt->bind_param("di", $newBalance, $customerId);
        $stmt->execute();
        $stmt->close();

        // Generate and insert transaction description
        $orderQuery = "SELECT order_id FROM orders ORDER BY order_id DESC LIMIT 1 FOR UPDATE";
        $orderResult = $conn->query($orderQuery);

        $newId = ($orderResult->num_rows > 0) ? $orderResult->fetch_assoc()['order_id'] + 1 : 1;
        $orderDescription = "Food Order: " . $newId;
        $stmt = $conn->prepare("INSERT INTO customer_transactions (customer_id, amount, date_created, transaction_type, payment_method, description) VALUES (?, ?, NOW(), 'debit', ?, ?)");
        $stmt->bind_param("idss", $customerId, $totalAmount, $paymentMethod, $orderDescription);
        $stmt->execute();
        $stmt->close();

        // Insert order
        $deliveryPin = rand(1000, 9999);
        $stmt = $conn->prepare("INSERT INTO orders (customer_id, order_date, service_fee, delivery_fee, total_order, discount, total_amount, delivery_pin, assigned_to) VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("idddddii", $customerId, $serviceFee, $deliveryFee, $totalOrder, $discount, $totalAmount, $deliveryPin, $superAdminUniqueId);
        $stmt->execute();
        $orderId = $stmt->insert_id;
        $stmt->close();

        // Insert items and update food quantities
        $stmt = $conn->prepare("INSERT INTO order_details (order_id, food_id, quantity, price_per_unit, total_price, order_date) VALUES (?, ?, ?, ?, ?, NOW())");
        foreach ($orderItems as $item) {
            $stmt->bind_param("iiidd", $orderId, $item['food_id'], $item['quantity'], $item['price_per_unit'], $item['total_price']);
            $stmt->execute();

            $updateStmt = $conn->prepare("UPDATE food SET available_quantity = available_quantity - ? WHERE food_id = ?");
            $updateStmt->bind_param("ii", $item['quantity'], $item['food_id']);
            $updateStmt->execute();
            $updateStmt->close();
        }
        $stmt->close();

        // Insert revenue data
        $revenueType = 2;
        $refundedAmount = 0.0;
        $stmt = $conn->prepare("INSERT INTO revenue (order_id, customer_id, total_amount, refunded_amount, retained_amount, transaction_date, revenue_type_id) VALUES (?, ?, ?, ?, ?, NOW(), ?)");
        $stmt->bind_param("iidddi", $orderId, $customerId, $totalAmount, $refundedAmount, $totalAmount, $revenueType);
        $stmt->execute();
        $stmt->close();

        // Promo code usage
        if ($promo_code !== "") {
            $stmt = $conn->prepare("INSERT INTO promo_usage (promo_code, customer_id, order_id, percentage_discount, discount_value, date_used) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("siidd", $promo_code, $customerId, $orderId, $discount_percent, $discount);
            $stmt->execute();
            $stmt->close();
        }
        function generateTransactionReference()
        {
            // Add a prefix for identification
            $prefix = 'TRX';

            // Generate a unique ID based on the current time with higher entropy (more randomness)
            $uniqueId = uniqid($prefix, true);

            // Generate a random number (for extra randomness)
            $randomNumber = mt_rand(1000, 9999);

            // Create the transaction reference
            $transactionRef = strtoupper($uniqueId . $randomNumber);

            // Optionally, remove any dots or special characters in the reference
            $transactionRef = str_replace('.', '', $transactionRef);

            return $transactionRef;
        }

        // Example usage
        $transactionReference = generateTransactionReference();
        $transaction_type = 'Credit';
        $status = 'Pending';
        $stmt = $conn->prepare("INSERT INTO transactions (transaction_ref, customer_id, order_id, transaction_type, amount, transaction_date, payment_method, status, created_at, revenue_type_id) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, NOW(), ?)");
        $stmt->bind_param("siisdssi", $transactionReference, $customerId, $orderId, $transaction_type, $totalAmount, $paymentMethod, $status, $revenueType);
        $stmt->execute();
        $stmt->close();

        // Admin notification
        $title = "Food Order for customer ID: " . $customerId;
        $eventType = "New Food Order";
        $eventDetails = "Customer placed an order on " . date('Y-m-d H:i:s');
        $stmt = $conn->prepare("INSERT INTO admin_notifications (event_title, event_type, event_details, created_at, user_id) VALUES (?, ?, ?, NOW(), ?)");
        $stmt->bind_param("ssss", $title, $eventType, $eventDetails, $superAdminUniqueId);
        $stmt->execute();
        $stmt->close();

        // Commit transaction
        $conn->commit();
        $response = ['success' => true, 'message' => 'Order placed successfully.'];

    } catch (Exception $e) {
        $conn->rollback();
        $response = ['success' => false, 'message' => "Error placing order: " . $e->getMessage()];
    }

    return $response;
}


function getDecryptedCardDetails($customerId, $dbConnection, $key, $iv)
{
    $encryptedCardNumber = '';
    $encryptedCVV = '';
    $expiryDate = '';
    $encryptedCardPIN = '';
    $query = "SELECT card_number, cvv, expiry_date, card_pin FROM cards WHERE customer_id = ?";
    $stmt = $dbConnection->prepare($query);
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $stmt->bind_result($encryptedCardNumber, $encryptedCVV, $expiryDate, $encryptedCardPIN);

    $cardDetails = [];
    while ($stmt->fetch()) {
        $cardDetails[] = [
            'card_number' => decrypt($encryptedCardNumber, $key, $iv),
            'cvv' => decrypt($encryptedCVV, $key, $iv),
            'expiry_date' => $expiryDate,
            'encryptedCardPIN' => $encryptedCardPIN
        ];
    }
    $stmt->close();

    return $cardDetails;
}

function validateCardOwnership($cardDetails, $inputCardNumber, $inputCVV, $inputExpiryDate)
{
    foreach ($cardDetails as $card) {
        if ($card['card_number'] === $inputCardNumber && $card['cvv'] === $inputCVV && $card['expiry_date'] === $inputExpiryDate) {
            return $card;
        }
    }
    return false;
}

function validateCardPIN($inputCardPIN, $encryptedCardPIN)
{
    return md5($inputCardPIN) === $encryptedCardPIN;
}

function validateCardPayment($customerId, $inputCardNumber, $inputCVV, $inputExpiryDate, $inputCardPIN, $dbConnection, $iv, $key)
{
    $cardDetails = getDecryptedCardDetails($customerId, $dbConnection, $key, $iv);
    $validCard = validateCardOwnership($cardDetails, $inputCardNumber, $inputCVV, $inputExpiryDate);
    if (!$validCard) {
        return ['success' => false, 'message' => 'Invalid Card Details.'];
    }
    if (!validateCardPIN($inputCardPIN, $validCard['encryptedCardPIN'])) {
        return ['success' => false, 'message' => 'Invalid card PIN.'];
    }
    return ['success' => true, 'message' => 'Card validated successfully.'];
}
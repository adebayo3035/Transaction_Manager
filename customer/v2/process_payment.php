<?php
session_start();
include 'config.php'; 
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

$customerId = $_SESSION['customer_id'] ?? null;
if (!$customerId) {
    echo json_encode(['success' => false, 'message' => 'Customer ID not found.']);
    exit;
}

// Decode the JSON input
$data = json_decode(file_get_contents('php://input'), true);

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
$totalAmount = $data['total_amount'] ?? 0;
$serviceFee = $data['service_fee'] ?? 0;
$deliveryFee = $data['delivery_fee'] ?? 0;
$totalOrder = $data['total_order'] ?? 0;
$bankName = $data['bank_name'] ?? null;
$bankAccount = $data['bank_account'] ?? null;
$paypalEmail = $data['paypal_email'] ?? null;

// Initialize the response
$response = ['success' => false, 'message' => 'Unknown error occurred'];

// Handle payment validation based on payment method
switch ($paymentMethod) {
    case 'Card':
        if ($cardNumber && $cardExpiry && $cardCvv && $cardPin) {
            // Perform credit card validation
            $validationResult = validateCardPayment($customerId, $cardNumber, $cardCvv, $cardExpiry, $cardPin, $conn, $encryption_iv, $encryption_key);
            if ($validationResult['success']) {
                // Proceed with order processing
                $response = processOrder($customerId, $orderItems, $totalAmount, $serviceFee, $deliveryFee, $totalOrder, $paymentMethod, $conn);
            } else {
                $response = $validationResult;
            }
        } else {
            $response['message'] = 'Missing credit card details';
        }
        break;

    case 'bank_transfer':
        if ($bankName && $bankAccount) {
            // Perform bank transfer validation (assuming basic validation)
            $response = processOrder($customerId, $orderItems, $totalAmount, $serviceFee, $deliveryFee, $totalOrder, $paymentMethod, $conn);
            $response['message'] = 'Congratulations! Bank transfer validation successful.';
        } else {
            $response['message'] = 'Missing bank transfer details';
        }
        break;

    case 'paypal':
        if ($paypalEmail) {
            // Process PayPal payment here
            $response = processOrder($customerId, $orderItems, $totalAmount, $serviceFee, $deliveryFee, $totalOrder, $paymentMethod, $conn);
            $response['message'] = 'PayPal payment processed successfully.';
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

function processOrder($customerId, $orderItems, $totalAmount, $serviceFee, $deliveryFee, $totalOrder, $paymentMethod, $conn) {
    $response = ['success' => false, 'message' => 'Unknown error occurred'];
    $conn->begin_transaction();
    $balance = '';
    $service_f = '';
    try {
        if ($totalAmount == 0) {
            throw new Exception("Your Order Cart is empty.");
        }

        // Check customer's wallet balance
        $stmt = $conn->prepare("SELECT balance FROM wallets WHERE customer_id = ?");
        $stmt->bind_param("i", $customerId);
        $stmt->execute();
        $stmt->bind_result($balance);
        $stmt->fetch();
        $stmt->close();

        if ($balance < $totalAmount) {
            throw new Exception("Insufficient balance in wallet.");
        }

        // Deduct the amount from Customer wallet
        $newBalance = $balance - $totalAmount;
        $stmt = $conn->prepare("UPDATE wallets SET balance = ? WHERE customer_id = ?");
        $stmt->bind_param("di", $newBalance, $customerId);
        $stmt->execute();
        $stmt->close();

        // Insert into customer transaction table
        $description = "Food Order";
        $stmt = $conn->prepare("INSERT INTO customer_transactions (customer_id, amount, date_created, transaction_type, payment_method, description) VALUES (?, ?, NOW(), 'debit', ?, ?)");
        $stmt->bind_param("idss", $customerId, $totalAmount, $paymentMethod, $description);
        $stmt->execute();
        $stmt->close();
        
        // Insert into orders table
        $stmt = $conn->prepare("INSERT INTO orders (customer_id, order_date, service_fee, delivery_fee, total_order, total_amount) VALUES (?, NOW(), ?, ?,?, ?)");
        $stmt->bind_param("idddd", $customerId, $serviceFee, $deliveryFee, $totalOrder, $totalAmount);
        $stmt->execute();
        $orderId = $stmt->insert_id;
        $stmt->close();

        // Insert into order_details table
        $stmt = $conn->prepare("INSERT INTO order_details (order_id, food_id, quantity, price_per_unit, total_price, order_date) VALUES (?, ?, ?, ?, ?, NOW())");
        foreach ($orderItems as $item) {
            $stmt->bind_param("iiidd", $orderId, $item['food_id'], $item['quantity'], $item['price_per_unit'], $item['total_price']);
            $stmt->execute();

            // Update available quantity for each food Item selected
            $update_query = "UPDATE food SET available_quantity = available_quantity - ? WHERE food_id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("ii", $item['quantity'], $item['food_id']);
            $update_stmt->execute();
            $update_stmt->close();
        }
        $stmt->close();

        // Insert revenue data
        $stmt = $conn->prepare("INSERT INTO revenue (order_id, customer_id, total_amount, transaction_date) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iid", $orderId, $customerId, $totalAmount);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        $response = ["success" => true, "message" => "Order placed successfully."];
    } catch (Exception $e) {
        $conn->rollback();
        $response = ["success" => false, "message" => "Error placing order: " . $e->getMessage()];
    }

    return $response;
}

function getDecryptedCardDetails($customerId, $dbConnection, $key, $iv) {
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

function validateCardOwnership($cardDetails, $inputCardNumber, $inputCVV, $inputExpiryDate) {
    foreach ($cardDetails as $card) {
        if ($card['card_number'] === $inputCardNumber && $card['cvv'] === $inputCVV && $card['expiry_date'] === $inputExpiryDate) {
            return $card;
        }
    }
    return false;
}

function validateCardPIN($inputCardPIN, $encryptedCardPIN) {
    return md5($inputCardPIN) === $encryptedCardPIN;
}

function validateCardPayment($customerId, $inputCardNumber, $inputCVV, $inputExpiryDate, $inputCardPIN, $dbConnection, $iv, $key) {
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

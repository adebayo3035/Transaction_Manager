<?php
session_start();
include 'config.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Log the start of the script
logActivity("Payment processing script started.");

// Fetch customer ID from session
$customerId = $_SESSION['customer_id'] ?? null;
checkSession($customerId);
// Log the customer ID for debugging
logActivity("Customer ID found in session: " . $customerId);

// Decode the JSON input from request body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Check if JSON decoding was successful
if (json_last_error() !== JSON_ERROR_NONE) {
    logActivity("Invalid JSON input received.");
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

// Extract payment data from the received JSON
$paymentMethod = $data['payment_method'] ?? null;
$cardNumber = $data['card_number'] ?? null;
$cardExpiry = $data['card_expiry'] ?? null;
$cardCvv = $data['card_cvv'] ?? null;
$cardPin = $data['card_pin'] ?? null;

// Retrieve order data from the session
if (!isset($_SESSION['order_items']) || !isset($_SESSION['total_amount']) || !isset($_SESSION['service_fee']) || !isset($_SESSION['delivery_fee'])) {
    logActivity("Order data not found in session.");
    echo json_encode(['success' => false, 'message' => 'Order data not found in session.']);
    exit;
}
$orderItems = $_SESSION['order_items'] ?? [];
$totalOrder = $_SESSION['total_order'];
$serviceFee = $_SESSION['service_fee'];
$deliveryFee = $_SESSION['delivery_fee'];

// Validate the order data (e.g., recalculate totals)
$calculatedTotalAmount = array_reduce($orderItems, function ($sum, $item) {
    return $sum + ($item['price_per_unit'] * $item['quantity']);
}, 0);

$calculatedServiceFee = 0.06 * $calculatedTotalAmount;
$calculatedDeliveryFee = 0.10 * $calculatedTotalAmount;

// Format calculated values to 2 decimal places
$calculatedTotalAmountFormatted = number_format($calculatedTotalAmount, 2, '.', '');
$calculatedServiceFeeFormatted = number_format($calculatedServiceFee, 2, '.', '');
$calculatedDeliveryFeeFormatted = number_format($calculatedDeliveryFee, 2, '.', '');

// Compare calculated values with stored values
// Compare calculated values with stored values
if ($calculatedTotalAmountFormatted != $totalOrder || $calculatedServiceFeeFormatted != $serviceFee || $calculatedDeliveryFeeFormatted != $deliveryFee) {
    // Return detailed error response with calculated and stored values
    echo json_encode([
        'success' => false,
        'message' => 'Order data has been tampered with.',
        'data' => [
            'calculated' => [
                'total_amount' => $calculatedTotalAmountFormatted,
                'service_fee' => $calculatedServiceFeeFormatted,
                'delivery_fee' => $calculatedDeliveryFeeFormatted
            ],
            'stored' => [
                'total_amount' => $totalOrder,
                'service_fee' => $serviceFee,
                'delivery_fee' => $deliveryFee
            ],
            'order_items' => $orderItems // Include order items for further inspection
        ]
    ]);
    exit;
}

$bankName = $data['bank_name'] ?? null;
$bankAccount = $data['bank_account'] ?? null;
$paypalEmail = $data['paypal_email'] ?? null;

$secretAnswer = $data['customer_secret_answer'] ?? null;
$isCredit = $data['is_credit'] ?? false;

$usingPromo = $data['using_promo'] ?? false;
$promo_code = $data['promo_code'] ?? null;
$discount = $data['discount'] ?? 0;
$discount_percent = $data['discount_percent'] ?? 0;

// Log payment method and other details
logActivity("Payment method: " . $paymentMethod);
logActivity("Order items: " . json_encode($orderItems));
logActivity("Total order amount: " . $totalOrder);

// Apply the discount if promo is used
if ($usingPromo) {
    $totalAmount = $data['total_amount'] - $discount;
    logActivity("Promo code applied. Discount: " . $discount . ", Total amount after discount: " . $totalAmount);
} else {
    $totalAmount = $data['total_amount'] ?? 0;
    logActivity("No promo code applied. Total amount: " . $totalAmount);
}

// Initialize the response
$response = ['success' => false, 'message' => 'Unknown error occurred'];

$conn->begin_transaction();

try {

// Handle payment validation based on the payment method
switch ($paymentMethod) {
    case 'Card':
        if ($cardNumber && $cardExpiry && $cardCvv && $cardPin) {
            // Validate credit card details
            logActivity("Validating card payment for customer ID: " . $customerId);
            $validationResult = validateCardPayment($customerId, $cardNumber, $cardCvv, $cardExpiry, $cardPin, $conn, $encryption_iv, $encryption_key);
            if ($validationResult['success']) {
                // Proceed with order processing
                logActivity("Card validation successful for customer ID: " . $customerId);
                $response = processOrder($customerId, $orderItems, $totalAmount, $serviceFee, $deliveryFee, $totalOrder, $discount, $discount_percent, $paymentMethod, $promo_code, $conn, $isCredit);
                logActivity("Order processed successfully for customer ID: " . $customerId);
            } else {
                $response = $validationResult;
                logActivity("Card validation failed for customer ID: " . $customerId . ". Reason: " . $validationResult['message']);
            }
        } else {
            $response['message'] = 'Missing credit card details';
            logActivity("Missing credit card details for customer ID: " . $customerId);
        }
        break;

    case 'credit':
        logActivity("Checking secret answer supplied by customer ID: " . $customerId);
        if ($secretAnswer) {
            logActivity("Secret Answer Validation was successful for Customer ID: " . $customerId);
            logActivity("Checking credit eligibility for customer ID: " . $customerId);
            $eligibilityResult = checkCreditEligibility($conn, $customerId, $totalAmount, $secretAnswer);
            if ($eligibilityResult['success']) {
                // Process credit payment and the order
                logActivity("Credit eligibility check passed for customer ID: " . $customerId);
                $response = processOrder($customerId, $orderItems, $totalAmount, $serviceFee, $deliveryFee, $totalOrder, $discount, $discount_percent, $paymentMethod, $promo_code, $conn, $isCredit);
                logActivity("Credit order processed successfully for customer ID: " . $customerId);
            } else {
                $response = $eligibilityResult;
                logActivity("Credit eligibility check failed for customer ID: " . $customerId . ". Reason: " . $eligibilityResult['message']);
            }
        } else {
            $response['message'] = 'Invalid Secret Answer';
            logActivity("Invalid Secret Answer for customer ID: " . $customerId);
        }
        break;

    case 'bank_transfer':
        logActivity("Customer selected Bank as medium of Payment on customer ID: " . $customerId);
        // Define allowed banks and accounts
        $allowedBanks = ["providus", "wema"];
        if ($bankName && $bankAccount) {
            logActivity("Checking If customer has selected a bank Name and Account Number on customer ID: " . $customerId);
            // Check if the bank is allowed
            if (!in_array($bankName, $allowedBanks)) {
                $response['message'] = 'Unknown Bank Account';
                logActivity("Unknown bank account selected for customer ID: " . $customerId);
                exit;
            } else {
                // Validate bank transfer and process the order
                logActivity("Processing bank transfer for customer ID: " . $customerId);
                $response = processOrder($customerId, $orderItems, $totalAmount, $serviceFee, $deliveryFee, $totalOrder, $discount, $discount_percent, $paymentMethod, $promo_code, $conn, $isCredit);
                logActivity("Bank transfer order processed successfully for customer ID: " . $customerId);
            }
        } else {
            $response['message'] = 'Missing bank transfer details';
            logActivity("Missing bank transfer details for customer ID: " . $customerId);
            exit;
        }
        break;

    case 'paypal':
        if ($paypalEmail) {
            // Process PayPal payment and the order
            logActivity("Processing PayPal payment for customer ID: " . $customerId);
            $validatePaypal = validatePayPalPayment($customerId, $paypalEmail, );

            if ($validatePaypal['success']) {
                // Process credit payment and the order
                logActivity("Paypal Details Validation passed for customer ID: " . $customerId);
                $response = processOrder($customerId, $orderItems, $totalAmount, $serviceFee, $deliveryFee, $totalOrder, $discount, $discount_percent, $paymentMethod, $promo_code, $conn, $isCredit);
                logActivity("Credit order processed successfully for customer ID: " . $customerId);
                $response = processOrder($customerId, $orderItems, $totalAmount, $serviceFee, $deliveryFee, $totalOrder, $discount, $discount_percent, $paymentMethod, $promo_code, $conn, $isCredit);
                logActivity("PayPal order processed successfully for customer ID: " . $customerId);
            } else {
                $response = $validatePaypal;
                logActivity("Paypal Details Validation check failed for customer ID: " . $customerId . ". Reason: " . $validatePaypal['message']);
            }
        } else {
            $response['message'] = 'Missing PayPal details';
            logActivity("Missing PayPal details for customer ID: " . $customerId);
        }
        break;

    default:
        $response['message'] = 'Invalid payment method';
        logActivity("Invalid payment method for customer ID: " . $customerId);
        break;
}
} catch (Exception $e) {
    $conn->rollback();
    $response = ['success' => false, 'message' => "Error placing order: " . $e->getMessage()];
    logActivity("Error placing order for customer ID: " . $customerId . ". Error: " . $e->getMessage());
}

// Return the final response as JSON
logActivity("Final response: " . json_encode($response));
echo json_encode($response);

// Function to process the order
function processOrder($customerId, $orderItems, $totalAmount, $serviceFee, $deliveryFee, $totalOrder, $discount, $discount_percent, $paymentMethod, $promo_code, $conn, $isCredit)
{
    $response = ['success' => false, 'message' => 'Unknown error occurred'];
    $conn->begin_transaction();

    try {
        // Validate total amount
        if ($totalAmount == 0) {
            throw new Exception("Your Order Cart is empty.");
        }
        $balance = null;
        // Log the start of order processing
        logActivity("Starting order processing for customer ID: " . $customerId);

        // Check wallet balance if not using credit
        if ($isCredit === false) {
            logActivity("Checking wallet balance for customer ID: " . $customerId);
            $stmt = $conn->prepare("SELECT balance FROM wallets WHERE customer_id = ?");
            $stmt->bind_param("i", $customerId);
            $stmt->execute();
            $stmt->bind_result($balance);
            $stmt->fetch();
            $stmt->close();

            if ($balance === null || $balance < $totalAmount) {
                throw new Exception("Insufficient balance in wallet.");
            } else {
                // Deduct amount from wallet
                $newBalance = $balance - $totalAmount;
                $stmt = $conn->prepare("UPDATE wallets SET balance = ? WHERE customer_id = ?");
                $stmt->bind_param("di", $newBalance, $customerId);
                $stmt->execute();
                $stmt->close();
                logActivity("Amount deducted from wallet for customer ID: " . $customerId . ". New balance: " . $newBalance);
            }
        }

        // Select a random admin
        logActivity("Selecting a random admin for order assignment.");
        $idRangeQuery = "SELECT MIN(unique_id) AS min_id, MAX(unique_id) AS max_id FROM admin_tbl WHERE role = 'Admin' AND restriction_id = 0 AND block_id = 0";
        $idRangeResult = $conn->query($idRangeQuery);
        $idRangeRow = $idRangeResult->fetch_assoc();
        $minId = $idRangeRow['min_id'];
        $maxId = $idRangeRow['max_id'];

        if ($minId !== null && $maxId !== null) {
            $randomId = rand($minId, $maxId);
            $superAdminQuery = "SELECT unique_id FROM admin_tbl WHERE unique_id >= $randomId AND role = 'Admin' AND restriction_id = 0 AND block_id = 0 LIMIT 1";
            $superAdminResult = $conn->query($superAdminQuery);

            if ($superAdminResult->num_rows > 0) {
                $superAdmin = $superAdminResult->fetch_assoc();
                $superAdminUniqueId = $superAdmin['unique_id'];
                logActivity("Admin assigned for order: " . $superAdminUniqueId);
            } else {
                logActivity("No eligible Super Admin found.");
                throw new Exception("No eligible Super Admin found.");
            }
        } else {
            logActivity("No eligible Super Admin found.");
            throw new Exception("No eligible Super Admin available.");
        }

        // Insert order
        $deliveryPin = rand(1000, 9999);
        logActivity("Attempting to Insert Record into orders table.");
        $stmt = $conn->prepare("INSERT INTO orders (customer_id, order_date, service_fee, delivery_fee, total_order, discount, total_amount, delivery_pin, assigned_to, is_credit) VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("idddddiii", $customerId, $serviceFee, $deliveryFee, $totalOrder, $discount, $totalAmount, $deliveryPin, $superAdminUniqueId, $isCredit);
        $stmt->execute();
        $orderId = $stmt->insert_id;
        $stmt->close();
        logActivity("Order inserted successfully. Order ID: " . $orderId);

        // Insert items and update food quantities
        logActivity("Attempting to Insert Record into order details table.");
        $stmt = $conn->prepare("INSERT INTO order_details (order_id, food_id, quantity, price_per_unit, total_price, order_date) VALUES (?, ?, ?, ?, ?, NOW())");
        foreach ($orderItems as $item) {
            $stmt->bind_param("iiidd", $orderId, $item['food_id'], $item['quantity'], $item['price_per_unit'], $item['total_price']);
            $stmt->execute();
            logActivity("Attempting to Update food record table.");
            $updateStmt = $conn->prepare("UPDATE food SET available_quantity = available_quantity - ? WHERE food_id = ?");
            $updateStmt->bind_param("ii", $item['quantity'], $item['food_id']);
            $updateStmt->execute();
            $updateStmt->close();
        }
        $stmt->close();
        logActivity("Order items inserted and food quantities updated.");

        // Insert into credit order table if applicable
        if ($isCredit == true) {
            $dueDate = (new DateTime())->modify('+14 days')->format('Y-m-d');
            logActivity("Attempting to Insert Credit orders into credit_orders table.");
            $insertCreditOrderQuery = "INSERT INTO credit_orders (order_id, customer_id, total_credit_amount, due_date) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($insertCreditOrderQuery);
            $stmt->bind_param("iids", $orderId, $customerId, $totalAmount, $dueDate);
            $stmt->execute();
            $stmt->close();
            logActivity("Credit order inserted for customer ID: " . $customerId);
        }

        // Promo code usage
        if ($promo_code !== "") {
            logActivity("Attempting to Insert Record promo usage table. for Customer ID  $customerId");
            $stmt = $conn->prepare("INSERT INTO promo_usage (promo_code, customer_id, order_id, percentage_discount, discount_value, date_used) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("siidd", $promo_code, $customerId, $orderId, $discount_percent, $discount);
            $stmt->execute();
            $stmt->close();
            logActivity("Promo code usage recorded for customer ID: " . $customerId);
        }

        // Admin notification
        $title = "Food Order for customer ID: " . $customerId;
        $eventType = "New Food Order";
        $eventDetails = "Customer placed an order on " . date('Y-m-d H:i:s');
        $stmt = $conn->prepare("INSERT INTO admin_notifications (event_title, event_type, event_details, created_at, user_id) VALUES (?, ?, ?, NOW(), ?)");
        $stmt->bind_param("ssss", $title, $eventType, $eventDetails, $superAdminUniqueId);
        $stmt->execute();
        $stmt->close();
        logActivity("Admin notification sent for order ID: " . $orderId);

        // Commit transaction
        $conn->commit();
        unset($_SESSION['order_items']);
        unset($_SESSION['total_order']);
        unset($_SESSION['service_fee']);
        unset($_SESSION['delivery_fee']);
        $response = ['success' => true, 'message' => 'Order placed successfully.'];
        logActivity("Order processing completed successfully for customer ID: " . $customerId);
    } catch (Exception $e) {
        $conn->rollback();
        $response = ['success' => false, 'message' => "Error placing order: " . $e->getMessage()];
        logActivity("Error placing order for customer ID: " . $customerId . ". Error: " . $e->getMessage());
    }

    return $response;
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
    $currentDateTime = date('Y-m-d H:i:s');
    foreach ($cardDetails as $card) {
        if ($card['card_number'] === $inputCardNumber && $card['cvv'] === $inputCVV && $card['expiry_date'] === $inputExpiryDate && $card['expiry_date'] >= $currentDateTime) {
            return $card;
        }
    }
    return false;
}

function validateCardPIN($inputCardPIN, $encryptedCardPIN)
{
    return md5($inputCardPIN) === $encryptedCardPIN;
}

function checkCardValidity($inputExpiryDate)
{

}

// Function to validate card payment
function validateCardPayment($customerId, $inputCardNumber, $inputCVV, $inputExpiryDate, $inputCardPIN, $dbConnection, $iv, $key)
{
    logActivity("Validating card payment for customer ID: " . $customerId);
    $cardDetails = getDecryptedCardDetails($customerId, $dbConnection, $key, $iv);
    $validCard = validateCardOwnership($cardDetails, $inputCardNumber, $inputCVV, $inputExpiryDate);
    if (!$validCard) {
        logActivity("Invalid card details for customer ID: " . $customerId);
        return ['success' => false, 'message' => 'Invalid Card Details.'];
    }
    if (!validateCardPIN($inputCardPIN, $validCard['encryptedCardPIN'])) {
        logActivity("Invalid card PIN for customer ID: " . $customerId);
        return ['success' => false, 'message' => 'Invalid card PIN.'];
    }
    logActivity("Card validation successful for customer ID: " . $customerId);
    return ['success' => true, 'message' => 'Card validated successfully.'];
}

function validatePayPalPayment($customerId, $email)
{
    logActivity("Validating Paypal Payment Details for customer ID: " . $customerId);
    if ($email !== $_SESSION['email']) {
        logActivity("Invalid Paypal details for customer ID: " . $customerId);
        return ['success' => false, 'message' => 'Invalid Paypal email address.'];
    } else {
        logActivity("Paypal Details validation successful for customer ID: " . $customerId);
        return ['success' => true, 'message' => 'Paypal Details validated successfully.'];
    }

}

// Function to check credit eligibility
function checkCreditEligibility($conn, $customerId, $orderAmount, $providedAnswer, $creditThreshold = 100000)
{
    logActivity("Checking credit eligibility for customer ID: " . $customerId);
    try {
        // Step 1: Validate secret answer
        logActivity("Attempting secret Answer Validation for customer ID: " . $customerId);
        $secretQuery = "SELECT secret_answer FROM customers WHERE customer_id = ?";
        $secretStmt = $conn->prepare($secretQuery);
        $secretStmt->bind_param("i", $customerId);
        $secretStmt->execute();
        $secretResult = $secretStmt->get_result();

        if ($secretResult->num_rows === 0) {
            logActivity("Customer record (secret answer) not found for customer ID: " . $customerId);
            return ["success" => false, "message" => "Customer record not found."];
        }

        $customerData = $secretResult->fetch_assoc();
        $storedSecretAnswer = $customerData['secret_answer'];

        // Hash the provided answer and compare with the stored one
        if (md5($providedAnswer) !== $storedSecretAnswer) {
            logActivity("Secret Answer Validation failed for customer ID: " . $customerId);
            return ["success" => false, "message" => "Authentication failed."];
        }

        // Step 2: Fetch wallet balance
        logActivity("Attempting to check Wallet Balance for customer ID: " . $customerId);
        $walletQuery = "SELECT balance FROM wallets WHERE customer_id = ?";
        $walletStmt = $conn->prepare($walletQuery);
        $walletStmt->bind_param("i", $customerId);
        $walletStmt->execute();
        $walletResult = $walletStmt->get_result();
        if ($walletResult->num_rows === 0) {
            logActivity("Wallet record not found for customer ID: " . $customerId);
            return ["success" => false, "message" => "Customer wallet record not found."];
        }
        $wallet = $walletResult->fetch_assoc();
        $walletBalance = $wallet['balance'];

        // Step 3: Check for outstanding debts
        $debtQuery = "
        SELECT COUNT(*) AS outstanding_debts
        FROM credit_orders
        WHERE customer_id = ? AND repayment_status NOT IN ('Paid', 'Void') AND status != 'Declined'";
        $debtStmt = $conn->prepare($debtQuery);
        $debtStmt->bind_param("i", $customerId);
        $debtStmt->execute();
        $debtResult = $debtStmt->get_result();
        $debtData = $debtResult->fetch_assoc();
        $outstandingDebts = $debtData['outstanding_debts'];

        // Step 4: Check for defaulting behavior
        $defaultQuery = "
            SELECT COUNT(*) AS defaults FROM credit_orders WHERE customer_id = ? AND repayment_status IN ('Pending', 'Partially Paid') 
      AND due_date < NOW();";
        $defaultStmt = $conn->prepare($defaultQuery);
        $defaultStmt->bind_param("i", $customerId);
        $defaultStmt->execute();
        $defaultResult = $defaultStmt->get_result();
        $defaultData = $defaultResult->fetch_assoc();
        $defaults = $defaultData['defaults'];

        // Step 5: Check if customer has made enough orders
        $orderQuery = "
            SELECT COUNT(*) AS total_orders
            FROM orders
            WHERE customer_id = ? AND delivery_status = 'Delivered'";
        $orderStmt = $conn->prepare($orderQuery);
        $orderStmt->bind_param("i", $customerId);
        $orderStmt->execute();
        $orderResult = $orderStmt->get_result();
        $orderData = $orderResult->fetch_assoc();
        $totalOrders = $orderData['total_orders'];

        // Step 6: Eligibility checks
        if ($orderAmount > $creditThreshold) {
            logActivity("Order amount exceeds credit limit for customer ID: " . $customerId);
            return ["success" => false, "message" => "Order amount exceeds your credit limit. 🚫"];
        }

        if ($walletBalance >= $orderAmount) {
            logActivity("Customer has sufficient wallet balance for customer ID: " . $customerId);
            return ["success" => false, "message" => "You have enough funds! No credit needed. 💰"];
        }

        if ($outstandingDebts > 0) {
            logActivity("Outstanding debts found for customer ID: " . $customerId);
            return ["success" => false, "message" => "Kindly clear your outstanding debt to proceed. ⚠️"];
        }

        if ($defaults > 0) {
            logActivity("Past defaults found for customer ID: " . $customerId);
            return ["success" => false, "message" => "Credit not available due to past defaults. ❌"];
        }

        if ($totalOrders < 0) {
            logActivity("Insufficient order history for customer ID: " . $customerId);
            return ["success" => false, "message" => "Not enough order history to qualify. Kindly Order more! 📉"];
        }
        // If all checks pass
        logActivity("Credit eligibility check passed for customer ID: " . $customerId);
        return ["success" => true, "message" => "You're eligible for credit! 🎉"];
    } catch (Exception $e) {
        logActivity("Error checking credit eligibility for customer ID: " . $customerId . ". Error: " . $e->getMessage());
        return ["success" => false, "message" => "An error occurred: " . $e->getMessage()];
    }
}
<?php
session_start();
include 'config.php';
include 'sendOTPGmail.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
use Dompdf\Dompdf;
use Dompdf\Options;

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
$orderItems = $data['order_items'] ?? [];
$serviceFee = $data['service_fee'] ?? 0;
$deliveryFee = $data['delivery_fee'] ?? 0;
$totalOrder = $data['total_order'] ?? 0;
$secretAnswer = $data['customer_secret_answer'] ?? null;
$token = $data['customer_token'] ?? null;

$bankName = $data['bank_name'] ?? null;
$bankAccount = $data['bank_account'] ?? null;
$paypalEmail = $data['paypal_email'] ?? null;


$isCredit = $data['is_credit'] ?? false;

$usingPromo = $data['using_promo'] ?? false;
$promo_code = $data['promo_code'] ?? null;
$discount = $data['discount'] ?? 0;
$discount_percent = $data['discount_percent'] ?? 0;
$receiptHtml = $data['receipt_html'] ?? null;
$customer_email = $_SESSION['email'];

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
                $response = processOrder($customerId, $orderItems, $totalAmount, $serviceFee, $deliveryFee, $totalOrder, $discount, $discount_percent, $paymentMethod, $promo_code, $conn, $isCredit, $secretAnswer, $token, $receiptHtml, $customer_email);
                logActivity("Order processed successfully for customer ID: " . $customerId);
            } else {
                $response = $validationResult;
                logActivity("Card validation failed for customer ID: " . $customerId . ". Reason: " . $validationResult['message']);
                deactivateToken($customerId);
            }
        } else {
            $response['message'] = 'Missing credit card details';
            logActivity("Missing credit card details for customer ID: " . $customerId);
            deactivateToken($customerId);
        }
        break;

    case 'credit':
        logActivity("Checking credit eligibility for customer ID: " . $customerId);
        $eligibilityResult = checkCreditEligibility($conn, $customerId, $totalAmount, $secretAnswer);
        if ($eligibilityResult['success']) {
            // Process credit payment and the order
            logActivity("Credit eligibility check passed for customer ID: " . $customerId);
            $response = processOrder($customerId, $orderItems, $totalAmount, $serviceFee, $deliveryFee, $totalOrder, $discount, $discount_percent, $paymentMethod, $promo_code, $conn, $isCredit, $secretAnswer, $token, $receiptHtml, $customer_email);
            logActivity("Credit order processed successfully for customer ID: " . $customerId);
        } else {
            $response = $eligibilityResult;
            logActivity("Credit eligibility check failed for customer ID: " . $customerId . ". Reason: " . $eligibilityResult['message']);
            deactivateToken($customerId);
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
                deactivateToken($customerId);
            } else {
                // Validate bank transfer and process the order
                logActivity("Processing bank transfer for customer ID: " . $customerId);
                $response = processOrder($customerId, $orderItems, $totalAmount, $serviceFee, $deliveryFee, $totalOrder, $discount, $discount_percent, $paymentMethod, $promo_code, $conn, $isCredit, $secretAnswer, $token, $receiptHtml, $customer_email);
                logActivity("Bank transfer order processed successfully for customer ID: " . $customerId);
            }
        } else {
            $response['message'] = 'Missing bank transfer details';
            logActivity("Missing bank transfer details for customer ID: " . $customerId);
            deactivateToken($customerId);
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
                // $response = processOrder($customerId, $orderItems, $totalAmount, $serviceFee, $deliveryFee, $totalOrder, $discount, $discount_percent, $paymentMethod, $promo_code, $conn, $isCredit);
                // logActivity("Credit order processed successfully for customer ID: " . $customerId);
                $response = processOrder($customerId, $orderItems, $totalAmount, $serviceFee, $deliveryFee, $totalOrder, $discount, $discount_percent, $paymentMethod, $promo_code, $conn, $isCredit, $secretAnswer, $token, $receiptHtml, $customer_email);
                logActivity("PayPal order processed successfully for customer ID: " . $customerId);
            } else {
                $response = $validatePaypal;
                logActivity("Paypal Details Validation check failed for customer ID: " . $customerId . ". Reason: " . $validatePaypal['message']);
                deactivateToken($customerId);
            }
        } else {
            $response['message'] = 'Missing PayPal details';
            logActivity("Missing PayPal details for customer ID: " . $customerId);
            deactivateToken($customerId);
        }
        break;

    default:
        $response['message'] = 'Invalid payment method';
        logActivity("Invalid payment method for customer ID: " . $customerId);
        deactivateToken($customerId);
        break;
}

// Return the final response as JSON
logActivity("Final response: " . json_encode($response));
echo json_encode($response);

// Function to process the order
function processOrder($customerId, $orderItems, $totalAmount, $serviceFee, $deliveryFee, $totalOrder, $discount, $discount_percent, $paymentMethod, $promo_code, $conn, $isCredit, $providedSecretAnswer, $token, $receiptHtml, $customer_email)
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

        // **Authenticate Customer**
        $CustomerValidation = AuthValidations($providedSecretAnswer, $customerId, $conn, $token);
        if (!$CustomerValidation['success']) {
            throw new Exception($CustomerValidation['message']);
        } else {
            logActivity("Secret Answer and Token Validation successful for Customer ID: " . $customerId);
        }


        // Check wallet balance if not using credit
        if ($isCredit === false) {
            logActivity("Checking wallet balance for customer ID: " . $customerId);
            $stmt = $conn->prepare("SELECT balance FROM wallets WHERE customer_id = ?");
            $stmt->bind_param("i", $customerId);
            $stmt->execute();
            $stmt->bind_result($balance);
            $stmt->fetch();
            $stmt->close();
            logActivity("Customer's Wallet Balance is: $balance");

            if ($balance === null || $balance < $totalAmount) {
                logActivity("Insufficient wallet balance to process Order");
                throw new Exception("Insufficient balance in wallet.");

            } else {
                // Deduct amount from wallet
                logActivity("Attempting to Debit customer's wallet to process Order");
                $newBalance = $balance - $totalAmount;

                $stmt = $conn->prepare("UPDATE wallets SET balance = ? WHERE customer_id = ?");
                $stmt->bind_param("di", $newBalance, $customerId);
                $stmt->execute();
                $stmt->close();
                logActivity("Amount deducted from wallet for customer ID: $customerId is $totalAmount");
                logActivity("New wallet balance for customer ID: " . $customerId . ". New balance: " . $newBalance);
            }
        }

        // Select a random admin
        logActivity("Selecting a random admin for order assignment.");

        // Fetch all eligible admins
        $adminQuery = "SELECT unique_id FROM admin_tbl WHERE role = 'Admin' AND restriction_id = 0 AND block_id = 0";
        $adminResult = $conn->query($adminQuery);

        if ($adminResult->num_rows > 0) {
            // Store all eligible admin IDs in an array
            $adminIds = [];
            while ($row = $adminResult->fetch_assoc()) {
                $adminIds[] = $row['unique_id'];
            }

            // Select a random admin ID from the array
            $randomIndex = array_rand($adminIds);
            $superAdminUniqueId = $adminIds[$randomIndex];

            logActivity("Admin assigned for order: " . $superAdminUniqueId);
        } else {
            logActivity("No eligible Super Admin found.");
            throw new Exception("No eligible Super Admin found.");
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

        $title = "Food Order for customer ID: " . $customerId;
        $eventType = "New Food Order";
        $eventDetails = "Customer placed an order on " . date('Y-m-d H:i:s');
        $logMessage = "Admin notification sent for order ID: " . $orderId;
        
        sendAdminNotification($conn, $title, $eventType, $eventDetails, $superAdminUniqueId, $logMessage);
        

        // Commit transaction
        $conn->commit();
        $response = ['success' => true, 'message' => 'Order placed successfully.'];
        logActivity("Attempting to Invalidate Token from Session to prevent token reuse");
        // Invalidate the token after successful operation
        // $_SESSION['token'] = [
        //     'value' => '',
        //     'expires_at' => time() // Token expired immediately
        // ];
        deactivateToken($customerId);
        logActivity("Token invalidated for customer ID: $customerId.");
        logActivity("Order processing completed successfully for customer ID: " . $customerId);

        // Send transaction receipt to customer's email address
        try {
            // Generate PDF - pass the actual order ID from your order processing
            $pdfPath = generatePdfReceipt($receiptHtml, $orderId); // $orderId should come from your order processing

            // Send email with PDF attachment
            $subject = "Your KaraKata Order Receipt #" . $orderId;
            $emailBody = "Thank you for your purchase! Your receipt is attached.";

            $emailSent = sendEmailWithGmailSMTP(
                $customer_email,
                $emailBody,
                $subject,
                [$pdfPath] // Attach the PDF
            );

            // Handle email sending result
            if (!$emailSent) {
                logActivity("Failed to send receipt email for order #" . $orderId);
                // You might want to still proceed with success since the order was processed
            } else {
                logActivity("Receipt email sent successfully for order #" . $orderId);
            }

            // Clean up temporary file
            if (file_exists($pdfPath)) {
                unlink($pdfPath);
            }
        } catch (Exception $e) {
            logActivity("Error generating/sending receipt for order #" . $orderId . ": " . $e->getMessage());
            // Continue with order processing despite email failure
        }
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
        if (($card['card_number'] === $inputCardNumber) && ($card['cvv'] === $inputCVV) && ($card['expiry_date'] === $inputExpiryDate)) {
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
    // Ensure the expiry date format is correct
    if (!preg_match('/^\d{2}-\d{4}$/', $inputExpiryDate)) {
        logActivity("Invalid expiry date format: $inputExpiryDate");
        return true; // Treat as expired if format is incorrect
    }

    // Parse expiry_date into month and year
    list($expiryMonth, $expiryYear) = explode('-', $inputExpiryDate);

    // Convert to integers to prevent string comparison issues
    $expiryMonth = (int) $expiryMonth;
    $expiryYear = (int) $expiryYear;

    // Get current month and year
    $currentMonth = (int) date('m'); // Current month (e.g., 09)
    $currentYear = (int) date('Y');  // Current year (e.g., 2023)

    // Check if the card has expired
    $isExpired = ($expiryYear < $currentYear) || ($expiryYear == $currentYear && $expiryMonth < $currentMonth);

    logActivity("Card expiry date: $inputExpiryDate, Is expired?: " . ($isExpired ? 'Yes' : 'No'));

    return $isExpired; // Return true if expired, false otherwise
}



// Function to validate card payment
function validateCardPayment($customerId, $inputCardNumber, $inputCVV, $inputExpiryDate, $inputCardPIN, $dbConnection, $iv, $key)
{
    logActivity("Validating card payment for customer ID: " . $customerId);
    $cardDetails = getDecryptedCardDetails($customerId, $dbConnection, $key, $iv);
    $cardDetails = getDecryptedCardDetails($customerId, $dbConnection, $key, $iv);
    logActivity("Decrypted Card Details: " . print_r($cardDetails, true));
    logActivity("Input Card Number: " . $inputCardNumber);
    logActivity("Input CVV: " . $inputCVV);
    logActivity("Input Expiry Date: " . $inputExpiryDate);
    $validCard = validateCardOwnership($cardDetails, $inputCardNumber, $inputCVV, $inputExpiryDate);

    if (!$validCard) {
        logActivity("Invalid card details for customer ID: " . $customerId);
        return ['success' => false, 'message' => 'Invalid Card Details.'];
    }

    // Check if the card is expired
    if (checkCardValidity($inputExpiryDate)) {
        logActivity("Expired card for customer ID: " . $customerId);
        return ['success' => false, 'message' => 'Card has expired.'];
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
function checkCreditEligibility($conn, $customerId, $orderAmount, $creditThreshold = 100000)
{
    logActivity("Checking credit eligibility for customer ID: " . $customerId);
    try {

        // Step 2: Fetch wallet balance
        logActivity("Attempting to check Wallet Balance for customer ID: " . $customerId);
        $WalletBalanceresponse = getWalletBalance($conn, $customerId);
        if ($WalletBalanceresponse['success']) {
            $walletBalance = $WalletBalanceresponse['balance'];
            // Proceed with wallet balance
        } else {
            echo $WalletBalanceresponse['message'];
        }
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

function validateSecretAnswer($providedSecretAnswer, $customerId, $conn)
{
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
    if (md5($providedSecretAnswer) !== $storedSecretAnswer) {
        logActivity("Secret Answer Validation failed for customer ID: " . $customerId);
        return ["success" => false, "message" => "Authentication failed."];
    }
    logActivity("Secret Answer Validation is Successful for Customer ID " . $customerId);
    return ["success" => true, "message" => "Secret Answer Validation Successful! 🎉"];
}

function getWalletBalance($conn, $customerId)
{
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
    return ["success" => true, "balance" => $wallet['balance']];
}

function AuthValidations($providedSecretAnswer, $customerId, $conn, $token)
{
    // Validate Secret Answer
    $secretValidation = validateSecretAnswer($providedSecretAnswer, $customerId, $conn);
    if (!$secretValidation['success']) {
        return $secretValidation; // Return error response if validation fails
    }

    // Validate Token
    if (
        !isset($_SESSION['token']) ||
        $_SESSION['token']['value'] !== $token ||
        time() > $_SESSION['token']['expires_at']
    ) {

        logActivity("Invalid or expired token for customer ID: $customerId.");
        return ['success' => false, 'message' => 'Invalid or expired token'];
    }

    return ['success' => true, 'message' => 'Authentication successful'];
}

function deactivateToken($customerId)
{
    $_SESSION['token'] = [
        'value' => '',
        'expires_at' => time() // Token expired immediately
    ];
    logActivity("Token invalidated for customer ID: $customerId.");
}

/**
 * Generates a PDF file from HTML and returns the file path.
 */
// Modified PDF generation function
function generatePdfReceipt($html, $orderId)
{
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Helvetica');

    // Prepend CSS to the HTML
    $styledHtml = '<!DOCTYPE html><html><head>' .
        getReceiptCss() .
        '</head><body>' .
        $html .
        '</body></html>';

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($styledHtml); // Use the styled HTML
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Create temp directory if it doesn't exist
    $tempDir = sys_get_temp_dir() . '/karakata_receipts';
    if (!file_exists($tempDir)) {
        mkdir($tempDir, 0755, true);
    }

    // Save PDF with order-specific filename
    $pdfPath = $tempDir . "/receipt_{$orderId}.pdf";
    file_put_contents($pdfPath, $dompdf->output());

    return $pdfPath;
}

//styling for receipt to be sent to email
function getReceiptCss()
{
    return '
    <style>
        /* Copy the contents of your receipt.css here */
       
                    * {
                        margin: 0;
                        padding: 0;
                        box-sizing: border-box;
                    }
                    body {
                        background-color: #f9f9f9;
                        color: #333;
                        line-height: 1.6;
                        padding: 20px;
                    }
                    .receipt-container {
                        max-width: 600px;
                        margin: 0 auto;
                        background-color: #fff;
                        border-radius: 10px;
                        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
                        padding: 20px;
                    }
                    .receipt-header {
                        text-align: center;
                        margin-bottom: 20px;
                    }
                    .receipt-header h1 {
                        font-size: 24px;
                        font-weight: 700;
                        color: #2c3e50;
                        margin-bottom: 10px;
                    }
                    .receipt-header h2 {
                        font-size: 20px;
                        font-weight: 600;
                        color: #34495e;
                        margin-bottom: 10px;
                    }
                    .receipt-header p {
                        font-size: 14px;
                        color: #7f8c8d;
                    }
                    .order-summary {
                        margin-bottom: 20px;
                    }
                    .order-item {
                        display: flex;
                        justify-content: space-between;
                        padding: 4px 10px;
                    }
                    #orderSummaryTable {
                        width: 100%;
                        border-collapse: collapse;
                        margin-top: 20px;
                    }
                    #orderSummaryTable th,
                    #orderSummaryTable td {
                        border: 1px solid #ddd;
                        padding: 12px;
                        text-align: left;
                        font-size: 14px;
                    }
                    #orderSummaryTable th {
                        background-color: #f8f9fa;
                        font-weight: 600;
                        color: #2c3e50;
                    }
                    #orderSummaryTable tbody tr:nth-child(even) {
                        background-color: #f9f9f9;
                    }
                    #orderSummaryTable tbody tr:hover {
                        background-color: #f1f1f1;
                    }
                    .order-total {
                        margin-top: 20px;
                        padding: 15px;
                        background-color: #f8f9fa;
                        border-radius: 5px;
                        text-align: right;
                    }
                    .order-total p {
                        font-size: 16px;
                        font-weight: 600;
                        color: #2c3e50;
                    }
                    .receipt-footer {
                        text-align: center;
                        margin-top: 20px;
                        padding-top: 20px;
                        border-top: 1px solid #ddd;
                    }
                    .receipt-footer p {
                        font-size: 14px;
                        color: #7f8c8d;
                    }
                    @media (max-width: 600px) {
                        .receipt-container {
                            padding: 15px;
                        }
                        .receipt-header h1 {
                            font-size: 20px;
                        }
                        .receipt-header h2 {
                            font-size: 18px;
                        }
                        #orderSummaryTable th,
                        #orderSummaryTable td {
                            padding: 10px;
                            font-size: 12px;
                        }
                        .order-total p {
                            font-size: 14px;
                        }
                    }
    </style>
    ';
}



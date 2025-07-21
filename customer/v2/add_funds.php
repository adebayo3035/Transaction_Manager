<?php
session_start();
include('config.php');
header('Content-Type: application/json');

// Constants
const MAX_DAILY_DEPOSIT = 300000;
const MAX_SINGLE_DEPOSIT = 100000;
const LATE_FEE_PERCENTAGE = 0.3;

// Initialize logging
logActivity("Wallet funding script initialized for session: " . session_id());

try {
    // Validate session and input
    validateSession();
    $input = validateAndSanitizeInput();
    
    // Check daily deposit limits
    checkDepositLimits($input['customerId'], $input['amount']);
    
    // Verify card details
    $cardDetails = verifyCardDetails($input['customerId'], $input['cardNumber'], $input['pin'], $input['cvv'],);
    
    // Process wallet funding transaction
    $transactionResult = processWalletFunding(
        $input['customerId'], 
        $input['amount'], 
        $input['cardNumber'], 
        $cardDetails
    );
    
    // Handle any outstanding debts
    handleOutstandingDebts($input['customerId'], $transactionResult['newBalance']);
    
    // Return success response
    sendSuccessResponse($transactionResult);
    
} catch (InvalidTokenException $e) {
    handleError("Invalid or expired token", 401);
} catch (InvalidAmountException $e) {
    handleError($e->getMessage(), 400);
} catch (CardException $e) {
    handleError($e->getMessage(), 400);
} catch (DailyLimitException $e) {
    handleError($e->getMessage(), 400);
} catch (DatabaseException $e) {
    handleError("Database operation failed: " . $e->getMessage(), 500);
} catch (Exception $e) {
    handleError("Unexpected error: " . $e->getMessage(), 500);
}

// ==================== FUNCTIONS ====================

/**
 * Validate session and CSRF token
 */
function validateSession() {
    $customerId = $_SESSION["customer_id"] ?? null;
    if (!$customerId) {
        throw new Exception("No active session found");
    }
    
    logActivity("Session validated for customer ID: $customerId");
    
    // Validate CSRF token
    $token = $_POST['token'] ?? '';
    if (!isset($_SESSION['token']) || $_SESSION['token']['value'] !== $token || time() > $_SESSION['token']['expires_at']) {
        logActivity("Invalid or expired token detected");
        throw new InvalidTokenException();
    }
}

/**
 * Validate and sanitize input parameters
 */
function validateAndSanitizeInput(): array {
    $input = [
        'token' => $_POST['token'] ?? '',
        'pin' => $_POST['pin'] ?? '',
        'amount' => $_POST['amount'] ?? '',
        'cardNumber' => $_POST['card_number'] ?? '',
        'cvv' => $_POST['card_cvv'] ?? '',
        'customerId' => $_SESSION['customer_id'] ?? ''
    ];
    
    // Validate amount
    if (!is_numeric($input['amount'])) {
        throw new InvalidAmountException("Invalid amount format");
    }
    if (!is_numeric($input['cvv'])) {
        throw new InvalidAmountException("Invalid Card Details - CVV");
    }
    if (strlen($input['cvv']) != 3) {
        throw new InvalidAmountException("Invalid Card Details - CVV");
    }
     if (strlen($input['cardNumber']) != 16) {
        throw new InvalidAmountException("Invalid Card Details - Card Number");
    }
    
    $input['amount'] = (float)$input['amount'];
    
    if ($input['amount'] <= 0) {
        throw new InvalidAmountException("Amount must be greater than zero");
    }
    
    if ($input['amount'] > MAX_SINGLE_DEPOSIT) {
        throw new InvalidAmountException("Amount exceeds maximum single deposit limit");
    }
    
    logActivity("Input validation passed for customer ID: " . $input['customerId']);
    
    return $input;
}

/**
 * Check daily deposit limits
 */
function checkDepositLimits($customerId, $amount) {
    global $conn;
    
    $cumulativeDeposit = getCumulativeDeposit($customerId, $conn);
    $projectedTotal = $cumulativeDeposit + $amount;
    
    logActivity("Checking deposit limits - Current: $cumulativeDeposit, Projected: $projectedTotal");
    
    if ($projectedTotal > MAX_DAILY_DEPOSIT) {
        throw new DailyLimitException("This transaction will exceed your daily deposit limit");
    }
}

/**
 * Verify card details including PIN and expiration
 */
function verifyCardDetails($customerId, $cardNumber, $pin, $cvv) {
    global $conn, $encryption_key, $encryption_iv;
    
    logActivity("Verifying card details for customer ID: $customerId");
    
    $encryptedCardNumber = encrypt($cardNumber, $encryption_key, $encryption_iv);
    $encryptedCVV = encrypt($cvv, $encryption_key, $encryption_iv);
    $encryptedPin = md5($pin);
    
    $cardDetails = getStoredPinHash($customerId, $encryptedCardNumber, $conn);
    
    if (!$cardDetails) {
        throw new CardException("Card not found");
    }
    
    if ($cardDetails['is_expired']) {
        throw new CardException("Your card has expired");
    }
    
    if ($cardDetails['card_status'] !== "Active") {
        throw new CardException("Your card is inactive. Please add another card");
    }
    
    if ($encryptedPin !== $cardDetails['card_pin']) {
        throw new CardException("Invalid card details");
    }
    if ($encryptedCVV !== $cardDetails['cvv']) {
        throw new CardException("Invalid card details - CVV");
    }
    
    logActivity("Card verification successful for customer ID: $customerId");
    
    return $cardDetails;
}

/**
 * Process wallet funding transaction
 */
function processWalletFunding($customerId, $amount, $cardNumber, $cardDetails): array {
    global $conn;
    
    logActivity("Starting wallet funding process for customer ID: $customerId, Amount: $amount");
    
    $conn->begin_transaction();
    
    try {
        // Check or create wallet
        $walletData = checkWalletExists($customerId, $conn);
        $currentBalance = $walletData ? $walletData['balance'] : 0;
        
        if ($walletData) {
            // Update existing wallet
            $stmt = $conn->prepare("UPDATE wallets SET balance = balance + ?, date_last_updated = NOW() WHERE wallet_id = ?");
            $stmt->bind_param("di", $amount, $walletData['wallet_id']);
            $stmt->execute();
            logActivity("Updated wallet balance for customer ID: $customerId");
        } else {
            // Create new wallet
            $stmt = $conn->prepare("INSERT INTO wallets (customer_id, balance, date_last_updated) VALUES (?, ?, NOW())");
            $stmt->bind_param("id", $customerId, $amount);
            $stmt->execute();
            $walletId = $stmt->insert_id;
            logActivity("Created new wallet for customer ID: $customerId, Wallet ID: $walletId");
        }
        
        // Record transaction
        $transactionReference = generateTransactionReference();
        $description = "Wallet Funding";
        $paymentMethod = 'Card';
        
        $stmt = $conn->prepare("
            INSERT INTO customer_transactions 
            (transaction_ref, customer_id, amount, date_created, transaction_type, payment_method, description) 
            VALUES (?, ?, ?, NOW(), 'credit', ?, ?)
        ");
        $stmt->bind_param("sidss", $transactionReference, $customerId, $amount, $paymentMethod, $description);
        $stmt->execute();
        
        logActivity("Recorded transaction for customer ID: $customerId, Reference: $transactionReference");
        
        $conn->commit();
        
        return [
            'newBalance' => $currentBalance + $amount,
            'transactionReference' => $transactionReference,
            'cardNumber' => $cardNumber
        ];
        
    } catch (Exception $e) {
        $conn->rollback();
        logActivity("Wallet funding failed for customer ID: $customerId. Error: " . $e->getMessage());
        throw new DatabaseException("Wallet funding transaction failed");
    }
}

/**
 * Handle any outstanding debts for the customer
 */
function handleOutstandingDebts($customerId, $currentWalletBalance) {
    global $conn;
    
    logActivity("Checking for outstanding debts for customer ID: $customerId");
    
    $outstandingDebt = getOutstandingDebt($customerId, $conn);
    if (!$outstandingDebt) {
        logActivity("No outstanding debts found for customer ID: $customerId");
        return;
    }
    
    $creditOrderId = $outstandingDebt['credit_order_id'];
    $orderId = $outstandingDebt['order_id'];
    $totalCreditAmount = $outstandingDebt['total_credit_amount'];
    $amountPaid = $outstandingDebt['amount_paid'];
    $debtAmount = $outstandingDebt['remaining_balance'];
    $dueDate = $outstandingDebt['due_date'];
    
    logActivity("Processing debt repayment for order ID: $orderId, Amount due: $debtAmount");
    
    // Check for late payment
    $currentDate = date('Y-m-d H:i:s');
    if ($dueDate < $currentDate) {
        $latePaymentFee = calculateLatePaymentFee($totalCreditAmount);
        logActivity("Late payment detected. Fee calculated: $latePaymentFee");
        
        if ($currentWalletBalance < ($debtAmount + $latePaymentFee)) {
            $message = 'Insufficient funds. Your credit is overdue. Total amount due: ' . 
                      number_format($debtAmount, 2) . ' and a late payment fee of: ' . 
                      number_format($latePaymentFee, 2);
            logActivity("Insufficient funds for late payment: $message");
            throw new InsufficientFundsException($message);
        }
        
        // Deduct late payment fee
        insertLatePaymentFee($orderId, $customerId, $latePaymentFee, $conn);
        $currentWalletBalance -= $latePaymentFee;
        updateWalletBalance($customerId, $currentWalletBalance, $conn);
    }
    
    // Process repayment
    processRepayment(
        $creditOrderId,
        $orderId,
        $customerId,
        $debtAmount,
        $amountPaid,
        $currentWalletBalance,
        $conn
    );
}

/**
 * Send success response
 */
function sendSuccessResponse(array $result) {
    $_SESSION['token'] = [
        'value' => '',
        'expires_at' => time() // Invalidate token
    ];
    
    logActivity("Transaction completed successfully for customer ID: " . $_SESSION['customer_id'] . 
               ", Reference: " . $result['transactionReference']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Transaction successful',
        'transactionReference' => $result['transactionReference'],
        'cardNumber' => $result['cardNumber']
    ]);
}

/**
 * Handle errors and send JSON response
 */
function handleError(string $message, int $statusCode = 400) {
    http_response_code($statusCode);
    logActivity("Error: $message");
    echo json_encode([
        'success' => false,
        'message' => $message
    ]);
    exit;
}

// ==================== HELPER FUNCTIONS ====================

function generateTransactionReference(): string {
    $prefix = 'TRX';
    $uniqueId = uniqid($prefix, true);
    $randomNumber = mt_rand(1000, 9999);
    return strtoupper(str_replace('.', '', $uniqueId . $randomNumber));
}

function getStoredPinHash($customerId, $cardNumber, $conn): ?array {
    logActivity("Fetching stored card details for customer ID: $customerId");
    
    $stmt = $conn->prepare("SELECT card_pin, cvv, expiry_date, status FROM cards WHERE customer_id = ? AND card_number = ?");
    $stmt->bind_param("is", $customerId, $cardNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if (!$row) {
        logActivity("No card found for customer ID: $customerId");
        return null;
    }
    
    // Parse expiry date
    list($expiryMonth, $expiryYear) = explode('-', $row['expiry_date']);
    $currentMonth = date('m');
    $currentYear = date('Y');
    
    $isExpired = ($expiryYear < $currentYear) || 
                 ($expiryYear == $currentYear && $expiryMonth < $currentMonth);
    
    logActivity("Card status - Expiry: {$row['expiry_date']}, Status: {$row['status']}, Expired: " . ($isExpired ? 'Yes' : 'No'));
    
    return [
        'card_pin' => $row['card_pin'],
        'expiry_date' => $row['expiry_date'],
        'cvv' => $row['cvv'],
        'is_expired' => $isExpired,
        'card_status' => $row['status']
    ];
}

function checkWalletExists($customerId, $conn): ?array {
    logActivity("Checking wallet existence for customer ID: $customerId");
    
    $stmt = $conn->prepare("SELECT wallet_id, balance FROM wallets WHERE customer_id = ?");
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if ($row) {
        logActivity("Wallet found - ID: {$row['wallet_id']}, Balance: {$row['balance']}");
        return $row;
    }
    
    logActivity("No wallet found for customer ID: $customerId");
    return null;
}
function getCumulativeDeposit($customerId, $conn): float {
    logActivity("Calculating cumulative deposits for customer ID: $customerId");
    
    $today = date('Y-m-d');
    $stmt = $conn->prepare("
        SELECT SUM(amount) AS total 
        FROM customer_transactions 
        WHERE customer_id = ? 
        AND DATE(date_created) = ? 
        AND transaction_type = 'credit'
    ");
    $stmt->bind_param("is", $customerId, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $total = $row ? (float)$row['total'] : 0;
    
    logActivity("Cumulative deposits today: $total");
    return $total;
}

function getOutstandingDebt($customerId, $conn): ?array {
    logActivity("Checking for outstanding debts for customer ID: $customerId");
    
    $stmt = $conn->prepare("
        SELECT * FROM credit_orders 
        WHERE customer_id = ? 
        AND (repayment_status = 'Pending' OR repayment_status = 'Partially Paid') 
        AND status = 'Approved'
        ORDER BY created_at ASC 
        LIMIT 1
    ");
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $debt = $result->fetch_assoc();
    
    if ($debt) {
        logActivity("Found outstanding debt - Order ID: {$debt['order_id']}, Amount: {$debt['remaining_balance']}");
    } else {
        logActivity("No outstanding debts found");
    }
    
    return $debt ?: null;
}

function calculateLatePaymentFee($totalCreditAmount): float {
    $fee = LATE_FEE_PERCENTAGE * $totalCreditAmount;
    logActivity("Calculated late payment fee: $fee");
    return $fee;
}

function processRepayment($creditOrderId, $orderId, $customerId, $debtAmount, $amountPaid, $currentWalletBalance, $conn) {
    logActivity("Processing repayment for credit order ID: $creditOrderId");
    
    if ($currentWalletBalance >= $debtAmount) {
        $repaymentStatus = 'Paid';
        $newRemainingBalance = 0.00;
        $newWalletBalance = $currentWalletBalance - $debtAmount;
        $amountToPay = $debtAmount;
        logActivity("Full repayment possible - Amount: $amountToPay");
    } else {
        $repaymentStatus = 'Partially Paid';
        $newRemainingBalance = $debtAmount - $currentWalletBalance;
        $newWalletBalance = 0.00;
        $amountToPay = $currentWalletBalance;
        logActivity("Partial repayment - Amount: $amountToPay, Remaining: $newRemainingBalance");
    }

    $newAmountPaid = $amountPaid + $amountToPay;

    // Record the repayment
    transRepayment($creditOrderId, $orderId, $customerId, $amountToPay, $conn);
    updateDebtStatus(
        $creditOrderId,
        $orderId,
        $customerId,
        $newWalletBalance,
        $newAmountPaid,
        $newRemainingBalance,
        $repaymentStatus,
        $conn
    );
    
    logActivity("Repayment processed successfully for order ID: $orderId");
}

function insertLatePaymentFee($orderId, $customerId, $latePaymentFee, $conn) {
    logActivity("Recording late payment fee for order ID: $orderId, Amount: $latePaymentFee");
    
    $currentDate = date('Y-m-d H:i:s');
    $revenueTypeId = 9;
    $status = 'Completed';
    $transactionReference = generateTransactionReference();

    try {
        // Insert into revenue table
        $stmt = $conn->prepare("
            INSERT INTO revenue 
            (order_id, customer_id, total_amount, refunded_amount, retained_amount, transaction_date, status, updated_at, revenue_type_id)
            VALUES (?, ?, ?, 0, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iiddsssi", $orderId, $customerId, $latePaymentFee, $latePaymentFee, $currentDate, $status, $currentDate, $revenueTypeId);
        $stmt->execute();
        
        // Insert into customer_transactions table
        $description = "Late payment fee for Credit Order with Order ID: #$orderId";
        $stmt = $conn->prepare("
            INSERT INTO customer_transactions 
            (transaction_ref, customer_id, amount, date_created, transaction_type, payment_method, description)
            VALUES (?, ?, ?, NOW(), 'Debit', 'Wallet Funding', ?)
        ");
        $stmt->bind_param("sidss", $transactionReference, $customerId, $latePaymentFee, $description);
        $stmt->execute();
        
        // Insert into transactions table
        $stmt = $conn->prepare("
            INSERT INTO transactions 
            (transaction_ref, customer_id, order_id, transaction_type, amount, transaction_date, payment_method, status, created_at, updated_at, revenue_type_id)
            VALUES (?, ?, ?, 'Credit', ?, NOW(), 'Direct Credit for Late Payment on Credit Order with Order ID: #$orderId', ?, NOW(), NOW(), ?)
        ");
        $stmt->bind_param("siisdssi", $transactionReference, $customerId, $orderId, $latePaymentFee, $status, $revenueTypeId);
        $stmt->execute();
        
        // Update credit_orders table
        $stmt = $conn->prepare("
            UPDATE credit_orders
            SET late_payment_fee = ?, date_last_modified = ?
            WHERE order_id = ?
        ");
        $stmt->bind_param("dsi", $latePaymentFee, $currentDate, $orderId);
        $stmt->execute();
        
        logActivity("Late payment fee recorded successfully for order ID: $orderId");
        
    } catch (Exception $e) {
        logActivity("Failed to record late payment fee: " . $e->getMessage());
        throw new DatabaseException("Failed to record late payment fee");
    }
}

function updateWalletBalance($customerId, $newBalance, $conn) {
    logActivity("Updating wallet balance for customer ID: $customerId to $newBalance");
    
    $stmt = $conn->prepare("UPDATE wallets SET balance = ? WHERE customer_id = ?");
    $stmt->bind_param("di", $newBalance, $customerId);
    
    if (!$stmt->execute()) {
        logActivity("Failed to update wallet balance: " . $stmt->error);
        throw new DatabaseException("Wallet balance update failed");
    }
}

function updateDebtStatus($creditOrderId, $orderId, $customerId, $newWalletBalance, $amountPaid, $newRemainingBalance, $repaymentStatus, $conn) {
    logActivity("Updating debt status for credit order ID: $creditOrderId");
    
    $dateLastUpdated = date('Y-m-d H:i:s');
    $revenueTypeId = 7;
    
    try {
        // Update wallet balance
        $stmt = $conn->prepare("UPDATE wallets SET balance = ? WHERE customer_id = ?");
        $stmt->bind_param("di", $newWalletBalance, $customerId);
        $stmt->execute();
        
        // Update credit_orders table
        $stmt = $conn->prepare("
            UPDATE credit_orders
            SET remaining_balance = ?, repayment_status = ?, amount_paid = ?, date_last_modified = ?
            WHERE credit_order_id = ?
        ");
        $stmt->bind_param("dsssi", $newRemainingBalance, $repaymentStatus, $amountPaid, $dateLastUpdated, $creditOrderId);
        $stmt->execute();
        
        // Update revenue table
        $stmt = $conn->prepare("
            UPDATE revenue
            SET total_amount = ?, retained_amount = ?, updated_at = ?, revenue_type_id = ?
            WHERE order_id = ? AND revenue_type_id IN (2, 7)
        ");
        $stmt->bind_param("ddsii", $amountPaid, $amountPaid, $dateLastUpdated, $revenueTypeId, $orderId);
        $stmt->execute();
        
        // Update transactions table
        $stmt = $conn->prepare("
            UPDATE transactions
            SET amount = ?, updated_at = ?, revenue_type_id = ?
            WHERE order_id = ? AND revenue_type_id IN (2, 7)
        ");
        $stmt->bind_param("dsii", $amountPaid, $dateLastUpdated, $revenueTypeId, $orderId);
        $stmt->execute();
        
        logActivity("Debt status updated successfully for credit order ID: $creditOrderId");
        
    } catch (Exception $e) {
        logActivity("Failed to update debt status: " . $e->getMessage());
        throw new DatabaseException("Debt status update failed");
    }
}

function transRepayment($creditOrderId, $orderId, $customerId, $amount, $conn) {
    logActivity("Recording repayment transaction for credit order ID: $creditOrderId, Amount: $amount");
    
    $transactionReference = generateTransactionReference();
    $description = "Repayment of credit order #$creditOrderId for Order #$orderId";
    $paymentDate = date('Y-m-d H:i:s');
    
    try {
        // Insert into customer_transactions
        $stmt = $conn->prepare("
            INSERT INTO customer_transactions 
            (transaction_ref, customer_id, amount, date_created, transaction_type, payment_method, description) 
            VALUES (?, ?, ?, NOW(), 'Debit', 'Direct Debit', ?)
        ");
        $stmt->bind_param("sidsss", $transactionReference, $customerId, $amount, $description);
        $stmt->execute();
        
        // Insert into repayment_history
        $stmt = $conn->prepare("
            INSERT INTO repayment_history 
            (order_id, credit_order_id, customer_id, amount_paid, payment_date)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iiids", $orderId, $creditOrderId, $customerId, $amount, $paymentDate);
        $stmt->execute();
        
        logActivity("Repayment transaction recorded successfully");
        
    } catch (Exception $e) {
        logActivity("Failed to record repayment transaction: " . $e->getMessage());
        throw new DatabaseException("Repayment transaction recording failed");
    }
}

// Custom Exception Classes
class InvalidTokenException extends Exception {}
class InvalidAmountException extends Exception {}
class CardException extends Exception {}
class DailyLimitException extends Exception {}
class InsufficientFundsException extends Exception {}
class DatabaseException extends Exception {}
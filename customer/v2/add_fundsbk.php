<?php
session_start();
include('config.php');
header('Content-Type: application/json');

if (!isset($_SESSION['customer_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}

function generateTransactionReference()
{
    $prefix = 'TRX';
    $uniqueId = uniqid($prefix, true);
    $randomNumber = mt_rand(1000, 9999);
    return strtoupper(str_replace('.', '', $uniqueId . $randomNumber));
}

function getStoredPinHash($customerId, $cardNumber, $conn)
{
    $stmt = $conn->prepare("SELECT card_pin FROM cards WHERE customer_id = ? and card_number = ?");
    $stmt->bind_param("is", $customerId, $cardNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ? $row['card_pin'] : null;
}

function checkWalletExists($customerId, $conn)
{
    $walletId = '';
    $balance = '';
    $stmt = $conn->prepare("SELECT wallet_id, balance FROM wallets WHERE customer_id = ?");
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $stmt->bind_result($walletId, $balance);
    $walletExists = $stmt->fetch();
    $stmt->close();
    return $walletExists ? ['wallet_id' => $walletId, 'balance' => $balance] : null;
}

function getCumulativeDeposit($customerId, $conn)
{
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT SUM(amount) AS total FROM customer_transactions WHERE customer_id = ? AND DATE(date_created) = ? AND transaction_type = 'credit'");
    $stmt->bind_param("is", $customerId, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ? $row['total'] : 0;
}

function getOutstandingDebt($customerId, $conn)
{
    $stmt = $conn->prepare("SELECT * from credit_orders WHERE customer_id = ? AND (repayment_status = 'Pending' OR repayment_status = 'Partially Paid') AND (status = 'Approved')  ORDER BY created_at ASC LIMIT 1");
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $debt = $result->fetch_assoc();
    $stmt->close();
    return $debt;
}

/**
 * Calculate the late payment fee.
 */
function calculateLatePaymentFee($totalCreditAmount)
{
    return 0.3 * $totalCreditAmount;
}
function processRepayment($creditOrderId, $orderId, $customerId, $debtAmount, $amountPaid, $currentWalletBalance, $conn)
{
    if ($currentWalletBalance >= $debtAmount) {
        // Fully pay the debt
        $repaymentStatus = 'Paid';
        $newRemainingBalance = 0.00;
        $newWalletBalance = $currentWalletBalance - $debtAmount;
        $amountToPay = $debtAmount;
    } else {
        // Partially pay the debt
        $repaymentStatus = 'Partially Paid';
        $newRemainingBalance = $debtAmount - $currentWalletBalance;
        $newWalletBalance = 0.00;
        $amountToPay = $currentWalletBalance;
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
}

/**
 * Insert the late payment fee as a separate record.
 */
function insertLatePaymentFee($orderId, $customerId, $latePaymentFee, $conn)
{
    $currentDate = date('Y-m-d H:i:s');

    // Insert into revenue table
    $revenue_type_id = 9;
    $refunded_amount = 0;
    $status = 'Completed';
    $insertRevenueStmt = $conn->prepare("
        INSERT INTO revenue (order_id, customer_id, total_amount, refunded_amount, retained_amount, transaction_date, status, updated_at, revenue_type_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$insertRevenueStmt) {
        throw new Exception("Failed to prepare revenue statement: " . $conn->error);
    }
    $insertRevenueStmt->bind_param("iidddsssi", $orderId, $customerId, $latePaymentFee, $refunded_amount, $latePaymentFee, $currentDate, $status, $currentDate, $revenue_type_id);
    if (!$insertRevenueStmt->execute()) {
        throw new Exception("Failed to insert into revenue table: " . $conn->error);
    }
    $insertRevenueStmt->close();

    // Insert into customer_transactions table
    $transactionReference = generateTransactionReference();
    $transactionType = "Debit";
    $paymentMethod = "Wallet Funding";
    $description = "Late payment fee for Credit Order with Order ID: #$orderId";

    $insertTransactionStmt = $conn->prepare("
        INSERT INTO customer_transactions (transaction_ref, customer_id, amount, date_created, transaction_type, payment_method, description)
        VALUES (?, ?, ?, NOW(), ?, ?, ?)
    ");
    if (!$insertTransactionStmt) {
        throw new Exception("Failed to prepare transaction statement: " . $conn->error);
    }
    $insertTransactionStmt->bind_param("sidsss", $transactionReference, $customerId, $latePaymentFee, $transactionType, $paymentMethod, $description);
    if (!$insertTransactionStmt->execute()) {
        throw new Exception("Failed to insert into customer_transactions table: " . $conn->error);
    }
    $insertTransactionStmt->close();

    // Insert into transactions table
    $transactionType2 = "Credit";
    $paymentMethod2 = "Direct Credit for Late Payment on Credit Order with Order ID: #$orderId";

    $insertTransactionStmt = $conn->prepare("
        INSERT INTO transactions (transaction_ref, customer_id, order_id, transaction_type, amount, transaction_date, payment_method, status, created_at, updated_at, revenue_type_id)
        VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, NOW(), NOW(), ?)
    ");
    if (!$insertTransactionStmt) {
        throw new Exception("Failed to prepare transaction statement: " . $conn->error);
    }
    $insertTransactionStmt->bind_param("siisdssi", $transactionReference, $customerId, $orderId, $transactionType2, $latePaymentFee,$paymentMethod2, $status, $revenue_type_id);
    if (!$insertTransactionStmt->execute()) {
        throw new Exception("Failed to insert into transactions table: " . $conn->error);
    }
    $insertTransactionStmt->close();
}
function updateWalletBalance($customerId, $newBalance, $conn) {
    $stmt = $conn->prepare("UPDATE wallets SET balance = ? WHERE customer_id = ?");
    $stmt->bind_param("di", $newBalance, $customerId);
    $stmt->execute();
}

$token = $_POST['token'] ?? '';
$pin = $_POST['pin'] ?? '';
$amount = $_POST['amount'] ?? '';
$cardNumber = $_POST['card_number'] ?? '';
$customerId = $_SESSION['customer_id'] ?? '';

$encrypted_pin = md5($pin);
$encrypted_cardNumber = encrypt($cardNumber, $encryption_key, $encryption_iv);

if (!isset($_SESSION['token']) || $_SESSION['token']['value'] !== $token || time() > $_SESSION['token']['expires_at']) {
    echo json_encode(['success' => false, 'message' => 'Invalid or expired token']);
    exit;
}

if (!is_numeric($amount) || $amount <= 0 || $amount > 100000) {
    echo json_encode(['success' => false, 'message' => 'Invalid amount or exceeds maximum limit']);
    exit;
}

//Ensure Daily Cumulative Deposit does not exceed 300k
$cumulativeDeposit = getCumulativeDeposit($customerId, $conn);
if (($cumulativeDeposit + $amount) > 300000) {
    echo json_encode(['success' => false, 'message' => 'This transaction will make you exceed the cumulative deposit limit today']);
    exit;
}

$storedPinHash = getStoredPinHash($customerId, $encrypted_cardNumber, $conn);
if ($encrypted_pin !== $storedPinHash) {
    echo json_encode(['success' => false, 'message' => 'Error occurred. Invalid Card Details']);
    exit;
}

$description = "Wallet Funding";
$paymentMethod = 'Card';

$conn->begin_transaction();

try {
    $walletData = checkWalletExists($customerId, $conn);

    if ($walletData) {
        // Wallet exists, update the balance
        $stmt = $conn->prepare("UPDATE wallets SET balance = balance + ?, date_last_updated = NOW() WHERE wallet_id = ?");
        $stmt->bind_param("di", $amount, $walletData['wallet_id']);
        $stmt->execute();
        $stmt->close();
    } else {
        // Wallet does not exist, create a new wallet
        $stmt = $conn->prepare("INSERT INTO wallets (customer_id, balance, date_last_updated) VALUES (?, ?, NOW())");
        $stmt->bind_param("id", $customerId, $amount);
        $stmt->execute();
        $walletId = $stmt->insert_id;
        $stmt->close();
    }

    $transactionReference = generateTransactionReference();
    $stmt = $conn->prepare("INSERT INTO customer_transactions (transaction_ref, customer_id, amount, date_created, transaction_type, payment_method, description) VALUES (?, ?, ?, NOW(), 'credit', ?, ?)");
    $stmt->bind_param("sidss", $transactionReference, $customerId, $amount, $paymentMethod, $description);
    $stmt->execute();
    $stmt->close();

    //check if customer has an existing debt yet to be repaid
    $outstandingDebt = getOutstandingDebt($customerId, $conn);
    if ($outstandingDebt) {
        $credit_order_id = $outstandingDebt['credit_order_id'];
        $order_id = $outstandingDebt['order_id'];
        $total_credit_amount = $outstandingDebt['total_credit_amount'];
        $amount_paid = $outstandingDebt['amount_paid'];
        $debtAmount = $outstandingDebt['remaining_balance'];
        $due_date = $outstandingDebt['due_date'];
        $current_date = date('Y-m-d H:i:s');
        $balance = $walletData['balance'];
        $currentWalletBalance = $amount + $balance;

        // Apply late payment fee if overdue
        if ($due_date < $current_date) {
            $latePaymentFee = calculateLatePaymentFee($total_credit_amount);
            //$debtAmount += $latePaymentFee;
            // Validate if user has enough balance
            if ($currentWalletBalance < ($debtAmount + $latePaymentFee)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Insufficient funds. Your credit is overdue. Total amount due: ' . number_format($debtAmount, 2) .
                        ' and a late payment fee of: ' . number_format($latePaymentFee, 2)
                ]);
                exit; // Stop further execution

            }
            // Insert late payment fee into revenue and transactions tables
            insertLatePaymentFee($order_id, $customerId, $latePaymentFee, $conn);

            // Deduct late payment fee from wallet balance
            $currentWalletBalance -= $latePaymentFee;
            updateWalletBalance($customerId, $currentWalletBalance, $conn);

        }
        // Process repayment
        processRepayment(
            $credit_order_id,
            $order_id,
            $customerId,
            $debtAmount,
            $amount_paid,
            $currentWalletBalance,
            $conn
        );
    }
    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Transaction successful', 'card_number' => $cardNumber]);
    $_SESSION['token'] = [
        'value' => '',
        'expires_at' => time() // Token expires in 60 seconds
    ];
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Transaction failed. Please try again.', 'error' => $e->getMessage()]);
}

$conn->close();


function updateDebtStatus($creditOrderId, $orderID, $customerID, $new_wallet_balance, $amountPaid, $newRemainingBalance, $repaymentStatus, $conn)
{
    // Update the wallet balance in the database
    $debit_wallet_stmt = $conn->prepare("UPDATE wallets SET balance = ? WHERE customer_id = ?");
    $debit_wallet_stmt->bind_param("di", $new_wallet_balance, $customerID);
    $debit_wallet_stmt->execute();

    // Calculate the new remaining balance and repayment status

    // Prepare the update query for the credit_orders table
    $update_stmt = $conn->prepare("
   UPDATE credit_orders
   SET remaining_balance = ?, repayment_status = ?, amount_paid = ?, date_last_modified = ?
   WHERE credit_order_id = ?
");

    // Get the current timestamp for the update
    $date_last_updated = date('Y-m-d H:i:s');

    // Bind the parameters to the query
    $update_stmt->bind_param("dsssi", $newRemainingBalance, $repaymentStatus, $amountPaid, $date_last_updated, $creditOrderId);

    // Execute the update statement
    $update_stmt->execute();


    //Update Revenue Table
    $update_stmt = $conn->prepare("
       UPDATE revenue
       SET total_amount = ?, retained_amount = ?,  updated_at = ?, revenue_type_id = ?
       WHERE order_id = ? AND revenue_type_id IN (2, 7)
   ");

    // Get the current timestamp
    $updated_at = date('Y-m-d H:i:s');
    $revenue_type_id = 7;

    // Bind the parameters
    $update_stmt->bind_param("ddsii", $amountPaid, $amountPaid, $updated_at, $revenue_type_id, $orderID);

    // Execute the statement
    $update_stmt->execute();

    //Update transaction Table
    $update_stmt = $conn->prepare("
    UPDATE transactions
    SET amount = ?, updated_at = ?, revenue_type_id = ?
    WHERE order_id = ? AND revenue_type_id IN (2, 7)
");

    // Get the current timestamp
    $updated_at = date('Y-m-d H:i:s');
    $revenue_type_id = 7;

    // Bind the parameters
    $update_stmt->bind_param("dsii", $amountPaid, $updated_at, $revenue_type_id, $orderID);

    // Execute the statement
    $update_stmt->execute();
    // Execute the statement
    $update_stmt->execute();
    // Update the wallet balance in the database
    $debit_wallet_stmt = $conn->prepare("UPDATE wallets SET balance = ? WHERE customer_id = ?");
    $debit_wallet_stmt->bind_param("di", $new_wallet_balance, $customerID);
    $debit_wallet_stmt->execute();

    // Return success response
    return json_encode([
        'success' => true,
        'message' => 'Repayment processed successfully.',
        'remaining_balance' => $newRemainingBalance,
        'repayment_status' => $repaymentStatus
    ]);
}

function transRepayment($creditOrderId, $orderID, $customerID, $amount, $conn)
{
    // Insert transaction into customer_transactions table
    $transactionReference = generateTransactionReference();
    $transaction_stmt = $conn->prepare("
       INSERT INTO customer_transactions (transaction_ref, customer_id, amount, date_created, transaction_type, payment_method, description) VALUES (?, ?, ?, NOW(), ?, ?, ?)");
    ;
    $transaction_type = "Debit";
    $payment_method = 'Diredct Debit';
    $description = "Repayment of credit order #$creditOrderId for Order #$orderID";
    $updated_at = date('Y-m-d H:i:s');

    $transaction_stmt->bind_param("sidsss", $transactionReference, $customerID, $amount, $transaction_type, $payment_method, $description);
    $transaction_stmt->execute();

    // Insert into repayment_history table
    $insert_stmt = $conn->prepare("
   INSERT INTO repayment_history (order_id, credit_order_id, customer_id, amount_paid, payment_date)
   VALUES (?, ?, ?, ?, ?)
");
    $insert_stmt->bind_param("iiids", $orderID, $creditOrderId, $customerID, $amount, $updated_at);
    $insert_stmt->execute();
}

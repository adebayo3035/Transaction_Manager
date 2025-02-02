<?php
header('Content-Type: application/json');

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection file
include('config.php');
include 'activity_logger.php';
session_start();

// Check if customer_id is set in the session
if (!isset($_SESSION['customer_id'])) {
    logActivity('Unauthorized access. Customer ID not found.');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Customer ID not found.']);
    exit;
}

$customer_id = $_SESSION['customer_id'];
logActivity("Customer ID retrieved from session: $customer_id");

// Decode the incoming JSON request body
$data = json_decode(file_get_contents('php://input'), true);
logActivity("Incoming request data: " . json_encode($data));

if (isset($data['order_id']) && isset($data['status'])) {
    $order_id = $data['order_id'];
    $status = $data['status'];
    logActivity("Order ID: $order_id, Status: $status");

    // Validate the status value
    $valid_statuses = ['Cancelled'];
    if (!in_array($status, $valid_statuses)) {
        logActivity("Invalid status value: $status");
        echo json_encode(['success' => false, 'message' => 'Invalid status value.']);
        exit;
    }

    // Generate transaction reference
    function generateTransactionReference() {
        $prefix = 'TRX';
        $uniqueId = uniqid($prefix, true);
        $randomNumber = mt_rand(1000, 9999);
        $transactionRef = strtoupper(str_replace('.', '', $uniqueId . $randomNumber));
        logActivity("Generated transaction reference: $transactionRef");
        return $transactionRef;
    }

    $transactionReference = generateTransactionReference();

    // Start a transaction
    $conn->begin_transaction();
    logActivity("Transaction started.");

    try {
        if ($status === 'Cancelled') {
            // Fetch order details
            $stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ?");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $orderData = $result->fetch_assoc();
            $stmt->close();

            if (!$orderData) {
                throw new Exception("Order not found.");
            }

            $totalAmount = $orderData['total_amount'];
            $customerId = $orderData['customer_id'];
            $isCredit = $orderData['is_credit'];
            $discount = $orderData['discount'];
            $orderDate = $orderData['order_date'];
            logActivity("Order details fetched: " . json_encode($orderData));

            if (!$isCredit) {
                // Process cancellation and refund
                $totalAmount += $discount;
                $cancellation_fee = 0.2 * $totalAmount;
                $balance = $totalAmount - $cancellation_fee;
                logActivity("Calculated cancellation fee: $cancellation_fee, Balance: $balance");

                // Update wallet balance
                $stmt = $conn->prepare("UPDATE wallets SET balance = balance + ? WHERE customer_id = ?");
                $stmt->bind_param("di", $balance, $customerId);
                $stmt->execute();
                $stmt->close();
                logActivity("Wallet balance updated for customer ID: $customerId");

                // Record cancellation fee transaction
                $description = "Cancellation fee for Order ID: $order_id";
                $paymentMethod = "Order Cancellation Fee";
                $stmt = $conn->prepare(
                    "INSERT INTO customer_transactions 
                    (transaction_ref, customer_id, amount, date_created, transaction_type, payment_method, description) 
                    VALUES (?, ?, ?, NOW(), 'debit', ?, ?)"
                );
                $stmt->bind_param("sidss", $transactionReference, $customerId, $cancellation_fee, $paymentMethod, $description);
                $stmt->execute();
                $stmt->close();
                logActivity("Cancellation fee transaction recorded: $transactionReference");

                // Record refund transaction
                $description = "Refund for Cancelled Order: $order_id";
                $paymentMethod = "Transaction Refund";
                $stmt = $conn->prepare(
                    "INSERT INTO customer_transactions 
                    (transaction_ref, customer_id, amount, date_created, transaction_type, payment_method, description) 
                    VALUES (?, ?, ?, NOW(), 'credit', ?, ?)"
                );
                $stmt->bind_param("sidss", $transactionReference, $customerId, $balance, $paymentMethod, $description);
                $stmt->execute();
                $stmt->close();
                logActivity("Refund transaction recorded: $transactionReference");

                // Insert into transactions table
                $transaction_type = 'Credit';
                $status1 = 'Completed';
                $paymentMethod = 'Direct Debit Cancelled Order';
                $revenue_type = 1;
                $stmt = $conn->prepare(
                    "INSERT INTO transactions 
                    (transaction_ref, customer_id, order_id, transaction_type, amount, transaction_date, payment_method, status, created_at, revenue_type_id) 
                    VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, NOW(), ?)"
                );
                $stmt->bind_param("siisdssi", $transactionReference, $customerId, $order_id, $transaction_type, $cancellation_fee, $paymentMethod, $status1, $revenue_type);
                $stmt->execute();
                $stmt->close();
                logActivity("Transaction recorded in transactions table: $transactionReference");

                // Insert into revenue table
                $retained_amount = $cancellation_fee;
                $revenue_type_id = 1; // Assuming 1 represents cancellation fees
                $stmt = $conn->prepare(
                    "INSERT INTO revenue 
                    (order_id, customer_id, total_amount, refunded_amount, retained_amount, transaction_date, status, updated_at, revenue_type_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)"
                );
                $stmt->bind_param("iidddssi", $order_id, $customerId, $totalAmount, $balance, $retained_amount, $orderDate, $status, $revenue_type_id);
                $stmt->execute();
                $stmt->close();
                logActivity("Revenue recorded for order ID: $order_id");
            } else {
                logActivity("Order placed on credit cannot be cancelled.");
                echo json_encode(['success' => false, 'message' => 'You cannot cancel an order placed on credit.']);
                exit;
            }

            // Update food stock
            $stmt = $conn->prepare("SELECT food_id, quantity FROM order_details WHERE order_id = ?");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $foodId = $row['food_id'];
                $quantity = $row['quantity'];
                $updateFoodStmt = $conn->prepare("UPDATE food SET available_quantity = available_quantity + ? WHERE food_id = ?");
                $updateFoodStmt->bind_param("ii", $quantity, $foodId);
                $updateFoodStmt->execute();
                $updateFoodStmt->close();
                logActivity("Food stock updated for food ID: $foodId, Quantity: $quantity");
            }
            $stmt->close();

            // Update delivery status
            $cancellationReason = "Customer Cancelled Order before Delivery";
            $stmt = $conn->prepare("UPDATE orders SET delivery_status = ?, cancellation_reason = ?, updated_at = NOW(), approved_by = ? WHERE order_id = ?");
            $stmt->bind_param("ssii", $status, $cancellationReason, $customer_id, $order_id);
            $stmt->execute();
            $stmt->close();
            logActivity("Delivery status updated for order ID: $order_id");

            // Update the status in the orders, revenue, and order_details tables
            $updateStatusQueries = [
                "UPDATE orders SET status = ?, updated_at = NOW() WHERE order_id = ?",
                "UPDATE order_details SET status = ?, updated_at = NOW() WHERE order_id = ?"
            ];

            // Prepare statements
            foreach ($updateStatusQueries as $query) {
                $stmt = $conn->prepare($query);

                if (strpos($query, 'total_amount') !== false) {
                    // For the second query (revenue table), bind totalAmount
                    $stmt->bind_param("sid", $status, $order_id, $totalAmount);
                } else {
                    // For the other queries, bind status and order_id only
                    $stmt->bind_param("si", $status, $order_id);
                }

                // Execute the statement
                $stmt->execute();
                $stmt->close();
                logActivity("Status updated in database for order ID: $order_id");
            }
        }

        // Commit transaction
        $conn->commit();
        logActivity("Transaction committed successfully.");
        echo json_encode(['success' => true, 'message' => 'Your order has been successfully cancelled.']);
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        logActivity("Transaction failed: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()]);
    }
} else {
    logActivity("Invalid request data.");
    echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
}

$conn->close();
logActivity("Database connection closed.");
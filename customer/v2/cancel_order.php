<?php
header('Content-Type: application/json');
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


// Include database connection file
include('config.php');
session_start();
$customer_id = $_SESSION['customer_id'];

// Decode the incoming JSON request body
$data = json_decode(json: file_get_contents(filename: 'php://input'), associative: true);

if (isset($data['order_id']) && isset($data['status'])) {
    $order_id = $data['order_id'];
    $status = $data['status'];

    // Validate the status value
    $valid_statuses = ['Cancelled'];
    if (!in_array(needle: $status, haystack: $valid_statuses)) {
        echo json_encode(value: ['success' => false, 'message' => 'Invalid status value.']);
        exit;
    }

    // Start a transaction
    $conn->begin_transaction();

    try {
         if ($status === 'Cancelled') {
            // Refund the customer
            $stmt = $conn->prepare("SELECT total_amount, customer_id FROM orders WHERE order_id = ?");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $stmt->bind_result($totalAmount, $customerId);
            $stmt->fetch();
            $stmt->close();

            // Deduct 20% cancellation fee and add balance to customer's wallet
            $cancellation_fee = (0.2 * $totalAmount);
            $balance = $totalAmount - $cancellation_fee;
            $stmt = $conn->prepare("UPDATE wallets SET balance = balance + ? WHERE customer_id = ?");
            $stmt->bind_param("di", $balance, $customerId);
            $stmt->execute();
            $stmt->close();

            // Update revenue table
            $refunded_amount = $totalAmount - $cancellation_fee;
            $stmt = $conn->prepare("UPDATE revenue SET refunded_amount = ?, retained_amount = ? WHERE order_id = ?");
            $stmt->bind_param("ddi", $refunded_amount, $cancellation_fee, $order_id);
            $stmt->execute();
            $stmt->close();

            // Update transactions table to Failed
            $stmt = $conn->prepare("SELECT transaction_ref FROM transactions WHERE order_id = ?");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $stmt->bind_result( $transaction_ref);
            $stmt->fetch();
            $stmt->close();

            $transaction_status = 'Failed';
            $stmt = $conn->prepare("UPDATE transactions SET status = ? WHERE order_id = ? AND transaction_ref = ?");
            $stmt->bind_param("sis", $transaction_status,$order_id, $transaction_ref);
            $stmt->execute();
            $stmt->close();

            function generateTransactionReference() {
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
            $status1 = 'Completed';
            $paymentMethod = 'Direct Credit';
            $revenue_type = 1;
            $stmt = $conn->prepare("INSERT INTO transactions (transaction_ref, customer_id, order_id, transaction_type, amount, transaction_date, payment_method, status, created_at, revenue_type_id) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, NOW(), ?)");
            $stmt->bind_param("siisdssi", $transactionReference, $customerId, $order_id, $transaction_type, $cancellation_fee, $paymentMethod, $status1, $revenue_type);
            $stmt->execute();
            $stmt->close();

            $transactionReference = generateTransactionReference();
            $transaction_type = 'Debit';
            $status1 = 'Completed';
            $paymentMethod = 'Direct Debit';
            $revenue_type = 5;
            $stmt = $conn->prepare("INSERT INTO transactions (transaction_ref, customer_id, order_id, transaction_type, amount, transaction_date, payment_method, status, created_at, revenue_type_id) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, NOW(), ?)");
            $stmt->bind_param("siisdssi", $transactionReference, $customerId, $order_id, $transaction_type, $refunded_amount, $paymentMethod, $status1, $revenue_type);
            $stmt->execute();
            $stmt->close();

            // Insert refund and cancellation fee record into customer_transactions
            $description = "Refund for Cancelled Order: " . $order_id;
            $paymentMethod = "Transaction Refund from Order Cancellation";
            $stmt = $conn->prepare("INSERT INTO customer_transactions (customer_id, amount, date_created, transaction_type, payment_method, description) VALUES (?, ?, NOW(), 'credit', ?, ?)");
            $stmt->bind_param("idss", $customerId, $balance, $paymentMethod, $description);
            $stmt->execute();
            $stmt->close();

            $description = "Cancellation Fee for Order: " . $order_id;
            $paymentMethod = "Direct Debit";
            $stmt = $conn->prepare("INSERT INTO customer_transactions (customer_id, amount, date_created, transaction_type, payment_method, description) VALUES (?, ?, NOW(), 'debit', ?, ?)");
            $stmt->bind_param("idss", $customerId, $cancellation_fee, $paymentMethod, $description);
            $stmt->execute();
            $stmt->close();

            // Update food stock quantities in the food table
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
            }

            $stmt->close();

            // Update the delivery_status to 'Cancelled'
            $cancellationReason = 'Customer Cancelled Order before Delivery';
            $updateDeliveryStatusQuery = "UPDATE orders SET delivery_status = ?, cancellation_reason = ?, updated_at = NOW(), approved_by = ? WHERE order_id = ?";
            $stmt = $conn->prepare($updateDeliveryStatusQuery);
            $stmt->bind_param("ssii", $status, $cancellationReason, $customer_id, $order_id);
            $stmt->execute();
            $stmt->close();
        }

        // Update the status in the orders, revenue, and order_details tables
        $updateStatusQueries = [
            "UPDATE orders SET status = ?, updated_at = NOW() WHERE order_id = ?",
            "UPDATE revenue SET status = ?, updated_at = NOW() WHERE order_id = ? AND total_amount = ?",
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
        }
        

        // Commit the transaction
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Your Order has been successfully Cancelled.']);

    } catch (Exception $e) {
        // Rollback the transaction if an error occurred
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
}

$conn->close();


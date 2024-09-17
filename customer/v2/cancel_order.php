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

            // Insert revenue data into revenue table
            // revenue type id for Order Inflow
            $revenue_type = 1; 
            $mystatus = "Order Cancellation Fee";
            $stmt = $conn->prepare("INSERT INTO revenue (order_id, customer_id, total_amount, transaction_date, status, revenue_type_id) VALUES (?, ?, ?, NOW(), ?, ?)");
            $stmt->bind_param("iidsi", $order_id, $customer_id, $cancellation_fee, $mystatus, $revenue_type);
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
            $cancellationReason = 'Customer Cancelled Order';
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
        echo json_encode(['success' => true, 'message' => 'Your Order has been successfully Canceled.']);

    } catch (Exception $e) {
        // Rollback the transaction if an error occurred
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
}

$conn->close();


<?php
header('Content-Type: application/json');
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


// Include database connection file
include('config.php');
include('restriction_checker.php');
$approvalID = $_SESSION['unique_id'];

if (isset($data['order_id']) && isset($data['status'])) {
    $order_id = $data['order_id'];
    $status = $data['status'];

    // Validate the status value
    $valid_statuses = ['Approved', 'Declined'];
    if (!in_array($status, $valid_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status value.']);
        exit;
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


    // Start a transaction
    $conn->begin_transaction();

    try {
        if ($status === 'Approved') {
            // Find an available driver who is not restricted
            $findDriverQuery = "SELECT id FROM driver WHERE status = 'Available' and restriction = 0 ORDER BY RAND() LIMIT 1";
            $result = $conn->query($findDriverQuery);

            if ($result->num_rows > 0) {
                $driver = $result->fetch_assoc();
                $driver_id = $driver['id'];

                // Assign the driver to the order and update the order and driver statuses
                $assignDriverQuery = "UPDATE orders SET driver_id = ?, delivery_status = 'Assigned', status = ?, updated_at = NOW(), approved_by = ? WHERE order_id = ?";
                $stmt = $conn->prepare($assignDriverQuery);
                $stmt->bind_param('isii', $driver_id, $status, $approvalID, $order_id);
                $stmt->execute();

                $updateDriverStatusQuery = "UPDATE driver SET status = 'Not Available' WHERE id = ?";
                $stmt = $conn->prepare($updateDriverStatusQuery);
                $stmt->bind_param('i', $driver_id);
                $stmt->execute();
                $stmt->close();

                // Check if the order is a credit order
                $checkCreditQuery = "SELECT is_credit, total_amount, customer_id FROM orders WHERE order_id = ?";
                $stmt = $conn->prepare($checkCreditQuery);
                $stmt->bind_param("i", $order_id);
                $stmt->execute();
                $stmt->bind_result($isCredit, $totalAmount, $customerId);
                $stmt->fetch();
                $stmt->close();

                if ($isCredit) {
                    $updateCreditOrderStatusQuery = "UPDATE credit_orders SET status = 'Approved' WHERE order_id = ?";
                    $stmt = $conn->prepare($updateCreditOrderStatusQuery);
                    $stmt->bind_param('i', $order_id);
                    $stmt->execute();
                    $stmt->close();
                }

            } else {
                throw new Exception('No available drivers at the moment.');
            }
        } else if ($status === 'Declined') {
            // Refund the customer
            $stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ?");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();

            // Fetch the result as an associative array
            $result = $stmt->get_result();
            $orderData = $result->fetch_assoc();
            $stmt->close();

            // Assign values to variables
            $totalAmount = $orderData['total_amount'];
            $customerId = $orderData['customer_id'];
            $isCredit = $orderData['is_credit'];
            $orderDate = $orderData['order_date']; // Example of additional columns
            $deliveryStatus = $orderData['delivery_status']; // Example of additional columns
            $isCredit = $orderData['is_credit'];

            // Use the variables as needed
            // if order is not a credit order then update the customer's wallet and refund
            $transactionReference = generateTransactionReference();
            if ($isCredit == false) {
                // Update wallet balance
                $stmt = $conn->prepare("UPDATE wallets SET balance = balance + ? WHERE customer_id = ?");
                $stmt->bind_param("di", $totalAmount, $customerId);
                $stmt->execute();
                $stmt->close();

                // Insert refund record into customer_transactions

                $description = "Declined Food Order Transaction Refund for Order ID: " . $order_id;
                $paymentMethod = "Transaction Refund";
                $stmt = $conn->prepare("INSERT INTO customer_transactions (transaction_ref, customer_id, amount, date_created, transaction_type, payment_method, description) VALUES (?, ?, ?, NOW(), 'credit', ?, ?)");
                $stmt->bind_param("sidss", $transactionReference, $customerId, $totalAmount, $paymentMethod, $description);
                $stmt->execute();
                $stmt->close();
            } else if ($isCredit == true) {
                $description = "Declined Food Order on Credit for Order ID: " . $order_id;
                $paymentMethod = "Not Applicable";
                $stmt = $conn->prepare("INSERT INTO customer_transactions (transaction_ref, customer_id, amount, date_created, transaction_type, payment_method, description) VALUES (?, ?, ?, NOW(), 'Others', ?, ?)");
                $stmt->bind_param("sidss", $transactionReference, $customerId, $totalAmount, $paymentMethod, $description);
                $stmt->execute();
                $stmt->close();

                //Update Credit Orders Status and repayment_status to Cancelled when Admin Cancels an Order
                $updateCreditOrderStatusQuery = "UPDATE credit_orders SET status = 'Cancelled', repayment_status = 'Void' WHERE order_id = ?";
                $stmt = $conn->prepare($updateCreditOrderStatusQuery);
                $stmt->bind_param('i', $order_id);
                $stmt->execute();
                $stmt->close();
            }
            $transaction_type = 'Others';
            $status1 = 'Declined';
            $paymentMethod = 'Declined Order';
            $revenue_type = 6;
            $stmt = $conn->prepare("INSERT INTO transactions (transaction_ref, customer_id, order_id, transaction_type, amount, transaction_date, payment_method, status, created_at, revenue_type_id) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, NOW(), ?)");
            $stmt->bind_param("siisdssi", $transactionReference, $customerId, $order_id, $transaction_type, $totalAmount, $paymentMethod, $status1, $revenue_type);
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
            $updateDeliveryStatusQuery = "UPDATE orders SET delivery_status = 'Declined', updated_at = NOW(), approved_by = ? WHERE order_id = ?";
            $stmt = $conn->prepare($updateDeliveryStatusQuery);
            $stmt->bind_param("ii", $approvalID, $order_id);
            $stmt->execute();
            $stmt->close();
        }

        // Update the status in the orders, revenue, and order_details tables
        $updateStatusQueries = [
            "UPDATE orders SET status = ?, updated_at = NOW() WHERE order_id = ?",
            "UPDATE order_details SET status = ?, updated_at = NOW() WHERE order_id = ?"
        ];

        foreach ($updateStatusQueries as $query) {
            $stmt = $conn->prepare($query);
            $stmt->bind_param("si", $status, $order_id);
            $stmt->execute();
            $stmt->close();
        }

        // Commit the transaction
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Order status updated successfully.']);

    } catch (Exception $e) {
        // Rollback the transaction if an error occurred
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
}

$conn->close();


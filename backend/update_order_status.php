<?php
header('Content-Type: application/json');
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection file
include('config.php');
include('restriction_checker.php');

if (isset($data['order_id']) && isset($data['status'])) {
    $order_id = $data['order_id'];
    $status = $data['status'];

    // Validate the status value
    $valid_statuses = ['Approved', 'Declined'];
    if (!in_array($status, $valid_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status value.']);
        exit;
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
                $assignDriverQuery  = "UPDATE orders SET driver_id = ?, delivery_status = 'Assigned', status = ?, updated_at = NOW() WHERE order_id = ?";
                $updateDriverStatusQuery = "UPDATE driver SET status = 'Not Available' WHERE id = ?";

                $stmt = $conn->prepare($assignDriverQuery);
                $stmt->bind_param('isi', $driver_id, $status, $order_id);
                $stmt->execute();
                
                $stmt = $conn->prepare($updateDriverStatusQuery);
                $stmt->bind_param('i', $driver_id);
                $stmt->execute();
                $stmt->close();

                // Optionally, send a notification to the driver
                // sendDriverNotification($driver_id, $order_id);
            } else {
                throw new Exception('No available drivers at the moment.');
            }
        } else if ($status === 'Declined') {
            // Refund the customer
            $stmt = $conn->prepare("SELECT total_amount, customer_id FROM orders WHERE order_id = ?");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $stmt->bind_result($totalAmount, $customerId);
            $stmt->fetch();
            $stmt->close();

            // Update wallet balance
            $stmt = $conn->prepare("UPDATE wallets SET balance = balance + ? WHERE customer_id = ?");
            $stmt->bind_param("di", $totalAmount, $customerId);
            $stmt->execute();
            $stmt->close();

            // Insert refund record into customer_transactions
            $description = "Declined Food Order Refund for Order ID: " . $order_id;
            $paymentMethod = "Transaction Refund";
            $stmt = $conn->prepare("INSERT INTO customer_transactions (customer_id, amount, date_created, transaction_type, payment_method, description) VALUES (?, ?, NOW(), 'credit', ?, ?)");
            $stmt->bind_param("idss", $customerId, $totalAmount, $paymentMethod, $description);
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
            $updateDeliveryStatusQuery = "UPDATE orders SET delivery_status = 'Cancelled', updated_at = NOW() WHERE order_id = ?";
            $stmt = $conn->prepare($updateDeliveryStatusQuery);
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $stmt->close();
        }

        // Update the status in the orders, revenue, and order_details tables
        $updateStatusQueries = [
            "UPDATE orders SET status = ?, updated_at = NOW() WHERE order_id = ?",
            "UPDATE revenue SET status = ?, updated_at = NOW() WHERE order_id = ?",
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


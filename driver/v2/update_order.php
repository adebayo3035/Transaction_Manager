<?php
// Include database connection
include('config.php');
session_start();

// Get the JSON data from the request body
$data = json_decode(file_get_contents("php://input"), true);

// Validate required inputs
if (!isset($data['id'], $data['currentStatus'], $data['orderStatus'])) {
    echo json_encode(["success" => false, "message" => "Invalid order - Order details missing."]);
    exit();
}

$orderId = $data['id'];
$current_status = $data['currentStatus'];
$orderStatus = $data['orderStatus'];
$driver_id = $_SESSION['driver_id'];

// Invalid status transitions check
$invalidTransitions = [
    ["from" => "Assigned", "to" => "Delivered"],
    ["from" => "Assigned", "to" => "Cancelled on Delivery"],
    ["from" => "In Transit", "to" => "In Transit"]
];

foreach ($invalidTransitions as $transition) {
    if ($current_status === $transition['from'] && $orderStatus === $transition['to']) {
        echo json_encode(["success" => false, "message" => "Invalid Order Status - From {$transition['from']} to {$transition['to']}"]);
        exit();
    }
}

// Validate the delivery pin if status is Delivered or Cancelled
if (($orderStatus === "Delivered" || $orderStatus === "Cancelled on Delivery") && isset($data['deliveryPin'])) {
    $deliveryPin = $data['deliveryPin'];
    $sql = "SELECT delivery_pin FROM orders WHERE order_id = ? AND driver_id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ii", $orderId, $driver_id);
        $stmt->execute();
        $stmt->bind_result($storedPin);
        $stmt->fetch();
        $stmt->close();

        if ($storedPin !== $deliveryPin) {
            echo json_encode(["success" => false, "message" => "Invalid delivery pin. Please try again."]);
            exit();
        }
    } else {
        echo json_encode(["success" => false, "message" => "Prepare failed: " . $conn->error]);
        exit();
    }
}

// Start a transaction
$conn->begin_transaction();

try {
    // Prepare to update the order status in the orders table
    $cancellationReason = null;
    $updateSql = "";

    if ($orderStatus === "Cancelled on Delivery") {
        if (!isset($data['cancelReason']) || empty($data['cancelReason'])) {
            throw new Exception("Cancellation reason is required for a Cancelled order.");
        }
        $cancellationReason = $data['cancelReason'];
        $updateSql = "UPDATE orders SET cancellation_reason = ?, delivery_status = 'Cancelled on Delivery' WHERE order_id = ? AND driver_id = ?";
    } elseif ($orderStatus === "Delivered") {
        $updateSql = "UPDATE orders SET delivery_status = 'Delivered' WHERE order_id = ? AND driver_id = ?";
    } elseif ($orderStatus === "In Transit") {
        $updateSql = "UPDATE orders SET delivery_status = 'In Transit' WHERE order_id = ? AND driver_id = ?";
    }

    // Execute the update for the order status
    if ($stmt = $conn->prepare($updateSql)) {
        if ($cancellationReason) {
            $stmt->bind_param("sii", $cancellationReason, $orderId, $driver_id);
        } else {
            $stmt->bind_param("ii", $orderId, $driver_id);
        }

        if (!$stmt->execute() || $stmt->affected_rows === 0) {
            throw new Exception("Failed to update the order status.");
        }
        $stmt->close();
    }

    // Update driver status to 'Available' if Delivered or Cancelled
    if ($orderStatus === "Delivered" || $orderStatus === "Cancelled on Delivery") {
        $sql = "UPDATE driver SET status = 'Available' WHERE id = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("i", $driver_id);
            $stmt->execute();
            $stmt->close();
        } else {
            throw new Exception("Failed to update driver status.");
        }
    }

    // Select transaction reference for the order
    $stmt = $conn->prepare("SELECT transaction_ref FROM transactions WHERE order_id = ?");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $stmt->bind_result($transaction_ref);
    $stmt->fetch();
    $stmt->close();

    // Handle order cancellation logic and refunds
    if ($orderStatus === 'Cancelled on Delivery') {
        // Refund logic
        $stmt = $conn->prepare("SELECT total_amount, customer_id FROM orders WHERE order_id = ?");
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $stmt->bind_result($totalAmount, $customerId);
        $stmt->fetch();
        $stmt->close();

        // Deduct 70% cancellation fee and add balance to customer's wallet
        $cancellation_fee = (0.7 * $totalAmount);
        $balance = $totalAmount - $cancellation_fee;
        $stmt = $conn->prepare("UPDATE wallets SET balance = balance + ? WHERE customer_id = ?");
        $stmt->bind_param("di", $balance, $customerId);
        $stmt->execute();
        $stmt->close();

        // Insert cancellation fee and refund into customer transactions
        $stmt = $conn->prepare("INSERT INTO customer_transactions 
            (customer_id, amount, date_created, transaction_type, payment_method, description) 
            VALUES 
            (?, ?, NOW(), 'credit', 'Refund', 'Refund for Cancelled Order $orderId on Delivery'),
            (?, ?, NOW(), 'debit', 'Order Cancellation Fee on Delivery', 'Fee deducted for Order: $orderId Cancelled on Delivery')");
        $stmt->bind_param("idid", $customerId, $balance, $customerId, $cancellation_fee);
        $stmt->execute();
        $stmt->close();

        // Update food stock quantities
        $stmt = $conn->prepare("SELECT food_id, quantity FROM order_details WHERE order_id = ?");
        $stmt->bind_param("i", $orderId);
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

        // Update revenue table
        $refunded_amount = $totalAmount - $cancellation_fee;
        $stmt = $conn->prepare("UPDATE revenue SET refunded_amount = ?, retained_amount = ?, status = ? WHERE order_id = ?");
        $stmt->bind_param("ddsi", $refunded_amount, $cancellation_fee, $orderStatus, $orderId);
        $stmt->execute();
        $stmt->close();

        // Update transactions table to 'Failed'
        $transaction_status = 'Failed';
        $stmt = $conn->prepare("UPDATE transactions SET status = ? WHERE order_id = ? AND transaction_ref = ?");
        $stmt->bind_param("sis", $transaction_status, $orderId, $transaction_ref);
        $stmt->execute();
        $stmt->close();
    } elseif ($orderStatus === 'Delivered') {
        // Update transaction to 'Completed' when the order is delivered
        $transaction_status = 'Completed';
        $stmt = $conn->prepare("UPDATE transactions SET status = ? WHERE order_id = ? AND transaction_ref = ?");
        $stmt->bind_param("sis", $transaction_status, $orderId, $transaction_ref);
        $stmt->execute();
        $stmt->close();
    }

    // Commit the transaction after all updates
    $conn->commit();
    echo json_encode(["success" => true, "message" => "Your Order has been successfully updated."]);

} catch (Exception $e) {
    // Rollback transaction in case of failure
    $conn->rollback();
    echo json_encode(["success" => false, "message" => "Transaction failed: " . $e->getMessage()]);
}

// Close the database connection
$conn->close();

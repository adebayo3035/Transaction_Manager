<?php
include 'config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['order_items']) || !isset($input['total_amount'])) {
        echo json_encode(["success" => false, "message" => "No order items or total amount found."]);
        exit();
    }

    $orderItems = $input['order_items'];
    $totalAmount = $input['total_amount'];


    if (!is_array($orderItems)) {
        echo json_encode(["success" => false, "message" => "Invalid order items format."]);
        exit();
    }

    $customerId = $_SESSION['customer_id'];
    $conn->begin_transaction();

    // code block to check for available quantity for each food item
    try {
        if ($totalAmount == 0) {
            throw new Exception("Your Order Cart is empty.");
        }

        // Check customer's wallet balance
        $stmt = $conn->prepare("SELECT balance FROM wallets WHERE customer_id = ?");
        $stmt->bind_param("i", $customerId);
        $stmt->execute();
        $stmt->bind_result($balance);
        $stmt->fetch();
        $stmt->close();

        if ($balance < $totalAmount) {
            throw new Exception("Insufficient balance in wallet.");
        }

        // Deduct the amount from Customer  wallet
        $newBalance = $balance - $totalAmount;
        $stmt = $conn->prepare("UPDATE wallets SET balance = ? WHERE customer_id = ?");
        $stmt->bind_param("di", $newBalance, $customerId);
        $stmt->execute();
        $stmt->close();

        // Insert into customer transaction table
        $description = "Food Order";
        $stmt = $conn->prepare("INSERT INTO customer_transactions (customer_id, amount, date_created, transaction_type, description) VALUES (?, ?, NOW(), 'debit', ?)");
        $stmt->bind_param("ids", $customerId, $totalAmount, $description);
        $stmt->execute();
        $stmt->close();
        // Insert into orders table
        $stmt = $conn->prepare("INSERT INTO orders (customer_id, order_date, total_amount) VALUES (?, NOW(), ?)");
        $stmt->bind_param("id", $customerId, $totalAmount);
        $stmt->execute();
        $orderId = $stmt->insert_id;
        $stmt->close();

        // Insert into order_details table
        $stmt = $conn->prepare("INSERT INTO order_details (order_id, food_id, quantity, price_per_unit, total_price) VALUES (?, ?, ?, ?, ?)");
        foreach ($orderItems as $item) {
            $stmt->bind_param("iiidd", $orderId, $item['food_id'], $item['quantity'], $item['price_per_unit'], $item['total_price']);
            $stmt->execute();

            // Update available quantity for each food Item selected
            // Update available quantity
            $update_query = "UPDATE food SET available_quantity = available_quantity - ? WHERE food_id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("ii", $item['quantity'], $item['food_id']);
            $update_stmt->execute();
            $update_stmt->close();
        }
        $stmt->close();

        // Insert revenue data
        $stmt = $conn->prepare("INSERT INTO revenue (order_id, customer_id, total_amount, transaction_date) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iid", $orderId, $customerId, $totalAmount);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        echo json_encode(["success" => true, "message" => "Order placed successfully."]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["success" => false, "message" => "Error placing order: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Invalid request."]);
}


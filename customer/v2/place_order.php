<?php
include 'config.php';
session_start();
$customerId = $_SESSION["customer_id"];
checkSession($customerId);

logActivity("Session Validation Check Successful");

logActivity("Request received to place an order for Customer ID: $customerId.");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['order_items']) || !isset($input['total_amount'])) {
        logActivity("Failed to place order for Customer ID: $customerId - Missing order items or total amount.");
        echo json_encode(["success" => false, "message" => "No order items or total amount found."]);
        exit();
    }

    $orderItems = $input['order_items'];
    $totalAmount = $input['total_amount'];

    logActivity("Received order items and total amount for Customer ID: $customerId.");

    if (!is_array($orderItems)) {
        logActivity("Failed to place order for Customer ID: $customerId - Invalid order items format.");
        echo json_encode(["success" => false, "message" => "Invalid order items format."]);
        exit();
    }

    logActivity("Order items format validated for Customer ID: $customerId.");

    $conn->begin_transaction();
    logActivity("Starting database transaction for Customer ID: $customerId.");

    try {
        if ($totalAmount == 0) {
            logActivity("Your Card is Empty. Kindly add order to Cart");
            throw new Exception("Your Order Cart is empty. Kindly add order to Cart");
        }

        logActivity("Total amount check passed for Customer ID: $customerId. Proceeding with wallet balance check.");

        // Check customer's wallet balance
        $stmt = $conn->prepare("SELECT balance FROM wallets WHERE customer_id = ?");
        $stmt->bind_param("i", $customerId);
        $stmt->execute();
        $stmt->bind_result($balance);
        $stmt->fetch();
        $stmt->close();

        logActivity("Customer ID: $customerId - Wallet balance retrieved: $balance.");

        if ($balance < $totalAmount) {
            logActivity("Customer ID: $customerId - Insufficient Wallet balance retrieved: $balance.");
            throw new Exception("Insufficient balance in wallet.");
        }

        // Log wallet balance check
        logActivity("Customer ID: $customerId has sufficient balance. Deducting $totalAmount from wallet.");

        // Deduct the amount from Customer wallet
        $newBalance = $balance - $totalAmount;
        $stmt = $conn->prepare("UPDATE wallets SET balance = ? WHERE customer_id = ?");
        $stmt->bind_param("di", $newBalance, $customerId);
        $stmt->execute();
        $stmt->close();

        logActivity("Customer ID: $customerId - Wallet balance updated to $newBalance.");

        // Fetch the last inserted record ID
        $sql = "SELECT order_id FROM orders ORDER BY order_id DESC LIMIT 1";
        $result = $conn->query($sql);
        $newId = 0;

        if ($result->num_rows > 0) {
            $lastRecord = $result->fetch_assoc();
            $newId = $lastRecord['order_id'] + 1; // Increment the last ID by 1
        } else {
            $newId = 1; // If no records found, set to 1
        }

        logActivity("New Order ID calculated: $newId.");

        // Concatenate the new ID to the description
        $description = "Food Order " . $newId;

        // Insert into customer transaction table
        $stmt = $conn->prepare("INSERT INTO customer_transactions (customer_id, amount, date_created, transaction_type, description) VALUES (?, ?, NOW(), 'debit', ?)");
        $stmt->bind_param("ids", $customerId, $totalAmount, $description);
        $stmt->execute();
        $stmt->close();

        logActivity("Transaction for Customer ID: $customerId recorded in customer_transactions.");

        // Insert into orders table
        $stmt = $conn->prepare("INSERT INTO orders (customer_id, order_date, total_amount) VALUES (?, NOW(), ?)");
        $stmt->bind_param("id", $customerId, $totalAmount);
        $stmt->execute();
        $orderId = $stmt->insert_id;
        $stmt->close();

        logActivity("Order created for Customer ID: $customerId with Order ID: $orderId.");

        // Insert into order_details table
        $stmt = $conn->prepare("INSERT INTO order_details (order_id, food_id, quantity, price_per_unit, total_price) VALUES (?, ?, ?, ?, ?)");
        foreach ($orderItems as $item) {
            $stmt->bind_param("iiidd", $orderId, $item['food_id'], $item['quantity'], $item['price_per_unit'], $item['total_price']);
            $stmt->execute();

            logActivity("Item added to Order ID: $orderId - Food ID: {$item['food_id']} with Quantity: {$item['quantity']}.");

            // Update available quantity for each food Item selected
            // Update available quantity
            $update_query = "UPDATE food SET available_quantity = available_quantity - ? WHERE food_id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("ii", $item['quantity'], $item['food_id']);
            $update_stmt->execute();
            $update_stmt->close();

            logActivity("Food ID: {$item['food_id']} quantity reduced by {$item['quantity']}.");
        }
        $stmt->close();

        // Insert revenue data
        $stmt = $conn->prepare("INSERT INTO revenue (order_id, customer_id, total_amount, transaction_date) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iid", $orderId, $customerId, $totalAmount);
        $stmt->execute();
        $stmt->close();

        logActivity("Revenue recorded for Order ID: $orderId.");

        $conn->commit();
        logActivity("Order placed successfully for Customer ID: $customerId with Order ID: $orderId.");
        echo json_encode(["success" => true, "message" => "Order placed successfully."]);
    } catch (Exception $e) {
        $conn->rollback();
        logActivity("Error placing order for Customer ID: $customerId - " . $e->getMessage());
        echo json_encode(["success" => false, "message" => "Error placing order: " . $e->getMessage()]);
    }
} else {
    logActivity("Invalid request method for Customer ID: $customerId.");
    echo json_encode(["success" => false, "message" => "Invalid request."]);
}


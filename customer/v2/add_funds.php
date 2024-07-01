<?php
include 'config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['amount'])) {
    $amount = $conn->real_escape_string($_POST['amount']);
    $customerId = $_SESSION['customer_id'];

    // Check if the amount is valid
    if (!is_numeric($amount) || $amount <= 0) {
        echo json_encode(["success" => false, "message" => "Invalid amount."]);
        exit();
    }

    // Begin transaction
    $conn->begin_transaction();

    try {
        // Update customer balance in wallets table
        $stmt = $conn->prepare("UPDATE wallets SET balance = balance + ? WHERE customer_id = ?");
        $stmt->bind_param("di", $amount, $customerId);
        $stmt->execute();

        // If no rows were updated, insert a new wallet record
        if ($stmt->affected_rows == 0) {
            $stmt = $conn->prepare("INSERT INTO wallets (customer_id, balance) VALUES (?, ?)");
            $stmt->bind_param("id", $customerId, $amount);
            $stmt->execute();
        }

        $stmt->close();

        // Commit transaction
        $conn->commit();
        echo json_encode(["success" => true, "message" => "Funds added successfully."]);
    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollback();
        echo json_encode(["success" => false, "message" => "Error adding funds: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Invalid request."]);
}


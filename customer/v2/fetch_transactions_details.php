<?php
include 'config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['transaction_id'])) {
        echo json_encode(["success" => false, "message" => "Transaction ID is missing."]);
        exit();
    }

    $transactionId = $input['transaction_id'];
    $customerId = $_SESSION['customer_id'];

    // $stmt = $conn->prepare("SELECT * FROM order_details WHERE order_id = ?");
    $query = "SELECT * FROM customer_transactions WHERE customer_id = ? AND id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $customerId , $transactionId);
    $stmt->execute();
    $result = $stmt->get_result();

    $transactionDetails = [];
    while ($row = $result->fetch_assoc()) {
        $transactionDetails[] = $row;
    }
    $stmt->close();

    echo json_encode(["success" => true, "transaction_details" => $transactionDetails]);
} else {
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
}

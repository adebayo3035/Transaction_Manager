<?php
include 'config.php';
session_start();

// Check if 'customer_id' is set in session
logActivity("Starting Session check for User");
$customerId = $_SESSION["customer_id"] ?? null;
checkSession($customerId);
logActivity("Session validated for customer ID: $customerId");


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    logActivity("Received POST request with payload: " . json_encode($input));
    
    if (!isset($input['transaction_id'])) {
        logActivity("Transaction ID missing in request.");
        echo json_encode(["success" => false, "message" => "Transaction ID is missing."]);
        exit();
    }

    $transactionId = $input['transaction_id'];
    logActivity("Fetching details for transaction ID: $transactionId");
    
    // Fetch transaction details
    $query = "SELECT * FROM customer_transactions WHERE customer_id = ? AND id = ?";
    logActivity("Executing query: $query");
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        logActivity("Failed to prepare transaction fetch statement: " . $conn->error);
        echo json_encode(["success" => false, "message" => "Database error."]);
        exit();
    }
    $stmt->bind_param("ii", $customerId, $transactionId);
    $stmt->execute();
    $result = $stmt->get_result();

    $transactionDetails = [];
    while ($row = $result->fetch_assoc()) {
        $transactionDetails[] = $row;
    }
    $stmt->close();
    $conn->close();
    logActivity("Fetched transaction details: " . json_encode($transactionDetails));

    echo json_encode(["success" => true, "transaction_details" => $transactionDetails]);
} else {
    logActivity("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
}

<?php
include('config.php');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
session_start();

$customerId = $_SESSION["customer_id"] ?? null;
logActivity("Session started. Customer ID: " . ($customerId ?? 'Not set'));

checkSession($customerId);

// Decode the JSON input
$data = json_decode(file_get_contents('php://input'), true);
logActivity("Received JSON input: " . json_encode($data));

// Check if JSON decoding was successful
if (json_last_error() !== JSON_ERROR_NONE) {
    logActivity("Invalid JSON input detected.");
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    logActivity("Processing POST request.");
    
    $new_question = $data['new_question'] ?? null;
    $new_answer = $data['new_answer'] ?? null;
    $confirm_answer = $data['confirm_answer'] ?? null;
    $token = $data['token_question'] ?? null;
    
    logActivity("Extracted request parameters: new_question=$new_question, token=$token");
    
    // Fetch customer from the database
    $hashedSecretAnswer = md5($new_answer);
    
    // Function to validate token 
    if (!isset($_SESSION['token']) || $_SESSION['token']['value'] !== $token || time() > $_SESSION['token']['expires_at']) {
        logActivity("Invalid or expired token detected.");
        echo json_encode(['success' => false, 'message' => 'Invalid or expired token']);
        exit;
    }
    
    if ($new_answer !== $confirm_answer) {
        logActivity("Data mismatch at secret answer. User input: $new_answer, Confirmation: $confirm_answer");
        echo json_encode(['success' => false, 'message' => 'Data Mismatch at secret answer! Please Try Again']);
        exit;
    }
    
    logActivity("Fetching customer details from database for customer_id: $customerId");
    $stmt = $conn->prepare("SELECT * FROM customers WHERE customer_id = ?");
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $customer = $result->fetch_assoc();

    if (!$customer) {
        logActivity("Customer not found for customer_id: $customerId");
        echo json_encode(['success' => false, 'message' => 'Customer not found.']);
        exit;
    }

    logActivity("Updating secret question and answer for customer_id: $customerId");
    $stmt = $conn->prepare("UPDATE customers SET secret_question = ?, secret_answer = ? WHERE customer_id = ?");
    $stmt->bind_param("ssi", $new_question, $hashedSecretAnswer, $customerId);
    if ($stmt->execute()) {
        logActivity("Secret question and answer updated successfully for customer_id: $customerId");
        echo json_encode([
            'success' => true,
            'message' => 'Secret Question and Answer has been Successfully Updated.',
            'redirect' => '../v1/cards.php' // Prepare the redirect URL
        ]);
    } else {
        logActivity("Failed to update secret question and answer for customer_id: $customerId. Error: " . $stmt->error);
        echo json_encode(['success' => false, 'message' => 'Update failed. Please try again.']);
    }
    
    $stmt->close();
    
    // Invalidate the token after use
    $_SESSION['token'] = [
        'value' => '',
        'expires_at' => time() // Token expires immediately
    ];
    logActivity("Token invalidated after use.");
} else {
    logActivity("Invalid request method detected: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}

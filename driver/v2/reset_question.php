<?php
include('config.php');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
session_start();

// Check if user is logged in
if (!isset($_SESSION['customer_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit;
}
// Decode the JSON input
$data = json_decode(file_get_contents('php://input'), true);

// Check if JSON decoding was successful
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_question = $data['new_question'] ?? null;
    $new_answer = $data['new_answer'] ?? null;
    $confirm_answer = $data['confirm_answer'] ?? null;
    $token = $data['token_question'] ?? null;
    // Fetch customer from the database
    $customerId = $_SESSION['customer_id'];
    $hashedSecretAnswer = md5($new_answer);

    // Function to validate token 
    if (!isset($_SESSION['token']) || $_SESSION['token']['value'] !== $token || time() > $_SESSION['token']['expires_at']) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired token']);
        exit;
    }
    if ($new_answer !== $confirm_answer) {
        echo json_encode(['success' => false, 'message' => 'Data Mismatch at secret answer! Please Try Again']);
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM customers WHERE customer_id = ?");
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $customer = $result->fetch_assoc();

    if (!$customer) {
        echo json_encode(['success' => false, 'message' => 'Customer not found.']);
        exit;
    }

    // Update Secret Question and answer
    $stmt = $conn->prepare("UPDATE customers SET secret_question = ?, secret_answer = ? WHERE customer_id = ?");
    $stmt->bind_param("ssi", $new_question, $hashedSecretAnswer, $customerId);
    $stmt->execute();
    echo json_encode([
        'success' => true,
        'message' => 'Secret Question and Answer has been Successfully Updated.',
        'redirect' => '../v1/cards.php' // Prepare the redirect URL
    ]);
    $stmt->close();
    $_SESSION['token'] = [
        'value' => '',
        'expires_at' => time() // Token expires in 60 seconds
    ];

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}

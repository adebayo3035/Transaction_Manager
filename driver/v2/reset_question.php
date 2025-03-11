<?php
include('config.php');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
session_start();

// Function to log activity

// Log function entry
logActivity("Entering secret question update handler.");

// Check if user is logged in
if (!isset($_SESSION['customer_id'])) {
    // Log session validation failure
    logActivity("User not logged in.");
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit;
}

// Decode the JSON input
$data = json_decode(file_get_contents('php://input'), true);

// Check if JSON decoding was successful
if (json_last_error() !== JSON_ERROR_NONE) {
    // Log JSON decoding error
    logActivity("Invalid JSON input received.");
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

// Log JSON input
logActivity("JSON input received: " . json_encode($data));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_question = $data['new_question'] ?? null;
    $new_answer = $data['new_answer'] ?? null;
    $confirm_answer = $data['confirm_answer'] ?? null;
    $token = $data['token_question'] ?? null;

    // Log input data
    logActivity("Input data - New Question: $new_question, New Answer: [hidden], Confirm Answer: [hidden], Token: $token");

    // Fetch customer from the database
    $customerId = $_SESSION['customer_id'];
    $hashedSecretAnswer = md5($new_answer);
    checkSession($customerId);

    // Log customer ID and hashed secret answer
    logActivity("Customer ID: $customerId, Hashed Secret Answer: [hidden]");

    // Function to validate token
    if (!isset($_SESSION['token']) || $_SESSION['token']['value'] !== $token || time() > $_SESSION['token']['expires_at']) {
        // Log token validation failure
        logActivity("Invalid or expired token.");
        echo json_encode(['success' => false, 'message' => 'Invalid or expired token']);
        exit;
    }

    if ($new_answer !== $confirm_answer) {
        // Log secret answer mismatch
        logActivity("Secret answer mismatch.");
        echo json_encode(['success' => false, 'message' => 'Data Mismatch at secret answer! Please Try Again']);
        exit;
    }

    // Fetch customer details
    $query = "SELECT * FROM customers WHERE customer_id = ?";
    $stmt = $conn->prepare($query);

    if ($stmt === false) {
        // Log SQL preparation failure
        logActivity("Failed to prepare SQL statement for fetching customer details.");
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }

    // Log SQL query and parameters
    logActivity("Executing SQL query: $query | Params: [$customerId]");

    $stmt->bind_param("i", $customerId);
    if (!$stmt->execute()) {
        // Log SQL execution failure
        logActivity("Failed to execute SQL statement for fetching customer details.");
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }

    $result = $stmt->get_result();
    $customer = $result->fetch_assoc();

    if (!$customer) {
        // Log customer not found
        logActivity("Customer not found for customer ID: $customerId.");
        echo json_encode(['success' => false, 'message' => 'Customer not found.']);
        exit;
    }

    // Log customer details fetched
    logActivity("Customer details fetched for customer ID: $customerId.");

    // Update Secret Question and answer
    $queryUpdate = "UPDATE customers SET secret_question = ?, secret_answer = ? WHERE customer_id = ?";
    $stmtUpdate = $conn->prepare($queryUpdate);

    if ($stmtUpdate === false) {
        // Log SQL preparation failure
        logActivity("Failed to prepare SQL statement for updating secret question and answer.");
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }

    // Log SQL query and parameters
    logActivity("Executing SQL query: $queryUpdate | Params: [$new_question, [hidden], $customerId]");

    $stmtUpdate->bind_param("ssi", $new_question, $hashedSecretAnswer, $customerId);
    if (!$stmtUpdate->execute()) {
        // Log SQL execution failure
        logActivity("Failed to execute SQL statement for updating secret question and answer.");
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }

    // Log successful update
    logActivity("Secret question and answer updated successfully for customer ID: $customerId.");

    echo json_encode([
        'success' => true,
        'message' => 'Secret Question and Answer has been Successfully Updated.',
        'redirect' => '../v1/cards.php' // Prepare the redirect URL
    ]);

    $stmtUpdate->close();

    // Invalidate the token
    $_SESSION['token'] = [
        'value' => '',
        'expires_at' => time() // Token expires immediately
    ];

    // Log token invalidation
    logActivity("Token invalidated for customer ID: $customerId.");
} else {
    // Log invalid request method
    logActivity("Invalid request method received.");
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}

// Log function exit
logActivity("Exiting secret question update handler.");
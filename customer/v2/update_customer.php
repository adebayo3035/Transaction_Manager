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
    $updateOption = $data['updateOption'] ?? null;
    $currentData = $data['currentData'] ?? null;
    $newData = $data['newData'] ?? null;
    $confirmNewData = $data['confirmNewData'] ?? null;
    $token = $data['token'] ?? null;
    $secretAnswer = $data['secretAnswer'] ?? null;
    

    // Fetch customer from the database
    $customerId = $_SESSION['customer_id'];

    // Function to validate token 
if (!isset($_SESSION['token']) || $_SESSION['token']['value'] !== $token || time() > $_SESSION['token']['expires_at']) {
    echo json_encode(['success' => false, 'message' => 'Invalid or expired token']);
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

    // Validate secret answer
    $hashedSecretAnswer = md5($secretAnswer);
    if ($customer['secret_answer'] !== $hashedSecretAnswer) {
        echo json_encode(['success' => false, 'message' => 'Customer Validation Failed.']);
        exit;
    }

    // Validate new data and check for duplicates
    if ($updateOption === 'password') {
        if ($newData !== $confirmNewData) {
            echo json_encode(['success' => false, 'message' => 'New data and confirmation do not match.']);
            exit;
        }
        // Update password
        $newPasswordHash = md5($newData);
        $oldPasswordHash = md5($currentData);
        $stmt = $conn->prepare("UPDATE customers SET password = ? WHERE customer_id = ? AND password = ?");
        $stmt->bind_param("sis", $newPasswordHash, $customerId, $oldPasswordHash);
        $stmt->execute();

    } else if ($updateOption === 'phone_number') {
        if ($newData !== $confirmNewData) {
            echo json_encode(['success' => false, 'message' => 'New phone number and confirmation do not match.']);
            exit;
        }
        // Check for duplicate phone number
        $stmt = $conn->prepare("SELECT * FROM customers WHERE mobile_number = ? AND customer_id <> ?");
        $stmt->bind_param("si", $newData, $customerId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'New Phone number already in use.']);
            exit;
        }
        // Update phone number
        $stmt = $conn->prepare("UPDATE customers SET mobile_number = ? WHERE customer_id = ? AND mobile_number = ?");
        $stmt->bind_param("sis", $newData, $customerId, $currentData);
        $stmt->execute();

    } else if ($updateOption === 'email') {
        if ($newData !== $confirmNewData) {
            echo json_encode(['success' => false, 'message' => 'New email and confirmation do not match.']);
            exit;
        }
        // Check for duplicate email
        $stmt = $conn->prepare("SELECT * FROM customers WHERE email = ? AND customer_id <> ?");
        $stmt->bind_param("si", $newData, $customerId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'New Email already in use.']);
            exit;
        }
        // Update email
        $stmt = $conn->prepare("UPDATE customers SET email = ? WHERE customer_id = ? AND email = ?");
        $stmt->bind_param("sis", $newData, $customerId, $currentData);
        $stmt->execute();

    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid Customer update option.']);
        exit;
    }

    // Success
    
    // echo json_encode(['success' => true, 'message' => 'Customer Information Update successful.']);
      // Success
      echo json_encode([
        'success' => true,
        'message' => 'Customer Information Update successful.',
        'customer_id' => $customerId, // Include customer_id in the response
        'redirect' => '../v2/logout.php?logout_id=' . urlencode($customerId) // Prepare the redirect URL
    ]);
    $_SESSION['token'] = [
        'value' => '',
        'expires_at' => time() // Token expires in 60 seconds
    ];

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}

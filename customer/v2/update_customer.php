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
    logActivity("Invalid JSON received");
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
    
    logActivity("Update option: $updateOption, Customer ID: $customerId");

    // Validate token 
    if (!isset($_SESSION['token']) || $_SESSION['token']['value'] !== $token || time() > $_SESSION['token']['expires_at']) {
        logActivity("Invalid or expired token for Customer ID: $customerId");
        echo json_encode(['success' => false, 'message' => 'Invalid or expired token']);
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM customers WHERE customer_id = ?");
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $customer = $result->fetch_assoc();

    if (!$customer) {
        logActivity("Customer not found for ID: $customerId");
        echo json_encode(['success' => false, 'message' => 'Customer not found.']);
        exit;
    }

    // Validate secret answer
    if ($customer['secret_answer'] !== md5($secretAnswer)) {
        logActivity("Customer validation failed for ID: $customerId");
        echo json_encode(['success' => false, 'message' => 'Customer Validation Failed.']);
        exit;
    }

    // Validate new data and check for duplicates
    if ($updateOption === 'password') {
        if ($newData !== $confirmNewData) {
            logActivity("Password confirmation mismatch for Customer ID: $customerId");
            echo json_encode(['success' => false, 'message' => 'New data and confirmation do not match.']);
            exit;
        }
        
        if ($customer['password'] !== md5($currentData)) {
            logActivity("Invalid old password for Customer ID: $customerId");
            echo json_encode(['success' => false, 'message' => 'Invalid Old Password.']);
            exit;
        }
        $encrypted_password = md5($newData);
        $stmt = $conn->prepare("UPDATE customers SET password = ? WHERE customer_id = ?");
        $stmt->bind_param("si", $encrypted_password , $customerId);
        $stmt->execute();
    } elseif ($updateOption === 'phone_number') {
        if ($newData !== $confirmNewData) {
            logActivity("Phone number confirmation mismatch for Customer ID: $customerId");
            echo json_encode(['success' => false, 'message' => 'New phone number and confirmation do not match.']);
            exit;
        }
        
        if ($customer['mobile_number'] !== $currentData) {
            logActivity("Invalid old phone number for Customer ID: $customerId");
            echo json_encode(['success' => false, 'message' => 'Invalid Old Phone Number.']);
            exit;
        }
        
        $stmt = $conn->prepare("SELECT * FROM customers WHERE mobile_number = ? AND customer_id <> ?");
        $stmt->bind_param("si", $newData, $customerId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            logActivity("Duplicate phone number for Customer ID: $customerId");
            echo json_encode(['success' => false, 'message' => 'Phone number already in use.']);
            exit;
        }
        
        $stmt = $conn->prepare("UPDATE customers SET mobile_number = ? WHERE customer_id = ?");
        $stmt->bind_param("si", $newData, $customerId);
        $stmt->execute();
    } elseif ($updateOption === 'email') {
        if ($newData !== $confirmNewData) {
            logActivity("Email confirmation mismatch for Customer ID: $customerId");
            echo json_encode(['success' => false, 'message' => 'New email and confirmation do not match.']);
            exit;
        }
        
        if ($customer['email'] !== $currentData) {
            logActivity("Invalid old email for Customer ID: $customerId");
            echo json_encode(['success' => false, 'message' => 'Invalid E-mail Address.']);
            exit;
        }
        
        $stmt = $conn->prepare("SELECT * FROM customers WHERE email = ? AND customer_id <> ?");
        $stmt->bind_param("si", $newData, $customerId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            logActivity("Duplicate email for Customer ID: $customerId");
            echo json_encode(['success' => false, 'message' => 'Email already in use.']);
            exit;
        }
        
        $stmt = $conn->prepare("UPDATE customers SET email = ? WHERE customer_id = ?");
        $stmt->bind_param("si", $newData, $customerId);
        $stmt->execute();
    } else {
        logActivity("Invalid update option for Customer ID: $customerId");
        echo json_encode(['success' => false, 'message' => 'Invalid update option.']);
        exit;
    }
    
    logActivity("Customer update successful for ID: $customerId");
    echo json_encode([
        'success' => true,
        'message' => 'Customer Information Update successful.',
        'customer_id' => $customerId,
        'redirect' => '../v2/logout.php?logout_id=' . urlencode($customerId)
    ]);
    
    $_SESSION['token'] = ['value' => '', 'expires_at' => time()];
} else {
    logActivity("Invalid request method");
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}

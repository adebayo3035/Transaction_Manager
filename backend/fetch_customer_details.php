<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

// Initialize logging
logActivity("Customer details fetch process started");

try {
    // Check authentication
    if (!isset($_SESSION['unique_id'])) {
        $errorMsg = "Unauthenticated access attempt to customer details";
        logActivity($errorMsg);
        echo json_encode(["success" => false, "message" => "Not logged in."]);
        exit();
    }

    $adminId = $_SESSION['unique_id'];
    $userRole = $_SESSION['role'] ?? null;
    logActivity("Request initiated by user ID: $adminId (Role: $userRole)");

    // Get and validate input
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON input: " . json_last_error_msg());
    }

    if (!isset($input['customer_id'])) {
        $errorMsg = "Missing customer_id in request data";
        logActivity($errorMsg);
        echo json_encode(['success' => false, 'message' => 'Customer ID is required']);
        exit();
    }

    $customer_id = filter_var($input['customer_id'], FILTER_VALIDATE_INT);
    if ($customer_id === false || $customer_id < 1) {
        $errorMsg = "Invalid customer_id format: " . $input['customer_id'];
        logActivity($errorMsg);
        echo json_encode(['success' => false, 'message' => 'Invalid Customer ID format']);
        exit();
    }

    logActivity("Processing customer details for ID: " . $customer_id);

    try {
        // Start transaction for consistent data view
        $conn->begin_transaction();
        logActivity("Database transaction started");

        // Fetch customer details with group and unit information
        $query = "SELECT c.*, g.group_name, u.unit_name 
                 FROM customers c
                 LEFT JOIN groups g ON c.group_id = g.group_id
                 LEFT JOIN unit u ON c.unit_id = u.unit_id
                 WHERE c.customer_id = ?";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed for customer details: " . $conn->error);
        }

        $stmt->bind_param("i", $customer_id);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed for customer details: " . $stmt->error);
        }

        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            logActivity("No customer found with ID: " . $customer_id);
            echo json_encode([
                'success' => false, 
                'message' => 'Customer not found',
                'customer_id' => $customer_id
            ]);
            exit();
        }

        $customerDetails = $result->fetch_assoc();
        // $stmt->close();
        $conn->commit();

        // Format sensitive data if needed (e.g., mask part of email)
        if (isset($customerDetails['email'])) {
            $customerDetails['email_masked'] = maskEmail($customerDetails['email']);
        }

        logActivity("Successfully retrieved customer details for ID: " . $customer_id);

        // Prepare response
        $response = [
            'success' => true,
            'customer_details' => $customerDetails,
            'requested_by' => $adminId,
            'user_role' => $userRole,
            'timestamp' => date('c')
        ];

        echo json_encode($response);
        logActivity("Customer details fetch completed successfully");

    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
} catch (Exception $e) {
    $errorMsg = "Error fetching customer details: " . $e->getMessage();
    logActivity($errorMsg);
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to fetch customer details',
        'error' => $e->getMessage(),
        'customer_id' => $customer_id ?? null
    ]);
} finally {
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    $conn->close();
    logActivity("Customer details fetch process completed");
}

// Helper function to mask sensitive email data
function maskEmail($email) {
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $parts = explode("@", $email);
        $username = $parts[0];
        $domain = $parts[1];
        $maskedUsername = substr($username, 0, 2) . str_repeat("*", max(0, strlen($username) - 4)) . substr($username, -2);
        return $maskedUsername . "@" . $domain;
    }
    return $email;
}
<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

// Initialize logging
logActivity("Transaction details fetch process started");

try {
    // Check authentication
    if (!isset($_SESSION['unique_id'])) {
        $errorMsg = "Unauthenticated access attempt to transaction details";
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

    if (!isset($input['transaction_ref'])) {
        $errorMsg = "Missing transaction reference in request";
        logActivity($errorMsg);
        echo json_encode(['success' => false, 'message' => 'Transaction reference is required']);
        exit();
    }

    $transaction_ref = trim($input['transaction_ref']);
    if (empty($transaction_ref)) {
        $errorMsg = "Empty transaction reference provided";
        logActivity($errorMsg);
        echo json_encode(['success' => false, 'message' => 'Transaction reference cannot be empty']);
        exit();
    }

    logActivity("Fetching details for transaction reference: " . $transaction_ref);

    try {
        // Start transaction for consistent data view
        $conn->begin_transaction();
        logActivity("Database transaction started");

        // Fetch transaction details with customer and revenue type information
        $query = "SELECT c.firstname, c.lastname, c.email, t.*, 
                  r.revenue_type_name, r.revenue_type_description
                  FROM transactions t
                  LEFT JOIN customers c ON t.customer_id = c.customer_id
                  LEFT JOIN revenue_types r ON t.revenue_type_id = r.revenue_type_id 
                  WHERE t.transaction_ref = ?";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("s", $transaction_ref);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            logActivity("No transaction found with reference: " . $transaction_ref);
            echo json_encode([
                'success' => false, 
                'message' => 'Transaction not found',
                'reference' => $transaction_ref
            ]);
            exit();
        }

        $transactionDetails = $result->fetch_assoc();
        // $stmt->close();
        $conn->commit();

        logActivity("Successfully retrieved transaction details for reference: " . $transaction_ref);

        // Prepare response
        $response = [
            'success' => true,
            'transaction_details' => $transactionDetails,
            'requested_by' => $adminId,
            'user_role' => $userRole,
            'timestamp' => date('c')
        ];

        echo json_encode($response);
        logActivity("Transaction details fetch completed successfully");

    } catch (Exception $e) {
        $conn->rollback();
        throw $e; // Re-throw to outer catch block
    }
} catch (Exception $e) {
    $errorMsg = "Error fetching transaction details: " . $e->getMessage();
    logActivity($errorMsg);
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to fetch transaction details',
        'error' => $e->getMessage(),
        'reference' => $transaction_ref ?? null
    ]);
} finally {
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    $conn->close();
    logActivity("Transaction details fetch process completed");
}
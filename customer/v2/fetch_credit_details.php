<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

try {
    // Validate session
    $customerId = $_SESSION["customer_id"] ?? null;
    logActivity("SESSION_VALIDATION: Starting session validation");
    
    if (!$customerId) {
        throw new Exception("No customer ID found in session");
    }
    
    checkSession($customerId);
    logActivity("SESSION_VALIDATION: Successfully validated session for customer");

    // Decode JSON input with validation
    logActivity("INPUT_PROCESSING: Reading JSON input");
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        $errorMsg = "Invalid JSON input: " . json_last_error_msg();
        logActivity("INPUT_ERROR: $errorMsg");
        throw new Exception($errorMsg);
    }
    logActivity("INPUT_PROCESSING: Successfully decoded JSON input");

    // Validate credit_id
    $credit_id = $input['credit_id'] ?? null;
    logActivity("INPUT_VALIDATION: Validating credit_id - Received: " . ($credit_id ?? 'NULL'));
    
    if (empty($credit_id)) {
        throw new Exception("Credit ID is required");
    }
    logActivity("INPUT_VALIDATION: Credit ID validation passed");

    // Database operation
    logActivity("DATABASE_OPERATION: Preparing to fetch credit details for ID: $credit_id");
    $stmt = $conn->prepare("SELECT * FROM credit_orders WHERE credit_order_id = ?");
    
    if (!$stmt) {
        $dbError = "Database error: " . $conn->error;
        logActivity("DATABASE_ERROR: $dbError");
        throw new Exception($dbError);
    }

    $stmt->bind_param("s", $credit_id);
    logActivity("DATABASE_OPERATION: Bound parameters for query");

    if (!$stmt->execute()) {
        $execError = "Query execution failed: " . $stmt->error;
        logActivity("DATABASE_ERROR: $execError");
        throw new Exception($execError);
    }
    logActivity("DATABASE_OPERATION: Query executed successfully");

    $result = $stmt->get_result();
    $details = [];
    $recordCount = 0;

    logActivity("DATA_PROCESSING: Processing result set");
    while ($row = $result->fetch_assoc()) {
        $recordCount++;
        logActivity("DATA_PROCESSING: Processing record $recordCount - Credit ID: " . $row['credit_order_id']);
        
        // Determine if credit is due
        $currentDate = new DateTime();
        $dueDate = new DateTime($row['due_date']);
        $repaymentStatus = $row['repayment_status'];
        $isDue = false;

        if ($currentDate > $dueDate && in_array($repaymentStatus, ['Pending', 'Partially Paid'])) {
            $isDue = true;
            logActivity("CREDIT_STATUS: Credit ID {$row['credit_order_id']} is overdue");
        }

        $row['is_due'] = $isDue;
        $details[] = $row;
    }

    logActivity("RESPONSE_PREPARATION: Preparing response with $recordCount records found");
    
    $response = [
        'success' => true,
        'credit_details' => $details,
        'record_count' => $recordCount
    ];
    
    echo json_encode($response);
    logActivity("REQUEST_COMPLETE: Successfully returned credit details");

} catch (Exception $e) {
    logActivity("FATAL_ERROR: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    $errorResponse = [
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => $e->getCode()
    ];
    
    echo json_encode($errorResponse);
    
    // Additional error handling if needed
    if (strpos($e->getMessage(), 'Database') !== false) {
        logActivity("SYSTEM_ALERT: Database-related error occurred");
    }
} finally {
    // Ensure resources are cleaned up
    if (isset($stmt)) {
        $stmt->close();
        logActivity("RESOURCE_CLEANUP: Database statement closed");
    }
    
    if (isset($conn)) {
        $conn->close();
        logActivity("RESOURCE_CLEANUP: Database connection closed");
    }
}
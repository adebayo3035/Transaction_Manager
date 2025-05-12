<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

// Initialize logging
logActivity("Credit details fetch process started");

try {
    // Check authentication
    if (!isset($_SESSION['unique_id'])) {
        $errorMsg = "Unauthenticated access attempt";
        logActivity($errorMsg);
        echo json_encode(["success" => false, "message" => "Not logged in."]);
        exit();
    }

    $adminId = $_SESSION['unique_id'];
    logActivity("Request initiated by admin ID: " . $adminId);

    // Get and validate input
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON input: " . json_last_error_msg());
    }

    $credit_id = $input['credit_id'] ?? null;
    if (!$credit_id) {
        $errorMsg = "Missing credit ID in request";
        logActivity($errorMsg);
        echo json_encode(['success' => false, 'message' => 'Credit ID is required']);
        exit();
    }

    // Start transaction for consistent data view
    $conn->begin_transaction();
    logActivity("Transaction started for credit details fetch");

    try {
        // Fetch credit details with FOR UPDATE to prevent concurrent modifications
        $stmt = $conn->prepare("SELECT * FROM credit_orders WHERE credit_order_id = ? FOR UPDATE");
        if (!$stmt) {
            throw new Exception("Prepare failed for credit details: " . $conn->error);
        }

        $stmt->bind_param("s", $credit_id);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed for credit details: " . $stmt->error);
        }

        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            throw new Exception("Credit order not found with ID: " . $credit_id);
        }

        $creditDetails = $result->fetch_assoc();
        // $stmt->close();

        // Calculate due status
        $currentDate = new DateTime();
        $dueDate = new DateTime($creditDetails['due_date']);
        $repaymentStatus = $creditDetails['repayment_status'];
        $isDue = ($currentDate > $dueDate && in_array($repaymentStatus, ['Pending', 'Partially Paid']));
        $creditDetails['is_due'] = $isDue;
        $creditDetails['days_overdue'] = $isDue ? $currentDate->diff($dueDate)->days : 0;

        logActivity("Credit details retrieved for ID: " . $credit_id . 
                  ", Status: " . $repaymentStatus . 
                  ", Due: " . ($isDue ? 'Yes' : 'No'));

        // Fetch repayment history
        $repaymentStmt = $conn->prepare("SELECT * FROM repayment_history WHERE credit_order_id = ? ORDER BY payment_date DESC");
        if (!$repaymentStmt) {
            throw new Exception("Prepare failed for repayment history: " . $conn->error);
        }

        $repaymentStmt->bind_param("s", $credit_id);
        if (!$repaymentStmt->execute()) {
            throw new Exception("Execute failed for repayment history: " . $repaymentStmt->error);
        }

        $repaymentResult = $repaymentStmt->get_result();
        $repaymentHistory = [];

        while ($repaymentRow = $repaymentResult->fetch_assoc()) {
            $repaymentHistory[] = $repaymentRow;
        }

        // $repaymentStmt->close();
        logActivity("Found " . count($repaymentHistory) . " repayment records");

        // Calculate summary stats
        $totalPaid = array_sum(array_column($repaymentHistory, 'amount_paid'));
        $remainingBalance = max(0, $creditDetails['total_credit_amount'] - $totalPaid);

        $conn->commit();

        // Prepare response
        $response = [
            'success' => true,
            'credit_details' => $creditDetails,
            'repayment_history' => $repaymentHistory,
            'summary' => [
                'total_paid' => $totalPaid,
                'remaining_balance' => $remainingBalance,
                'payment_progress' => ($creditDetails['total_credit_amount'] > 0) 
                    ? round(($totalPaid / $creditDetails['total_credit_amount']) * 100, 2)
                    : 0
            ],
            'requested_by' => $adminId,
            'timestamp' => date('c')
        ];

        echo json_encode($response);
        logActivity("Credit details fetch completed successfully");

    } catch (Exception $e) {
        $conn->rollback();
        throw $e; // Re-throw to outer catch block
    }
} catch (Exception $e) {
    $errorMsg = "Error fetching credit details: " . $e->getMessage();
    logActivity($errorMsg);
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to fetch credit details',
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    if (isset($repaymentStmt) && $repaymentStmt instanceof mysqli_stmt) {
        $repaymentStmt->close();
    }
    $conn->close();
    logActivity("Credit details fetch process completed");
}

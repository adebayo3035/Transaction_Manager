<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

// Initialize logging
logActivity("Credit details fetch process started");

try {
    // Check authentication
    if (!isset($_SESSION['unique_id'])) {
        logActivity("Unauthenticated access attempt");
        echo json_encode(["success" => false, "message" => "Not logged in."]);
        exit();
    }

    $adminId = $_SESSION['unique_id'];
    logActivity("Request initiated by admin ID: $adminId");

    // Parse JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON input: " . json_last_error_msg());
    }

    $credit_id = $input['credit_id'] ?? null;
    if (!$credit_id) {
        logActivity("Missing credit ID");
        echo json_encode(['success' => false, 'message' => 'Credit ID is required']);
        exit();
    }

    $page = isset($input['page']) && $input['page'] > 0 ? (int)$input['page'] : 1;
    $limit = isset($input['limit']) && $input['limit'] > 0 ? (int)$input['limit'] : 5;
    $offset = ($page - 1) * $limit;

    // Start transaction
    $conn->begin_transaction();
    logActivity("Transaction started for credit details");

    // Fetch credit details
    $stmt = $conn->prepare("SELECT * FROM credit_orders WHERE credit_order_id = ? FOR UPDATE");
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
    $stmt->bind_param("s", $credit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) throw new Exception("Credit order not found: $credit_id");
    $creditDetails = $result->fetch_assoc();

    // Calculate due status
    $currentDate = new DateTime();
    $dueDate = new DateTime($creditDetails['due_date']);
    $repaymentStatus = $creditDetails['repayment_status'];
    $isDue = ($currentDate > $dueDate && in_array($repaymentStatus, ['Pending', 'Partially Paid']));
    $creditDetails['is_due'] = $isDue;
    $creditDetails['days_overdue'] = $isDue ? $currentDate->diff($dueDate)->days : 0;

    // Repayment History with Pagination
    $repaymentQuery = "SELECT * FROM repayment_history WHERE credit_order_id = ? ORDER BY payment_date DESC LIMIT ? OFFSET ?";
    $repaymentStmt = $conn->prepare($repaymentQuery);
    if (!$repaymentStmt) throw new Exception("Prepare failed for repayments: " . $conn->error);
    $repaymentStmt->bind_param("sii", $credit_id, $limit, $offset);
    $repaymentStmt->execute();
    $repaymentResult = $repaymentStmt->get_result();

    $repaymentHistory = [];
    while ($row = $repaymentResult->fetch_assoc()) {
        $repaymentHistory[] = $row;
    }

    // Total count of repayments
    $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM repayment_history WHERE credit_order_id = ?");
    if (!$countStmt) throw new Exception("Prepare failed for count: " . $conn->error);
    $countStmt->bind_param("s", $credit_id);
    $countStmt->execute();
    $totalRepayments = $countStmt->get_result()->fetch_assoc()['total'];

    // Summary
    $totalPaid = array_sum(array_column($repaymentHistory, 'amount_paid'));
    $remainingBalance = max(0, $creditDetails['total_credit_amount'] - $totalPaid);

    $conn->commit();

    // Respond
    echo json_encode([
        'success' => true,
        'credit_details' => $creditDetails,
        'repayment_history' => [
            'records' => $repaymentHistory,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $totalRepayments,
                'total_pages' => ceil($totalRepayments / $limit)
            ]
        ],
        'summary' => [
            'total_paid' => $totalPaid,
            'remaining_balance' => $remainingBalance,
            'payment_progress' => $creditDetails['total_credit_amount'] > 0
                ? round(($totalPaid / $creditDetails['total_credit_amount']) * 100, 2)
                : 0
        ],
        'requested_by' => $adminId,
        'timestamp' => date('c')
    ]);

    logActivity("Credit details fetch successful");

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    logActivity("Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch credit details',
        'error' => $e->getMessage()
    ]);
} finally {
    $stmt?->close();
    $repaymentStmt?->close();
    $countStmt?->close();
    $conn->close();
    logActivity("Credit details fetch process completed");
}

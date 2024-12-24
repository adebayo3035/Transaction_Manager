<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

if (!isset($_SESSION['unique_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}

$adminId = $_SESSION['unique_id'];

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
$credit_id = $input['credit_id'] ?? null;

if (!$credit_id) {
    echo json_encode(['success' => false, 'message' => 'Credit ID is required']);
    exit;
}

try {
    // Fetch credit details
    $stmt = $conn->prepare("SELECT * FROM credit_orders WHERE credit_order_id = ?");
    $stmt->bind_param("s", $credit_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $details = [];
    while ($row = $result->fetch_assoc()) {
        // Determine if the credit is due
        $currentDate = new DateTime();
        $dueDate = new DateTime($row['due_date']);
        $repaymentStatus = $row['repayment_status'];
        $isDue = false;

        if ($currentDate > $dueDate && ($repaymentStatus === 'Pending' || $repaymentStatus === 'Partially Paid')) {
            $isDue = true;
        }

        // Add the "is_due" flag to the row
        $row['is_due'] = $isDue;

        $details = $row; // Assuming only one row will match
    }

    // Fetch repayment history for this credit order
    $repaymentStmt = $conn->prepare("SELECT * FROM repayment_history WHERE credit_order_id = ?");
    $repaymentStmt->bind_param("s", $credit_id);
    $repaymentStmt->execute();
    $repaymentResult = $repaymentStmt->get_result();

    $repaymentHistory = [];
    while ($repaymentRow = $repaymentResult->fetch_assoc()) {
        $repaymentHistory[] = $repaymentRow;
    }

    // Combine the data
    echo json_encode([
        'success' => true,
        'credit_details' => $details,
        'repayment_history' => $repaymentHistory
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

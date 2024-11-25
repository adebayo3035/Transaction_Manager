<?php
header('Content-Type: application/json');
require 'config.php';
session_start();
if (!isset($_SESSION['customer_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}

$customerId = $_SESSION['customer_id'];

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
$credit_id = $input['credit_id'] ?? null;

if (!$credit_id) {
    echo json_encode(['success' => false, 'message' => 'Credit ID is required']);
    exit;
}

try {
    // Fetch credit details
    $stmt = $conn->prepare("SELECT * from credit_orders WHERE credit_order_id = ?");
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

        $details[] = $row;
    }

    echo json_encode([
        'success' => true,
        'credit_details' => $details
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

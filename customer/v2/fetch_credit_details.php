<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

// Function to log activity

$customerId = $_SESSION["customer_id"] ?? null;
checkSession(($customerId));

logActivity("Customer ID: $customerId - Session validated.");

// Decode JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    logActivity("Invalid JSON input received.");
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit;
}

// Validate `credit_id`
$credit_id = $input['credit_id'] ?? null;
if (empty($credit_id)) {
    logActivity("Missing credit_id in request.");
    echo json_encode(['success' => false, 'message' => 'Credit ID is required']);
    exit;
}

logActivity("Fetching credit details for Credit ID: $credit_id.");

try {
    // Fetch credit details
    $stmt = $conn->prepare("SELECT * FROM credit_orders WHERE credit_order_id = ?");
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }

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

        if ($currentDate > $dueDate && in_array($repaymentStatus, ['Pending', 'Partially Paid'])) {
            $isDue = true;
        }

        $row['is_due'] = $isDue;
        $details[] = $row;
    }

    logActivity("Credit details fetched successfully for Credit ID: $credit_id.");

    echo json_encode([
        'success' => true,
        'credit_details' => $details
    ]);

} catch (Exception $e) {
    logActivity("Error fetching credit details: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$stmt->close();
$conn->close();

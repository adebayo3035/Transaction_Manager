<?php
// Include database connection
include('config.php');

// Get the JSON data from the request body
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['transaction_ref'])) {
    echo json_encode(['success' => false, 'message' => 'Transaction reference is missing.']);
    exit();
}

$transaction_ref = $data['transaction_ref'];

// Fetch transaction details
$query = "SELECT  c.firstname, c.lastname, c.email, t.*, r.revenue_type_name, r.revenue_type_description
          FROM transactions t
          LEFT JOIN customers c ON t.customer_id = c.customer_id
          LEFT JOIN revenue_types r ON t.revenue_type_id = r.revenue_type_id 
          WHERE t.transaction_ref = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $transaction_ref);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode(['success' => true, 'transaction_details' => $row]);
} else {
    echo json_encode(['success' => false, 'message' => 'Transaction not found.']);
}


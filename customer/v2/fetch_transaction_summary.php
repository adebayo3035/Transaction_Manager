<?php
include 'config.php';
session_start();
logActivity("Starting Session validation for User");


// Check if 'customer_id' is set in session
$customerId = $_SESSION["customer_id"] ?? null;
checkSession($customerId);

logActivity("Session validated for customer ID: $customerId");

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = ($page - 1) * $limit;
logActivity("Pagination parameters - Page: $page, Limit: $limit, Offset: $offset");

// Fetch total count of customer transactions
$totalQuery = "SELECT COUNT(*) as total FROM customer_transactions WHERE customer_id = ?";
logActivity("Executing total count query: $totalQuery");
$stmt = $conn->prepare($totalQuery);
if (!$stmt) {
    logActivity("Failed to prepare total count statement: " . $conn->error);
    echo json_encode(["success" => false, "message" => "Database error."]);
    exit();
}
$stmt->bind_param("i", $customerId);
$stmt->execute();
$totalResult = $stmt->get_result();
$totalTransactions = $totalResult->fetch_assoc()['total'];
$stmt->close();
logActivity("Total transactions count: $totalTransactions");

// Fetch paginated customer transactions
$query = "SELECT id, transaction_ref, amount, date_created, transaction_type, payment_method, description FROM customer_transactions WHERE customer_id = ? ORDER BY date_created DESC LIMIT ? OFFSET ?";
logActivity("Executing transaction fetch query: $query");
$stmt = $conn->prepare($query);
if (!$stmt) {
    logActivity("Failed to prepare transaction fetch statement: " . $conn->error);
    echo json_encode(["success" => false, "message" => "Database error."]);
    exit();
}
$stmt->bind_param("iii", $customerId, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$transactions = [];
while ($row = $result->fetch_assoc()) {
    $transactions[] = $row;
}
$stmt->close();
$conn->close();
logActivity("Fetched transactions: " . json_encode($transactions));

// Return response with transactions
$response = [
    "success" => true,
    "transactions" => $transactions,
    "total" => $totalTransactions,
    "page" => $page,
    "limit" => $limit
];
logActivity("Response sent: " . json_encode($response));
echo json_encode($response);

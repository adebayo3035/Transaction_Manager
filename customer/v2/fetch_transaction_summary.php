<?php
include 'config.php';
session_start();

if (!isset($_SESSION['customer_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}

$customerId = $_SESSION['customer_id'];
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = ($page - 1) * $limit;

// Fetch total count of orders for the customer
$totalQuery = "SELECT COUNT(*) as total FROM customer_transactions WHERE customer_id = ?";
$stmt = $conn->prepare($totalQuery);
$stmt->bind_param("i", $customerId);
$stmt->execute();
$totalResult = $stmt->get_result();
$totalTransactions = $totalResult->fetch_assoc()['total'];

// Fetch paginated orders
$query = "SELECT id, amount, date_created, transaction_type, payment_method, description FROM customer_transactions WHERE customer_id = ? ORDER BY date_created DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("iii", $customerId, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$orders = [];
while ($row = $result->fetch_assoc()) {
    $transactions[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode([
    "success" => true,
    "transactions" => $transactions,
    "total" => $totalTransactions,
    "page" => $page,
    "limit" => $limit
]);


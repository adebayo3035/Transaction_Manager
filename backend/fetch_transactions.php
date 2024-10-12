<?php
// Include database connection
include('config.php');

// Get pagination parameters
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$offset = ($page - 1) * $limit;

// Fetch total number of transactions
$totalQuery = "SELECT COUNT(*) as total FROM transactions";
$totalResult = $conn->query($totalQuery);
$totalRow = $totalResult->fetch_assoc();
$total = $totalRow['total'];

// Fetch transactions
$query = "SELECT transaction_ref, transaction_type, amount, payment_method, status, transaction_date FROM transactions ORDER BY transaction_date DESC LIMIT ?, ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $offset, $limit);
$stmt->execute();
$result = $stmt->get_result();

$transactions = [];
while ($row = $result->fetch_assoc()) {
    $transactions[] = $row;
}

echo json_encode([
    'success' => true,
    'transactions' => $transactions,
    'total' => $total,
    'page' => $page,
    'limit' => $limit
]);


<?php
// Include database connection
include('config.php');

// Get pagination parameters
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$offset = ($page - 1) * $limit;

// Fetch total number of revenue
$totalQuery = "SELECT COUNT(*) as total FROM revenue";
$totalResult = $conn->query($totalQuery);
$totalRow = $totalResult->fetch_assoc();
$total = $totalRow['total'];

// Fetch revenue
$query = "SELECT revenue_id, order_id, customer_id, total_amount, status, transaction_date, updated_at FROM revenue ORDER BY updated_at DESC LIMIT ?, ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $offset, $limit);
$stmt->execute();
$result = $stmt->get_result();

$revenues = [];
while ($row = $result->fetch_assoc()) {
    $revenues[] = $row;
}

echo json_encode([
    'success' => true,
    'revenues' => $revenues,
    'total' => $total,
    'page' => $page,
    'limit' => $limit
]);


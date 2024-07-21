<?php
header('Content-Type: application/json');
include 'config.php';

$totalRevenueQuery = "SELECT SUM(total_amount) as totalRevenue FROM revenue WHERE status = 'Approved'";
$totalRevenueResult = $conn->query($totalRevenueQuery);
$totalRevenue = $totalRevenueResult->fetch_assoc()['totalRevenue'];

$pendingOrdersQuery = "SELECT COUNT(*) as pendingOrders FROM revenue WHERE status = 'Pending'";
$pendingOrdersResult = $conn->query($pendingOrdersQuery);
$pendingOrders = $pendingOrdersResult->fetch_assoc()['pendingOrders'];

$approvedOrdersQuery = "SELECT COUNT(*) as approvedOrders FROM revenue WHERE status = 'Approved'";
$approvedOrdersResult = $conn->query($approvedOrdersQuery);
$approvedOrders = $approvedOrdersResult->fetch_assoc()['approvedOrders'];

$declinedOrdersQuery = "SELECT COUNT(*) as declinedOrders FROM revenue WHERE status = 'Declined'";
$declinedOrdersResult = $conn->query($declinedOrdersQuery);
$declinedOrders = $declinedOrdersResult->fetch_assoc()['declinedOrders'];

$recentTransactionsQuery = "SELECT * FROM revenue ORDER BY transaction_date DESC LIMIT 10";
$recentTransactionsResult = $conn->query($recentTransactionsQuery);
$recentTransactions = [];
while($row = $recentTransactionsResult->fetch_assoc()) {
    $recentTransactions[] = $row;
}

$response = [
    'totalRevenue' => $totalRevenue,
    'pendingOrders' => $pendingOrders,
    'approvedOrders' => $approvedOrders,
    'declinedOrders' => $declinedOrders,
    'recentTransactions' => $recentTransactions
];

echo json_encode($response);

$conn->close();


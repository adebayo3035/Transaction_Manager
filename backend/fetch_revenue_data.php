<?php
header('Content-Type: application/json');
include 'config.php';

$totalRevenueQuery = "
    SELECT SUM(total_amount) AS totalRevenue 
    FROM revenue 
    WHERE status IN ('Approved', 'Order Cancellation Fee')
";

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

$transitOrdersQuery = "SELECT COUNT(*) as transitOrders FROM orders WHERE delivery_status = 'In Transit'";
$transitOrdersResult = $conn->query($transitOrdersQuery);
$transitOrders = $transitOrdersResult->fetch_assoc()['transitOrders'];

$deliveredOrdersQuery = "SELECT COUNT(*) as deliveredOrders FROM orders WHERE delivery_status = 'Delivered'";
$deliveredOrdersResult = $conn->query($deliveredOrdersQuery);
$deliveredOrders = $deliveredOrdersResult->fetch_assoc()['deliveredOrders'];

$cancelledOrdersQuery = "SELECT COUNT(*) as cancelledOrders FROM orders WHERE delivery_status = 'Cancelled'";
$cancelledOrdersResult = $conn->query($cancelledOrdersQuery);
$cancelledOrders = $cancelledOrdersResult->fetch_assoc()['cancelledOrders'];

$assignedOrdersQuery = "SELECT COUNT(*) as assignedOrders FROM orders WHERE delivery_status = 'Assigned'";
$assignedOrdersResult = $conn->query($assignedOrdersQuery);
$assignedOrders = $assignedOrdersResult->fetch_assoc()['assignedOrders'];


$recentTransactionsQuery = "
    SELECT 
        r.*, 
        o.delivery_status, 
        d.firstname, 
        d.lastname 
    FROM 
        revenue r
    JOIN 
        orders o ON r.order_id = o.order_id
    LEFT JOIN 
        driver d ON o.driver_id = d.id
    ORDER BY 
        r.transaction_date DESC 
    LIMIT 10";

$recentTransactionsResult = $conn->query($recentTransactionsQuery);
$recentTransactions = [];

if ($recentTransactionsResult->num_rows > 0) {
    while ($row = $recentTransactionsResult->fetch_assoc()) {
        $recentTransactions[] = $row;
    }
}


$response = [
    'totalRevenue' => $totalRevenue,
    'pendingOrders' => $pendingOrders,
    'approvedOrders' => $approvedOrders,
    'declinedOrders' => $declinedOrders,
    'transitOrders' => $transitOrders,
    'deliveredOrders' => $deliveredOrders,
    'cancelledOrders' => $cancelledOrders,
    'assignedOrders' => $assignedOrders,
    'recentTransactions' => $recentTransactions
];

echo json_encode($response);

$conn->close();


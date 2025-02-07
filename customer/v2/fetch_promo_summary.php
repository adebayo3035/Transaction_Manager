<?php
include 'config.php';
session_start();

logActivity("Starting session check for User");
// Check if either 'unique_id' (for admin) or 'customer_id' (for customer) is set
$customerId = $_SESSION["customer_id"] ?? null;
checkSession($customerId);

logActivity("Session validated for customer ID: $customerId");

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = ($page - 1) * $limit;
logActivity("Pagination parameters - Page: $page, Limit: $limit, Offset: $offset");

// Fetch total count of promos
$totalQuery = "SELECT COUNT(*) as total FROM promo WHERE delete_id = 0";
logActivity("Executing total count query: $totalQuery");
$stmt = $conn->prepare($totalQuery);
if (!$stmt) {
    logActivity("Failed to prepare total count statement: " . $conn->error);
    echo json_encode(["success" => false, "message" => "Database error."]);
    exit();
}
$stmt->execute();
$totalResult = $stmt->get_result();
$total = $totalResult->fetch_assoc()['total'];
$stmt->close();
logActivity("Total promos count: $total");

// Fetch paginated promos
$query = "SELECT * FROM promo WHERE status = 1 AND NOW() BETWEEN start_date AND end_date AND delete_id = 0 ORDER BY date_last_modified DESC, status DESC LIMIT ? OFFSET ?";
logActivity("Executing promo fetch query: $query");
$stmt = $conn->prepare($query);
if (!$stmt) {
    logActivity("Failed to prepare promo fetch statement: " . $conn->error);
    echo json_encode(["success" => false, "message" => "Database error."]);
    exit();
}
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$promos = [];
while ($row = $result->fetch_assoc()) {
    $promos[] = $row;
}
$stmt->close();
logActivity("Fetched promos: " . json_encode($promos));

// Return both paginated and ongoing promos
$response = [
    "success" => true,
    "promos" => $promos,
    "total" => $total,
    "page" => $page,
    "limit" => $limit
];
logActivity("Response sent: " . json_encode($response));
echo json_encode($response);
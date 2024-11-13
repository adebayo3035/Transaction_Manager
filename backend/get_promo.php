<?php
include 'config.php';
session_start();
// Check if either 'unique_id' (for admin) or 'customer_id' (for customer) is set
if (!isset($_SESSION['unique_id']) && !isset($_SESSION['customer_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = ($page - 1) * $limit;

// Fetch total count of promos
$totalQuery = "SELECT COUNT(*) as total FROM promo WHERE delete_id = 0";
$stmt = $conn->prepare($totalQuery);
$stmt->execute();
$totalResult = $stmt->get_result();
$totalPromos = $totalResult->fetch_assoc()['total'];
$stmt->close();

// Fetch paginated promos
$query = "SELECT * FROM promo WHERE delete_id = 0 ORDER BY date_last_modified DESC, status DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$promos = [];
while ($row = $result->fetch_assoc()) {
    $promos[] = $row;
}
$stmt->close();

// Fetch ongoing promos
$now = date('Y-m-d H:i:s');
$ongoingQuery = "SELECT promo_id, promo_name, promo_code, promo_description, discount_type, discount_value, max_discount
                 FROM promo 
                 WHERE status = 1 AND delete_id = 0 AND start_date <= ? AND end_date >= ? 
                 ORDER BY date_last_modified DESC";
$stmt = $conn->prepare($ongoingQuery);
$stmt->bind_param("ss", $now, $now);
$stmt->execute();
$ongoingResult = $stmt->get_result();

$ongoingPromos = [];
while ($row = $ongoingResult->fetch_assoc()) {
    $ongoingPromos[] = $row;
}

$stmt->close();
$conn->close();

// Return both paginated and ongoing promos
echo json_encode([
    "success" => true,
    "promos" => [
        "all" => $promos,
        "ongoing" => $ongoingPromos
    ],
    "total" => $totalPromos,
    "page" => $page,
    "limit" => $limit
]);

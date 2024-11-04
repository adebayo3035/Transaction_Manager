<?php
include 'config.php';
session_start();

if (!isset($_SESSION['unique_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}

$adminId = $_SESSION['unique_id'];
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = ($page - 1) * $limit;

// Fetch total count of drivers
$totalQuery = "SELECT COUNT(*) as total FROM promo";
$stmt = $conn->prepare($totalQuery);
$stmt->execute();
$totalResult = $stmt->get_result();
$totalPromos = $totalResult->fetch_assoc()['total'];

// Fetch paginated drivers
$query = "SELECT * FROM promo WHERE delete_id = 0 ORDER BY date_last_modified DESC, status desc LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$promos = [];
while ($row = $result->fetch_assoc()) {
    $promos[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode([
    "success" => true,
    "promos" => $promos,
    "total" => $totalPromos,
    "page" => $page,
    "limit" => $limit
]);


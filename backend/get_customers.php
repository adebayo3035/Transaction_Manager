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
$totalQuery = "SELECT COUNT(*) as total FROM customers";
$stmt = $conn->prepare($totalQuery);
$stmt->execute();
$totalResult = $stmt->get_result();
$totalCustomers = $totalResult->fetch_assoc()['total'];

// Fetch paginated drivers
$query = "SELECT * FROM customers ORDER BY date_updated DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$customers = [];
while ($row = $result->fetch_assoc()) {
    $customers[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode([
    "success" => true,
    "customers" => $customers,
    "total" => $totalCustomers,
    "page" => $page,
    "limit" => $limit
]);


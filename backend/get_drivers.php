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
$totalQuery = "SELECT COUNT(*) as total FROM driver";
$stmt = $conn->prepare($totalQuery);
$stmt->execute();
$totalResult = $stmt->get_result();
$totalDrivers = $totalResult->fetch_assoc()['total'];

// Fetch paginated drivers
$query = "SELECT * FROM driver ORDER BY date_updated DESC, status desc LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$drivers = [];
while ($row = $result->fetch_assoc()) {
    $drivers[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode([
    "success" => true,
    "drivers" => $drivers,
    "total" => $totalDrivers,
    "page" => $page,
    "limit" => $limit
]);


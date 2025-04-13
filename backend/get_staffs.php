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
$loggedInUserRole = $_SESSION['role'];

// Fetch total count of drivers
$totalQuery = "SELECT COUNT(*) as total FROM admin_tbl";
$stmt = $conn->prepare($totalQuery);
$stmt->execute();
$totalResult = $stmt->get_result();
$totalStaffs = $totalResult->fetch_assoc()['total'];

// Fetch paginated drivers
// $query = "SELECT * FROM admin_tbl  ORDER BY updated_at DESC LIMIT ? OFFSET ?";
$query = "SELECT 
    admin_tbl.*, 
    admin_active_sessions.status as admin_status
FROM 
    admin_tbl
LEFT JOIN 
    admin_active_sessions 
ON 
    admin_tbl.unique_id = admin_active_sessions.unique_id 
WHERE 
    admin_tbl.delete_status != 'Yes' 
    OR admin_tbl.delete_status IS NULL
ORDER BY 
    admin_tbl.updated_at DESC 
LIMIT ? 
OFFSET ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$staffs = [];
while ($row = $result->fetch_assoc()) {
    $staffs[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode([
    "success" => true,
    "staffs" => $staffs,
    "total" => $totalStaffs,
    "page" => $page,
    "limit" => $limit,
    'logged_in_user_role' => $loggedInUserRole
]);


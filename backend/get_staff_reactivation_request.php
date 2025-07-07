<?php
session_start();
require 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['unique_id']) || $_SESSION['role'] !== 'Super Admin') {
    $userId = $_SESSION['unique_id'] ?? 'Guest';
    logActivity("Unauthorized attempt by user ID: {$userId} to access deleted staff records.");
    http_response_code(403);
    echo json_encode(["error" => "Access denied. Only Super Admins can view deleted staff."]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

$admin_id = $_SESSION['unique_id'];
logActivity("Super Admin ID {$admin_id} is retrieving deleted staff records.");

try {
    // Pagination
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
    $offset = ($page - 1) * $limit;

    // Total count
    $countQuery = "
    SELECT COUNT(*) AS total 
    FROM admin_tbl a
    INNER JOIN admin_deactivation_logs d ON a.unique_id = d.admin_id
    LEFT JOIN admin_reactivation_logs l ON d.id = l.deactivation_log_id
    WHERE a.delete_status = 'Yes' AND l.status = 'Pending'
";
    $stmt = $conn->prepare($countQuery);
    $stmt->execute();
    $result = $stmt->get_result();
    $totalRow = $result->fetch_assoc();
    $total = $totalRow['total'];
    $totalPages = ceil($total / $limit);

    // Fetch records
    $query = "
        SELECT 
    a.unique_id AS staff_id,
    a.firstname AS staff_firstname,
    a.lastname AS staff_lastname,
    l.status AS reactivation_status,
    l.id AS reactivation_id,
    d.date_created AS date_deactivated,
    d.deactivated_by,
    deactivator.firstname AS deactivated_by_firstname,
    deactivator.lastname AS deactivated_by_lastname
FROM admin_tbl a
INNER JOIN admin_deactivation_logs d ON a.unique_id = d.admin_id
LEFT JOIN admin_reactivation_logs l ON d.id = l.deactivation_log_id
LEFT JOIN admin_tbl deactivator ON d.deactivated_by = deactivator.unique_id
WHERE a.delete_status = 'Yes' AND l.status = 'Pending'
ORDER BY d.date_created DESC
LIMIT ? OFFSET ?
";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $deletedStaff = [];
    while ($row = $result->fetch_assoc()) {
        $deletedStaff[] = [
            'staff_id' => $row['staff_id'],
            'firstname' => $row['staff_firstname'],
            'lastname' => $row['staff_lastname'],
            'reactivation_status' => $row['reactivation_status'] ?? 'No Request',
            'reactivation_id' => $row['reactivation_id'],
            'date_deactivated' => $row['date_deactivated'],
            'deactivated_by' => [
                'admin_id' => $row['deactivated_by'],
                'name' => $row['deactivated_by_firstname'] . ' ' . $row['deactivated_by_lastname']
            ]
        ];
    }

    logActivity("Super Admin ID: $admin_id fetched deleted staff list.");
    echo json_encode([
        'success' => true,
        'message' => 'Deactivated Staff Account records fetched successfully',
        'staffData' => $deletedStaff,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'totalPages' => $totalPages
    ]);
} catch (Exception $e) {
    logActivity("Error fetching deleted staff by Admin ID: $admin_id - " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Internal Server Error"]);
}

$conn->close();

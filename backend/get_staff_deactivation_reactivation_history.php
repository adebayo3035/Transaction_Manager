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

   // Corrected count query (matches main query logic)
$countQuery = "
    SELECT COUNT(*) AS total 
    FROM admin_deactivation_logs d
    INNER JOIN admin_tbl a ON d.admin_id = a.unique_id
";

// Execute count query
$stmt = $conn->prepare($countQuery);
$stmt->execute();
$result = $stmt->get_result();
$totalRow = $result->fetch_assoc();
$total = $totalRow['total'];

    // Fetch records
   $query = "
    SELECT 
        d.id AS deactivation_id,
        d.date_created AS deactivation_date,
        d.reason AS deactivation_reason,
        d.deactivated_by,
        deactivator.firstname AS deactivated_by_firstname,
        deactivator.lastname AS deactivated_by_lastname,
        
        a.unique_id AS staff_id,
        a.firstname AS staff_firstname,
        a.lastname AS staff_lastname,
        a.email AS staff_email,
        a.phone AS staff_phone,
        
        COALESCE(l.status, 'No Reactivation Request') AS reactivation_status,
        l.date_created AS reactivation_date,
        l.date_last_updated as date_last_updated,
        l.reactivated_by as reactivated_by,
        reactivator.firstname AS reactivated_by_firstname,
        reactivator.lastname AS reactivated_by_lastname
        
    FROM admin_deactivation_logs d
    INNER JOIN admin_tbl a ON d.admin_id = a.unique_id
    LEFT JOIN admin_reactivation_logs l ON d.id = l.deactivation_log_id
    LEFT JOIN admin_tbl deactivator ON d.deactivated_by = deactivator.unique_id
    LEFT JOIN admin_tbl reactivator ON l.reactivated_by = reactivator.unique_id
    ORDER BY d.id DESC, l.date_last_updated DESC
    LIMIT ? OFFSET ?
";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $deletedStaff = [];
    $deletedStaff = [];
while ($row = $result->fetch_assoc()) {
    $deletedStaff[] = [
        'deactivation_id' => $row['deactivation_id'],
        'deactivation_date' => $row['deactivation_date'],
        'deactivation_reason' => $row['deactivation_reason'] ?? null,
        
        'staff' => [
            'staff_id' => $row['staff_id'],
            'firstname' => $row['staff_firstname'],
            'lastname' => $row['staff_lastname'],
            'email' => $row['staff_email'],
            'phone' => $row['staff_phone']
        ],
        
        'reactivation_status' => $row['reactivation_status'], // Already has COALESCE in query
        'reactivation_date' => $row['reactivation_date'] ?? null,
        'date_last_updated' => $row['date_last_updated'] ?? null,
        
        'deactivated_by' => [
            'admin_id' => $row['deactivated_by'],
            'name' => trim($row['deactivated_by_firstname'] . ' ' . $row['deactivated_by_lastname'])
        ],
        
        'reactivated_by' => [
            'admin_id' => $row['reactivated_by'] ?? null,
            'name' => isset($row['reactivated_by_firstname']) 
                     ? trim($row['reactivated_by_firstname'] . ' ' . $row['reactivated_by_lastname'])
                     : null
        ]
    ];
}

    logActivity("Super Admin ID: $admin_id fetched deleted staff list.");
    echo json_encode([
        'success' => true,
        'message' => 'Deactivated Staff Account records fetched successfully',
        'deletedStaff' => $deletedStaff,
        'total' => $total,
        'page' => $page,
        'limit' => $limit
    ]);
} catch (Exception $e) {
    logActivity("Error fetching deleted staff by Admin ID: $admin_id - " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Internal Server Error"]);
}

$conn->close();

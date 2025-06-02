<?php
session_start();
require 'config.php';
header('Content-Type: application/json');

// Verify admin is logged in
if (!isset($_SESSION['unique_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized access"]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

$admin_id = $_SESSION['unique_id'];
logActivity("Customer ID {$admin_id} accessing reactivation history");

try {
    // Pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = ($page - 1) * $limit;

    // Count query
    $countQuery = "SELECT COUNT(*) AS total FROM admin_reactivation_logs WHERE admin_id = ?";
    $stmt = $conn->prepare($countQuery);
    $stmt->bind_param("s", $admin_id);
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];

    // Fetch reactivation records
    $query = "
        SELECT 
            r.id AS reactivation_id,
            r.date_created AS reactivation_date,
            r.reactivation_reason,
            r.status,
            r.date_last_updated,
            r.reactivated_by,
            a.firstname AS admin_firstname,
            a.lastname AS admin_lastname,
            d.reason AS deactivation_reason,
            d.date_created AS deactivation_date
        FROM admin_reactivation_logs r
        LEFT JOIN admin_deactivation_logs d ON r.deactivation_log_id = d.id
        LEFT JOIN admin_tbl a ON r.reactivated_by = a.unique_id
        WHERE r.admin_id = ?
        ORDER BY r.date_created DESC
        LIMIT ? OFFSET ?
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("sii", $admin_id, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = [
            'reactivation_id' => $row['reactivation_id'],
            'date' => $row['reactivation_date'],
            'status' => $row['status'],
            'last_updated' => $row['date_last_updated'],
            'reason' => $row['reactivation_reason'],
            'deactivation_details' => [
                'reason' => $row['deactivation_reason'],
                'date' => $row['deactivation_date']
            ],
            'processed_by' => $row['reactivated_by'] ? [
                'admin_id' => $row['reactivated_by'],
                'name' => trim($row['admin_firstname'] . ' ' . $row['admin_lastname'])
            ] : null
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $history,
        'pagination' => [
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ]
    ]);

} catch (Exception $e) {
    logActivity("Error fetching admin reactivation history: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Internal Server Error"]);
}
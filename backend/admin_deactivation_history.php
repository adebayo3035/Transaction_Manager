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
logActivity("Admin ID {$admin_id} accessing deactivation history");

try {
    // Pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = ($page - 1) * $limit;

    // Count query
    $countQuery = "SELECT COUNT(*) AS total FROM admin_deactivation_logs WHERE admin_id = ?";
    $stmt = $conn->prepare($countQuery);
    $stmt->bind_param("s", $admin_id);
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];

    // Fetch deactivation records
    $query = "
        SELECT 
            d.id AS deactivation_id,
            d.date_created AS deactivation_date,
            d.reason AS deactivation_reason,
            d.deactivated_by,
            d.status,
            a.firstname AS admin_firstname,
            a.lastname AS admin_lastname
        FROM admin_deactivation_logs d
        LEFT JOIN admin_tbl a ON d.deactivated_by = a.unique_id
        WHERE d.admin_id = ?
        ORDER BY d.date_created DESC
        LIMIT ? OFFSET ?
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("sii", $admin_id, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = [
            'deactivation_id' => $row['deactivation_id'],
            'date' => $row['deactivation_date'],
            'reason' => $row['deactivation_reason'],
            'status' => $row['status'],
            'deactivated_by' => [
                'admin_id' => $row['deactivated_by'],
                'name' => trim($row['admin_firstname'] . ' ' . $row['admin_lastname'])
            ]
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
    logActivity("Error fetching admin deactivation history: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Internal Server Error"]);
}
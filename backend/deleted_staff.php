<?php
header('Content-Type: application/json');
require_once 'config.php'; // include DB connection and logActivity()
session_start();
$admin_id = $_SESSION['unique_id'];

try {
    logActivity("REACTIVATION_FETCH_START: Starting fetch for pending deleted staff");

    $conn->begin_transaction();

    // Get pagination parameters
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
    $offset = ($page - 1) * $limit;

    // Count total records
    $countQuery = "
    SELECT COUNT(*) AS total 
    FROM admin_tbl a
    INNER JOIN account_reactivation_requests r 
        ON a.unique_id = r.user_id
    WHERE 
        a.delete_status = 'Yes' 
        AND r.status IN ('Pending', 'Rejected')
        AND r.assigned_to = ?
";

    $stmt = $conn->prepare($countQuery);
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $totalRow = $result->fetch_assoc();
    $total = $totalRow['total'];

    // Fetch paginated records
    // Add assigned_admin_id condition to the WHERE clause
$query = "
SELECT 
    a.unique_id AS staff_id,
    a.firstname,
    a.lastname,
    r.status,
    r.requested_at AS reactivation_date
FROM 
    admin_tbl a
INNER JOIN 
    account_reactivation_requests r 
    ON a.unique_id = r.user_id
WHERE 
    a.delete_status = 'Yes'
    AND r.status IN ('Pending', 'Rejected')
    AND r.assigned_to = ?
ORDER BY 
    r.requested_at DESC
LIMIT ? OFFSET ?
";

logActivity("REACTIVATION_FETCH_QUERY: $query");

// Use 'iii' because there are now 3 integer parameters
$stmt = $conn->prepare($query);
$stmt->bind_param('iii', $admin_id, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

    $staffData = [];
    while ($row = $result->fetch_assoc()) {
        $staffData[] = [
            'staff_id' => $row['staff_id'],
            'firstname' => $row['firstname'],
            'lastname' => $row['lastname'],
            'status' => $row['status'],
            'reactivation_request_date' => $row['reactivation_date']
        ];
    }

    logActivity("REACTIVATION_FETCH_RESULT_COUNT: " . count($staffData));
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Pending deactivated staff reactivation requests fetched successfully',
        'staffData' => $staffData,
        'total' => $total,
        'page' => $page,
        'limit' => $limit
    ]);

} catch (Exception $e) {
    $conn->rollback();
    logActivity("REACTIVATION_FETCH_ERROR: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch reactivation requests',
        'error' => $e->getMessage()
    ]);
}

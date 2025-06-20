<?php
include('config.php');
session_start();
header('Content-Type: application/json');

// === Validate session ===
if (!isset($_SESSION['driver_id'])) {
    logActivity("Access Denied: No driver session found.");
    echo json_encode(['success' => false, 'message' => 'Access Denied. Please log in first.']);
    exit;
}

$driverId = $_SESSION['driver_id'];
if (!is_numeric($driverId)) {
    logActivity("Invalid driver_id in session: '$driverId'");
    echo json_encode(['success' => false, 'message' => 'Invalid session data.']);
    exit;
}

// === Pagination Parameters ===
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int) $_GET['limit'] : 10;
$offset = ($page - 1) * $limit;

logActivity("Fetching UNRESTRICT logs for Driver ID $driverId | Page $page | Limit $limit");

try {
    // Count total
    $countQuery = "SELECT COUNT(*) FROM account_restriction_audit_log WHERE account_type = 'DRIVER' AND account_id = ? AND action_type = 'UNRESTRICT'";
    $countStmt = $conn->prepare($countQuery);
    $countStmt->bind_param("i", $driverId);
    $countStmt->execute();
    $countStmt->bind_result($totalRecords);
    $countStmt->fetch();
    $countStmt->close();

    // Main paginated query
    $query = "
        SELECT 
            ar.reference_id,
            ar.account_id,
            ar.account_type,
            ar.action_type,
            ar.created_at,
            CONCAT(ad.firstname, ' ', ad.lastname) AS initiator
        FROM account_restriction_audit_log ar
        LEFT JOIN admin_tbl ad ON ar.initiated_by = ad.unique_id
        WHERE ar.account_type = 'DRIVER' 
          AND ar.account_id = ?
          AND ar.action_type = 'UNRESTRICT'
        ORDER BY ar.id DESC
        LIMIT ?, ?
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $driverId, $offset, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $records = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    logActivity("Fetched " . count($records) . " UNRESTRICTED records out of $totalRecords for Driver ID $driverId");

    echo json_encode([
        'success' => true,
        'records' => $records,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $totalRecords,
            'pages' => ceil($totalRecords / $limit)
        ]
    ]);

} catch (Exception $e) {
    logActivity("Error fetching UNRESTRICT logs for Driver ID $driverId: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error fetching data.']);
} finally {
    $conn->close();
}

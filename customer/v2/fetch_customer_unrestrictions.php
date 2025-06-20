<?php
include('config.php');
session_start();
header('Content-Type: application/json');

// === Validate session ===
if (!isset($_SESSION['customer_id'])) {
    logActivity("Access Denied: No customer session found.");
    echo json_encode(['success' => false, 'message' => 'Access Denied. Please log in first.']);
    exit;
}

$customerId = $_SESSION['customer_id'];

if (!is_numeric($customerId)) {
    logActivity("Invalid customer_id in session: '$customerId'");
    echo json_encode(['success' => false, 'message' => 'Invalid session data.']);
    exit;
}

// === Step 1: Get internal `id` from customers table using customer_id ===
$getIdQuery = "SELECT id FROM customers WHERE customer_id = ?";
$getIdStmt = $conn->prepare($getIdQuery);
$getIdStmt->bind_param("i", $customerId);
$getIdStmt->execute();
$getIdStmt->bind_result($internalId);
$getIdStmt->fetch();
$getIdStmt->close();

if (empty($internalId)) {
    logActivity("No matching customer found for customer_id: $customerId");
    echo json_encode(['success' => false, 'message' => 'Customer not found.']);
    exit;
}

logActivity("Resolved customer_id $customerId to internal ID $internalId");

// === Pagination Parameters ===
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int) $_GET['limit'] : 10;
$offset = ($page - 1) * $limit;

logActivity("Fetching UNRESTRICT logs for Customer internal ID $internalId | Page $page | Limit $limit");

try {
    // Count total
    $countQuery = "SELECT COUNT(*) FROM account_restriction_audit_log WHERE account_type = 'CUSTOMERS' AND account_id = ? AND action_type = 'UNRESTRICT'";
    $countStmt = $conn->prepare($countQuery);
    $countStmt->bind_param("i", $internalId);
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
        WHERE ar.account_type = 'CUSTOMERS' 
          AND ar.account_id = ?
          AND ar.action_type = 'UNRESTRICT'
        ORDER BY ar.id DESC
        LIMIT ?, ?
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $internalId, $offset, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $records = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    logActivity("Fetched " . count($records) . " UNRESTRICT records out of $totalRecords for customer ID $customerId (internal ID: $internalId)");

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
    logActivity("Error fetching UNRESTRICT logs for customer ID $customerId: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error fetching data.']);
} finally {
    $conn->close();
}

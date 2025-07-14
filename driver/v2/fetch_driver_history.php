<?php
include('config.php');
session_start();
header('Content-Type: application/json');

// === Validate session ===
if (!isset($_SESSION['driver_id'])) {
    logActivity("DRIVER_API_ACCESS_DENIED: No session found for driver from IP " . $_SERVER['REMOTE_ADDR']);
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Access Denied. Please log in first.']));
}

$driverId = (int)$_SESSION['driver_id'];
if ($driverId <= 0) {
    logActivity("DRIVER_API_INVALID_SESSION: Invalid driver_id in session - " . ($_SESSION['driver_id'] ?? 'NULL'));
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Invalid session data.']));
}

// === Input Validation ===
$input = json_decode(file_get_contents("php://input"), true) ?? [];
$actionType = strtoupper(trim($input['actionType'] ?? ''));
$page = max(1, (int)($input['page'] ?? 1));
$limit = max(1, min(100, (int)($input['limit'] ?? 10)));
$offset = ($page - 1) * $limit;

$validActions = ['RESTRICT', 'UNRESTRICT', 'DEACTIVATE', 'REACTIVATE'];
$validTables = [
    'RESTRICT' => 'account_restriction_audit_log',
    'UNRESTRICT' => 'account_restriction_audit_log',
    'DEACTIVATE' => 'account_deactivation_audit_log',
    'REACTIVATE' => 'account_deactivation_audit_log'
];

logActivity("DRIVER_API_REQUEST: Driver $driverId requesting $actionType logs (Page: $page, Limit: $limit)");

if (!array_key_exists($actionType, $validTables)) {
    logActivity("DRIVER_API_INVALID_ACTION: Invalid action type '$actionType' requested by driver $driverId");
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Invalid action type.']));
}

$table = $validTables[$actionType];
logActivity("DRIVER_API_TABLE_SELECTED: Using table '$table' for action type '$actionType'");

// Initialize all statement variables
$countStmt = $stmt = null;

try {
    // === Count Query ===
    $countQuery = "SELECT COUNT(*) FROM `$table` WHERE account_id = ? AND account_type = 'DRIVER' AND action_type = ?";
    logActivity("DRIVER_API_COUNT_QUERY: Preparing count query - " . str_replace(["\n", "  "], " ", $countQuery));
    
    $countStmt = $conn->prepare($countQuery);
    if (!$countStmt) {
        logActivity("DRIVER_API_COUNT_PREPARE_FAIL: " . $conn->error);
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    if (!$countStmt->bind_param("is", $driverId, $actionType)) {
        logActivity("DRIVER_API_COUNT_BIND_FAIL: Driver $driverId, Action $actionType");
        throw new Exception("Count query bind failed");
    }
    
    if (!$countStmt->execute()) {
        logActivity("DRIVER_API_COUNT_EXECUTE_FAIL: " . $countStmt->error);
        throw new Exception("Count query execute failed: " . $countStmt->error);
    }
    
    $countStmt->bind_result($totalRecords);
    $countStmt->fetch();
    $countStmt->close();
    
    logActivity("DRIVER_API_COUNT_RESULT: Found $totalRecords records for driver $driverId");

    // === Main Query ===
    $query = "
        SELECT 
            ar.reference_id,  
            ar.account_id,
            ar.account_type,
            ar.action_type,
            ar.created_at,
            CONCAT(ad.firstname, ' ', ad.lastname) AS initiator
        FROM `$table` ar
        LEFT JOIN admin_tbl ad ON ar.initiated_by = ad.unique_id
        WHERE ar.account_id = ?
          AND ar.account_type = 'DRIVER'
          AND ar.action_type = ?
        ORDER BY ar.id DESC
        LIMIT ?, ?
    ";
    
    logActivity("DRIVER_API_MAIN_QUERY: Preparing main query - " . str_replace(["\n", "  "], " ", $query));
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        logActivity("DRIVER_API_MAIN_PREPARE_FAIL: " . $conn->error);
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    if (!$stmt->bind_param("isii", $driverId, $actionType, $offset, $limit)) {
        logActivity("DRIVER_API_MAIN_BIND_FAIL: Driver $driverId, Action $actionType, Offset $offset, Limit $limit");
        throw new Exception("Main query bind failed");
    }
    
    if (!$stmt->execute()) {
        logActivity("DRIVER_API_MAIN_EXECUTE_FAIL: " . $stmt->error);
        throw new Exception("Main query execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $records = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    $recordCount = count($records);
    logActivity("DRIVER_API_RECORDS_FETCHED: Retrieved $recordCount records for driver $driverId");

    if ($recordCount === 0) {
        logActivity("DRIVER_API_NO_RECORDS: No records found for driver $driverId, action $actionType");
        echo json_encode([
            'success' => true,
            'message' => 'No records found',
            'records' => [],
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $totalRecords,
                'pages' => ceil($totalRecords / $limit)
            ]
        ]);
        exit;
    }

    // Mask reference_id for each record
    $maskedRecords = array_map(function($record) use ($driverId) {
        if (isset($record['reference_id']) && strlen($record['reference_id']) > 8) {
            $ref = $record['reference_id'];
            $record['reference_id'] = substr($ref, 0, 4) . '****' . substr($ref, -4);
            logActivity("DRIVER_API_REFERENCE_MASKED: Masked reference ID for driver $driverId - Original: $ref");
        }
        return $record;
    }, $records);
    
    logActivity("DRIVER_API_RESPONSE_PREPARED: Preparing response for driver $driverId with $recordCount records");
    
    echo json_encode([
        'success' => true,
        'records' => $maskedRecords,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $totalRecords,
            'pages' => ceil($totalRecords / $limit)
        ]
    ]);

} catch (Exception $e) {
    logActivity("DRIVER_API_ERROR: Driver $driverId - " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching data.',
        'error' => $e->getMessage() // Remove in production
    ]);
} finally {
    try {
        // if ($countStmt instanceof mysqli_stmt) $countStmt->close();
        // if ($stmt instanceof mysqli_stmt) $stmt->close();
        // if ($conn instanceof mysqli) $conn->close();
        $conn->close();
        logActivity("DRIVER_API_RESOURCES_CLEANED: Database resources released for driver $driverId");
    } catch (Exception $e) {
        logActivity("DRIVER_API_CLEANUP_ERROR: Resource cleanup failed - " . $e->getMessage());
    }
}
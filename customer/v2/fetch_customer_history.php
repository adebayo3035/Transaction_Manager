<?php
include('config.php');
session_start();
header('Content-Type: application/json');

// === Validate session ===
if (!isset($_SESSION['customer_id'])) {
    logActivity("Customer API access denied - no session found");
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Access Denied. Please log in first.']));
}

$customersId = $_SESSION['customer_id']; // This is the customer's identifier (could be email, username, etc.)
logActivity("Customer request initiated for customers_id: $customersId");

try {
    // === STEP 1: Find the customer's primary ID ===
    $findCustomerQuery = "SELECT id FROM customers WHERE customer_id = ?";
    $findStmt = $conn->prepare($findCustomerQuery);
    
    if (!$findStmt) {
        logActivity("Customer lookup prepare failed: " . $conn->error);
        throw new Exception("Database error");
    }
    
    if (!$findStmt->bind_param("s", $customersId) || !$findStmt->execute()) {
        logActivity("Customer lookup execution failed for customers_id: $customersId");
        throw new Exception("Database error");
    }
    
    $findStmt->bind_result($customerId);
    if (!$findStmt->fetch()) {
        logActivity("Customer not found with customers_id: $customersId");
        http_response_code(404);
        exit(json_encode(['success' => false, 'message' => 'Customer not found']));
    }
    $findStmt->close();
    
    logActivity("Found customer ID: $customerId for customers_id: $customersId");

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

    if (!array_key_exists($actionType, $validTables)) {
        logActivity("Invalid action type requested: $actionType by customer $customersId");
        http_response_code(400);
        exit(json_encode(['success' => false, 'message' => 'Invalid action type.']));
    }

    $table = $validTables[$actionType];
    logActivity("Preparing to fetch $actionType logs for customer ID: $customerId");

    // === Count Query ===
    $countQuery = "SELECT COUNT(*) FROM `$table` WHERE account_id = ? AND account_type = 'CUSTOMERS' AND action_type = ?";
    $countStmt = $conn->prepare($countQuery);
    
    if (!$countStmt) {
        logActivity("Count prepare failed: " . $conn->error);
        throw new Exception("Database error");
    }
    
    if (!$countStmt->bind_param("is", $customerId, $actionType) || !$countStmt->execute()) {
        logActivity("Count query failed for customer ID: $customerId");
        throw new Exception("Database error");
    }
    
    $countStmt->bind_result($totalRecords);
    $countStmt->fetch();
    $countStmt->close();
    
    logActivity("Found $totalRecords $actionType records for customer ID: $customerId");

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
          AND ar.account_type = 'CUSTOMERS'
          AND ar.action_type = ?
        ORDER BY ar.id DESC
        LIMIT ?, ?
    ";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        logActivity("Main query prepare failed: " . $conn->error);
        throw new Exception("Database error");
    }
    
    if (!$stmt->bind_param("isii", $customerId, $actionType, $offset, $limit)) {
        logActivity("Main query bind failed for customer ID: $customerId");
        throw new Exception("Database error");
    }
    
    if (!$stmt->execute()) {
        logActivity("Main query execute failed: " . $stmt->error);
        throw new Exception("Database error");
    }
    
    $result = $stmt->get_result();
    $records = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    logActivity("Retrieved " . count($records) . " $actionType records for customer ID: $customerId");

    // Mask reference_id
    $maskedRecords = array_map(function($record) {
        if (isset($record['reference_id']) && strlen($record['reference_id']) > 8) {
            $ref = $record['reference_id'];
            $record['reference_id'] = substr($ref, 0, 4) . '****' . substr($ref, -4);
        }
        return $record;
    }, $records);
    
    echo json_encode([
        'success' => true,
        'customer_id' => $customerId, // Include the internal ID in response
        'records' => $maskedRecords,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $totalRecords,
            'pages' => ceil($totalRecords / $limit)
        ]
    ]);

} catch (Exception $e) {
    logActivity("ERROR for customers_id $customersId: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching data.',
        'error' => $e->getMessage() // Remove in production
    ]);
} finally {
    // if (isset($findStmt)) $findStmt->close();
    // if (isset($countStmt)) $countStmt->close();
    // if (isset($stmt)) $stmt->close();
    $conn->close();
}
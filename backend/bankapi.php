<?php
// backend/bank_crud_api.php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
session_start();

// Start logging
$requestId = uniqid('bank_', true);
logActivity("[BANK_CRUD_START] [ID:{$requestId}] Bank CRUD request started");

// Check authentication
if (!isset($_SESSION['unique_id'])) {
    logActivity("[BANK_CRUD_ERROR] [ID:{$requestId}] No session found");
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Please login first']);
    exit;
}



// Check if user is admin (you can adjust role check based on your system)
$user_id = $_SESSION['unique_id'];
$user_role = $_SESSION['role'] ?? '';

if (!in_array($user_role, ['Super Admin', 'Admin'])) {
    logActivity("[BANK_CRUD_ERROR] [ID:{$requestId}] Insufficient permissions for user: {$user_id}");
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

logActivity("[BANK_CRUD] [ID:{$requestId}] User ID: {$user_id}, Role: {$user_role}, Method: {$method}");

try {
    switch ($method) {
        case 'GET':
            handleGet($conn, $user_id);
            break;
        case 'POST':
            handlePost($conn, $user_id);
            break;
        case 'PUT':
            handlePut($conn, $user_id);
            break;
        case 'DELETE':
            handleDelete($conn, $user_id);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    logActivity("[BANK_CRUD_EXCEPTION] [ID:{$requestId}] Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}

function handleGet($conn, $user_id)
{
    global $requestId;

    $action = $_GET['action'] ?? 'list';

    logActivity("[BANK_CRUD_GET] [ID:{$requestId}] Action: {$action}");

    switch ($action) {
        case 'list':
            getBanks($conn);
            break;
        case 'get':
            getBank($conn, $_GET);
            break;
        case 'codes':
            getBankCodes($conn);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

function handlePost($conn, $user_id)
{
    global $requestId;

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        logActivity("[BANK_CRUD_POST_ERROR] [ID:{$requestId}] Invalid JSON");
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
        return;
    }

    $action = $data['action'] ?? 'create';

    logActivity("[BANK_CRUD_POST] [ID:{$requestId}] Action: {$action}");

    switch ($action) {
        case 'create':
            createBank($conn, $user_id, $data);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

function handlePut($conn, $user_id)
{
    global $requestId;

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        logActivity("[BANK_CRUD_PUT_ERROR] [ID:{$requestId}] Invalid JSON");
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
        return;
    }

    $action = $data['action'] ?? 'update';

    logActivity("[BANK_CRUD_PUT] [ID:{$requestId}] Action: {$action}");

    switch ($action) {
        case 'update':
            updateBank($conn, $user_id, $data);
            break;
        case 'toggle_status':
            toggleBankStatus($conn, $user_id, $data);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

function handleDelete($conn, $user_id)
{
    global $requestId;

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        logActivity("[BANK_CRUD_DELETE_ERROR] [ID:{$requestId}] Invalid JSON");
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
        return;
    }

    $bank_id = $data['bank_id'] ?? 0;

    if (!$bank_id) {
        echo json_encode(['success' => false, 'message' => 'Bank ID is required']);
        return;
    }

    deleteBank($conn, $user_id, $bank_id);
}

function getBanks($conn)
{
    global $requestId;

    logActivity("[BANKS_GET_START] [ID:{$requestId}] Fetching banks list");

    // Pagination
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(100, intval($_GET['limit'])) : 20;
    $offset = ($page - 1) * $limit;
    $search = $_GET['search'] ?? '';
    $show_inactive = isset($_GET['show_inactive']) && $_GET['show_inactive'] === 'true';

    logActivity("[BANKS_GET_PARAMS] [ID:{$requestId}] Page: {$page}, Limit: {$limit}, Offset: {$offset}, Search: '{$search}', Show Inactive: " . ($show_inactive ? 'true' : 'false'));

    // Build query
    $query = "SELECT * FROM banks WHERE 1=1";
    $countQuery = "SELECT COUNT(*) as total FROM banks WHERE 1=1";
    $params = [];
    $types = "";

    if (!$show_inactive) {
        $query .= " AND is_active = 1";
        $countQuery .= " AND is_active = 1";
        logActivity("[BANKS_GET_FILTER] [ID:{$requestId}] Filtering for active banks only");
    }

    if (!empty($search)) {
        $query .= " AND (bank_name LIKE ? OR bank_code LIKE ?)";
        $countQuery .= " AND (bank_name LIKE ? OR bank_code LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= "ss";
        logActivity("[BANKS_GET_SEARCH] [ID:{$requestId}] Applying search filter: '{$search}'");
    }

    logActivity("[BANKS_GET_QUERY] [ID:{$requestId}] Main query: " . $query);
    logActivity("[BANKS_GET_COUNT_QUERY] [ID:{$requestId}] Count query: " . $countQuery);

    // Get total count
    $countStmt = $conn->prepare($countQuery);
    if (!$countStmt) {
        logActivity("[BANKS_GET_ERROR] [ID:{$requestId}] Failed to prepare count statement: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }

    if (!empty($search)) {
        logActivity("[BANKS_GET_COUNT_BIND] [ID:{$requestId}] Binding count params: types={$types}, values=" . json_encode($params));
        $countStmt->bind_param($types, ...$params);
    }

    if (!$countStmt->execute()) {
        logActivity("[BANKS_GET_ERROR] [ID:{$requestId}] Failed to execute count query: " . $countStmt->error);
        $countStmt->close();
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }

    $countResult = $countStmt->get_result();
    $totalCount = $countResult->fetch_assoc()['total'];

    // Get distinct number of banks used by drivers
$driverBankQuery = "
    SELECT COUNT(DISTINCT bank_code) as used_bank_count 
    FROM driver_banks
";

logActivity("[BANKS_GET_DRIVER_COUNT_QUERY] [ID:{$requestId}] Query: {$driverBankQuery}");

$driverStmt = $conn->prepare($driverBankQuery);

if (!$driverStmt) {
    logActivity("[BANKS_GET_ERROR] [ID:{$requestId}] Failed to prepare driver bank count statement: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    return;
}

if (!$driverStmt->execute()) {
    logActivity("[BANKS_GET_ERROR] [ID:{$requestId}] Failed to execute driver bank count query: " . $driverStmt->error);
    $driverStmt->close();
    echo json_encode(['success' => false, 'message' => 'Database error']);
    return;
}

$driverResult = $driverStmt->get_result();
$driverData = $driverResult->fetch_assoc();
$distinctDriverBanks = (int) $driverData['used_bank_count'];

$driverStmt->close();

logActivity("[BANKS_GET_DRIVER_COUNT] [ID:{$requestId}] Distinct driver banks used: {$distinctDriverBanks}");
    $countStmt->close();

    logActivity("[BANKS_GET_TOTAL] [ID:{$requestId}] Total banks found: {$totalCount}");

    // Add pagination
    $query .= " ORDER BY bank_name ASC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";

    logActivity("[BANKS_GET_FINAL_QUERY] [ID:{$requestId}] Final query with pagination: " . $query);
    logActivity("[BANKS_GET_PARAMS_FULL] [ID:{$requestId}] Final params: " . json_encode($params) . ", types: {$types}");

    // Execute main query
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        logActivity("[BANKS_GET_ERROR] [ID:{$requestId}] Failed to prepare main statement: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }

    if (!empty($params)) {
        $bindResult = $stmt->bind_param($types, ...$params);
        if (!$bindResult) {
            logActivity("[BANKS_GET_ERROR] [ID:{$requestId}] Failed to bind parameters");
            $stmt->close();
            echo json_encode(['success' => false, 'message' => 'Parameter binding failed']);
            return;
        }
        logActivity("[BANKS_GET_BIND] [ID:{$requestId}] Parameters bound successfully");
    }

    if (!$stmt->execute()) {
        logActivity("[BANKS_GET_ERROR] [ID:{$requestId}] Failed to execute main query: " . $stmt->error);
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }

    $result = $stmt->get_result();
    $rowCount = $result->num_rows;
    logActivity("[BANKS_GET_RESULTS] [ID:{$requestId}] Retrieved {$rowCount} banks from database");

    $banks = [];
    while ($row = $result->fetch_assoc()) {
        $banks[] = [
            'id' => $row['id'],
            'bank_code' => $row['bank_code'],
            'bank_name' => $row['bank_name'],
            'sort_code' => $row['sort_code'],
            'is_active' => (bool) $row['is_active'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }

    $stmt->close();

    logActivity("[BANKS_GET_SUCCESS] [ID:{$requestId}] Retrieved " . count($banks) . " banks successfully");

    echo json_encode([
    'success' => true,
    'banks' => $banks,
    'driver_bank_stats' => [
        'distinct_banks_used' => $distinctDriverBanks
    ],
    'pagination' => [
        'total' => $totalCount,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => ceil($totalCount / $limit)
    ]
]);
}

function getBank($conn, $params)
{
    global $requestId;

    $bank_id = $params['bank_id'] ?? 0;
    $bank_code = $params['bank_code'] ?? '';

    logActivity("[BANK_GET_START] [ID:{$requestId}] Fetching bank details - ID: {$bank_id}, Code: '{$bank_code}'");

    if (!$bank_id && !$bank_code) {
        logActivity("[BANK_GET_ERROR] [ID:{$requestId}] Neither bank_id nor bank_code provided");
        echo json_encode(['success' => false, 'message' => 'Bank ID or Code is required']);
        return;
    }

    $query = "SELECT * FROM banks WHERE ";
    $types = "";
    $values = [];

    if ($bank_id) {
        $query .= "id = ?";
        $types .= "i";
        $values[] = $bank_id;
        logActivity("[BANK_GET_QUERY] [ID:{$requestId}] Searching by bank ID: {$bank_id}");
    } else {
        $query .= "bank_code = ?";
        $types .= "s";
        $values[] = $bank_code;
        logActivity("[BANK_GET_QUERY] [ID:{$requestId}] Searching by bank code: '{$bank_code}'");
    }

    logActivity("[BANK_GET_SQL] [ID:{$requestId}] Query: " . $query);
    logActivity("[BANK_GET_PARAMS] [ID:{$requestId}] Params: " . json_encode($values) . ", types: {$types}");

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        logActivity("[BANK_GET_ERROR] [ID:{$requestId}] Failed to prepare statement: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }

    $bindResult = $stmt->bind_param($types, ...$values);
    if (!$bindResult) {
        logActivity("[BANK_GET_ERROR] [ID:{$requestId}] Failed to bind parameters");
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Parameter binding failed']);
        return;
    }

    if (!$stmt->execute()) {
        logActivity("[BANK_GET_ERROR] [ID:{$requestId}] Failed to execute query: " . $stmt->error);
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        logActivity("[BANK_GET_NOT_FOUND] [ID:{$requestId}] Bank not found");
        echo json_encode(['success' => false, 'message' => 'Bank not found']);
        $stmt->close();
        return;
    }

    $row = $result->fetch_assoc();
    $stmt->close();

    logActivity("[BANK_GET_SUCCESS] [ID:{$requestId}] Bank found: {$row['bank_name']} ({$row['bank_code']})");

    echo json_encode([
        'success' => true,
        'bank' => [
            'id' => $row['id'],
            'bank_code' => $row['bank_code'],
            'bank_name' => $row['bank_name'],
            'sort_code' => $row['sort_code'],
            'is_active' => (bool) $row['is_active'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ]
    ]);
}

function getBankCodes($conn)
{
    global $requestId;

    logActivity("[BANK_CODES_START] [ID:{$requestId}] Fetching active bank codes");

    $stmt = $conn->prepare("SELECT bank_code, bank_name FROM banks WHERE is_active = 1 ORDER BY bank_name ASC");
    if (!$stmt) {
        logActivity("[BANK_CODES_ERROR] [ID:{$requestId}] Failed to prepare statement: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }

    if (!$stmt->execute()) {
        logActivity("[BANK_CODES_ERROR] [ID:{$requestId}] Failed to execute query: " . $stmt->error);
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }

    $result = $stmt->get_result();
    $rowCount = $result->num_rows;
    logActivity("[BANK_CODES_RESULTS] [ID:{$requestId}] Found {$rowCount} active banks");

    $codes = [];
    while ($row = $result->fetch_assoc()) {
        $codes[] = [
            'code' => $row['bank_code'],
            'name' => $row['bank_name']
        ];
    }

    $stmt->close();

    logActivity("[BANK_CODES_SUCCESS] [ID:{$requestId}] Retrieved " . count($codes) . " bank codes successfully");

    echo json_encode([
        'success' => true,
        'codes' => $codes
    ]);
}

function createBank($conn, $user_id, $data)
{
    global $requestId;

    $bank_code = trim(strtoupper($data['bank_code'] ?? ''));
    $bank_name = trim($data['bank_name'] ?? '');
    $sort_code = trim($data['sort_code'] ?? '');

    logActivity("[BANK_CREATE_START] [ID:{$requestId}] Creating new bank - Code: {$bank_code}, Name: {$bank_name}, Sort Code: {$sort_code}, By User: {$user_id}");

    // Validate inputs
    $errors = [];
    if (empty($bank_code))
        $errors[] = 'Bank code is required';
    if (empty($bank_name))
        $errors[] = 'Bank name is required';

    if (!empty($errors)) {
        logActivity("[BANK_CREATE_VALIDATION_ERROR] [ID:{$requestId}] Validation failed: " . implode(', ', $errors));
        echo json_encode(['success' => false, 'message' => implode('. ', $errors)]);
        return;
    }

    // Check if bank code, sort code, or similar bank name already exists
    logActivity("[BANK_CREATE_CHECK] [ID:{$requestId}] Checking for existing bank with code: {$bank_code} or sort code: {$sort_code} or name similar to: {$bank_name}");

    $checkStmt = $conn->prepare("
        SELECT id, bank_code, sort_code, bank_name 
        FROM banks 
        WHERE bank_code = ? 
           OR sort_code = ? 
           OR LOWER(bank_name) = LOWER(?)
    ");

    if (!$checkStmt) {
        logActivity("[BANK_CREATE_ERROR] [ID:{$requestId}] Failed to prepare check statement: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }

    // Use exact match for bank name instead of LIKE with wildcards
    // This prevents partial matches (e.g., "GTB" matching "GTBank")
    $checkStmt->bind_param("sss", $bank_code, $sort_code, $bank_name);

    if (!$checkStmt->execute()) {
        logActivity("[BANK_CREATE_ERROR] [ID:{$requestId}] Failed to execute check query: " . $checkStmt->error);
        $checkStmt->close();
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }

    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows > 0) {
        $duplicateFields = [];
        $existingRecords = [];

        // Check all matching records to identify all duplicates
        while ($row = $checkResult->fetch_assoc()) {
            $existingRecords[] = $row;

            if ($row['bank_code'] === $bank_code) {
                $duplicateFields['Bank Code'] = $row['bank_code'];
            }

            if (!empty($sort_code) && $row['sort_code'] === $sort_code) {
                $duplicateFields['Sort Code'] = $row['sort_code'];
            }

            if (strcasecmp($row['bank_name'], $bank_name) === 0) {
                $duplicateFields['Bank Name'] = $row['bank_name'];
            }
        }

        // Build duplicate message
        if (!empty($duplicateFields)) {
            $fieldNames = array_keys($duplicateFields);
            $duplicateMessage = implode(", ", $fieldNames) . " already exist" . (count($fieldNames) > 1 ? "" : "s") . ".";
        } else {
            $duplicateMessage = "A similar bank record already exists.";
        }

        logActivity("[BANK_CREATE_DUPLICATE] [ID:{$requestId}] Duplicate detected: {$duplicateMessage} - Existing records: " . json_encode($existingRecords));

        $checkStmt->close();

        echo json_encode([
            'success' => false,
            'message' => $duplicateMessage
        ]);
        return;
    }

    $checkStmt->close();

    logActivity("[BANK_CREATE_UNIQUE] [ID:{$requestId}] No duplicates found, proceeding with insert");

    // Insert new bank
    $stmt = $conn->prepare("
        INSERT INTO banks (bank_code, bank_name, sort_code, created_by, updated_by) 
        VALUES (?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        logActivity("[BANK_CREATE_ERROR] [ID:{$requestId}] Failed to prepare insert statement: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }

    $stmt->bind_param("sssii", $bank_code, $bank_name, $sort_code, $user_id, $user_id);

    if ($stmt->execute()) {
        $bank_id = $stmt->insert_id;
        logActivity("[BANK_CREATE_SUCCESS] [ID:{$requestId}] Bank created successfully - ID: {$bank_id}, Code: {$bank_code}, Name: {$bank_name}");

        echo json_encode([
            'success' => true,
            'message' => 'Bank created successfully',
            'bank_id' => $bank_id,
            'bank' => [
                'id' => $bank_id,
                'bank_code' => $bank_code,
                'bank_name' => $bank_name,
                'sort_code' => $sort_code,
                'is_active' => true
            ]
        ]);
    } else {
        logActivity("[BANK_CREATE_ERROR] [ID:{$requestId}] Failed to create bank: " . $stmt->error);
        echo json_encode(['success' => false, 'message' => 'Failed to create bank: ' . $stmt->error]);
    }

    $stmt->close();
}

function updateBank($conn, $user_id, $data)
{
    global $requestId;

    $bank_id = $data['bank_id'] ?? 0;
    $bank_code = trim(strtoupper($data['bank_code'] ?? ''));
    $bank_name = trim($data['bank_name'] ?? '');
    $sort_code = trim($data['sort_code'] ?? '');

    logActivity("[BANK_UPDATE_START] [ID:{$requestId}] Updating bank ID: {$bank_id} - New Code: {$bank_code}, Name: {$bank_name}, Sort Code: {$sort_code}, By User: {$user_id}");

    if (!$bank_id) {
        logActivity("[BANK_UPDATE_ERROR] [ID:{$requestId}] Bank ID is required but not provided");
        echo json_encode(['success' => false, 'message' => 'Bank ID is required']);
        return;
    }

    // Validate inputs
    $errors = [];
    if (empty($bank_code))
        $errors[] = 'Bank code is required';
    if (empty($bank_name))
        $errors[] = 'Bank name is required';

    if (!empty($errors)) {
        logActivity("[BANK_UPDATE_VALIDATION_ERROR] [ID:{$requestId}] Validation failed: " . implode(', ', $errors));
        echo json_encode(['success' => false, 'message' => implode('. ', $errors)]);
        return;
    }

    // First, get current bank details for comparison
    $currentStmt = $conn->prepare("SELECT bank_code, bank_name, sort_code FROM banks WHERE id = ?");
    $currentStmt->bind_param("i", $bank_id);
    $currentStmt->execute();
    $currentResult = $currentStmt->get_result();
    
    if ($currentResult->num_rows === 0) {
        logActivity("[BANK_UPDATE_ERROR] [ID:{$requestId}] Bank ID {$bank_id} not found");
        echo json_encode(['success' => false, 'message' => 'Bank not found']);
        $currentStmt->close();
        return;
    }
    
    $currentBank = $currentResult->fetch_assoc();
    $currentStmt->close();
    
    logActivity("[BANK_UPDATE_CURRENT] [ID:{$requestId}] Current bank details - Code: {$currentBank['bank_code']}, Name: {$currentBank['bank_name']}, Sort Code: {$currentBank['sort_code']}");

    // Check for duplicates in other banks
    logActivity("[BANK_UPDATE_CHECK] [ID:{$requestId}] Checking for duplicates in other banks");
    
    $checkStmt = $conn->prepare("
        SELECT id, bank_code, bank_name, sort_code 
        FROM banks 
        WHERE id != ? 
          AND (
              bank_code = ? 
              OR sort_code = ? 
              OR LOWER(bank_name) = LOWER(?)
          )
    ");
    
    if (!$checkStmt) {
        logActivity("[BANK_UPDATE_ERROR] [ID:{$requestId}] Failed to prepare check statement: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }

    $checkStmt->bind_param("isss", $bank_id, $bank_code, $sort_code, $bank_name);
    
    if (!$checkStmt->execute()) {
        logActivity("[BANK_UPDATE_ERROR] [ID:{$requestId}] Failed to execute check query: " . $checkStmt->error);
        $checkStmt->close();
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }

    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows > 0) {
        $duplicateFields = [];
        $duplicateRecords = [];
        
        while ($row = $checkResult->fetch_assoc()) {
            $duplicateRecords[] = $row;
            
            if ($row['bank_code'] === $bank_code) {
                $duplicateFields['Bank Code'] = $row['bank_code'];
            }
            
            if (!empty($sort_code) && $row['sort_code'] === $sort_code) {
                $duplicateFields['Sort Code'] = $row['sort_code'];
            }
            
            if (strcasecmp($row['bank_name'], $bank_name) === 0) {
                $duplicateFields['Bank Name'] = $row['bank_name'];
            }
        }

        // Build duplicate message
        if (!empty($duplicateFields)) {
            $fieldNames = array_keys($duplicateFields);
            $duplicateMessage = implode(", ", $fieldNames) . " already exist" . (count($fieldNames) > 1 ? "" : "s") . " in another bank.";
        } else {
            $duplicateMessage = "A similar bank record already exists.";
        }

        logActivity("[BANK_UPDATE_DUPLICATE] [ID:{$requestId}] Duplicate detected: {$duplicateMessage} - Duplicate records: " . json_encode($duplicateRecords));
        
        $checkStmt->close();
        
        echo json_encode([
            'success' => false,
            'message' => $duplicateMessage
        ]);
        return;
    }
    
    $checkStmt->close();

    // Also check if sort code is being used by another bank (if provided and different from current)
    if (!empty($sort_code) && $sort_code !== $currentBank['sort_code']) {
        $sortCheckStmt = $conn->prepare("SELECT id FROM banks WHERE sort_code = ? AND id != ?");
        $sortCheckStmt->bind_param("si", $sort_code, $bank_id);
        $sortCheckStmt->execute();
        $sortCheckResult = $sortCheckStmt->get_result();
        
        if ($sortCheckResult->num_rows > 0) {
            logActivity("[BANK_UPDATE_DUPLICATE] [ID:{$requestId}] Sort code '{$sort_code}' already exists in another bank");
            $sortCheckStmt->close();
            echo json_encode(['success' => false, 'message' => 'Sort code already exists in another bank']);
            return;
        }
        $sortCheckStmt->close();
    }

    logActivity("[BANK_UPDATE_UNIQUE] [ID:{$requestId}] All checks passed, proceeding with update");

    // Update bank
    $stmt = $conn->prepare("
        UPDATE banks 
        SET bank_code = ?, bank_name = ?, sort_code = ?, updated_by = ? 
        WHERE id = ?
    ");

    if (!$stmt) {
        logActivity("[BANK_UPDATE_ERROR] [ID:{$requestId}] Failed to prepare update statement: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }

    $stmt->bind_param("sssii", $bank_code, $bank_name, $sort_code, $user_id, $bank_id);

    if ($stmt->execute()) {
        $affectedRows = $stmt->affected_rows;
        
        // Get updated bank details for confirmation
        $confirmStmt = $conn->prepare("SELECT bank_code, bank_name, sort_code FROM banks WHERE id = ?");
        $confirmStmt->bind_param("i", $bank_id);
        $confirmStmt->execute();
        $confirmResult = $confirmStmt->get_result();
        $updatedBank = $confirmResult->fetch_assoc();
        $confirmStmt->close();
        
        logActivity("[BANK_UPDATE_SUCCESS] [ID:{$requestId}] Bank updated successfully - ID: {$bank_id}, Affected rows: {$affectedRows}");
        logActivity("[BANK_UPDATE_CONFIRM] [ID:{$requestId}] Updated bank details - Code: {$updatedBank['bank_code']}, Name: {$updatedBank['bank_name']}, Sort Code: {$updatedBank['sort_code']}");

        echo json_encode([
            'success' => true,
            'message' => 'Bank updated successfully',
            'bank' => [
                'id' => $bank_id,
                'bank_code' => $bank_code,
                'bank_name' => $bank_name,
                'sort_code' => $sort_code
            ]
        ]);
    } else {
        logActivity("[BANK_UPDATE_ERROR] [ID:{$requestId}] Failed to update bank: " . $stmt->error);
        echo json_encode(['success' => false, 'message' => 'Failed to update bank: ' . $stmt->error]);
    }

    $stmt->close();
}
function toggleBankStatus($conn, $user_id, $data)
{
    global $requestId;

    $bank_id = $data['bank_id'] ?? 0;
    $is_active = isset($data['is_active']) ? (int) $data['is_active'] : null;

    logActivity("[BANK_STATUS_START] [ID:{$requestId}] Toggling bank status - Bank ID: {$bank_id}, New Status: " . ($is_active !== null ? ($is_active ? 'active' : 'inactive') : 'null') . ", By User: {$user_id}");

    if (!$bank_id || $is_active === null) {
        $errorMsg = "Bank ID and status are required";
        if (!$bank_id)
            logActivity("[BANK_STATUS_ERROR] [ID:{$requestId}] Bank ID is missing");
        if ($is_active === null)
            logActivity("[BANK_STATUS_ERROR] [ID:{$requestId}] Status value is missing");

        echo json_encode(['success' => false, 'message' => 'Bank ID and status are required']);
        return;
    }

    // Optional: Get current bank details before update for logging
    $checkStmt = $conn->prepare("SELECT bank_name, bank_code, is_active FROM banks WHERE id = ?");
    if ($checkStmt) {
        $checkStmt->bind_param("i", $bank_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        if ($checkResult->num_rows > 0) {
            $bank = $checkResult->fetch_assoc();
            logActivity("[BANK_STATUS_CURRENT] [ID:{$requestId}] Current bank state - Name: {$bank['bank_name']}, Code: {$bank['bank_code']}, Current Status: " . ($bank['is_active'] ? 'active' : 'inactive'));
        } else {
            logActivity("[BANK_STATUS_WARNING] [ID:{$requestId}] Bank ID {$bank_id} not found in database");
        }
        $checkStmt->close();
    }

    logActivity("[BANK_STATUS_QUERY] [ID:{$requestId}] Preparing update: UPDATE banks SET is_active = {$is_active}, updated_by = {$user_id} WHERE id = {$bank_id}");

    $stmt = $conn->prepare("UPDATE banks SET is_active = ?, updated_by = ? WHERE id = ?");
    if (!$stmt) {
        logActivity("[BANK_STATUS_ERROR] [ID:{$requestId}] Failed to prepare statement: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }

    $bindResult = $stmt->bind_param("iii", $is_active, $user_id, $bank_id);
    if (!$bindResult) {
        logActivity("[BANK_STATUS_ERROR] [ID:{$requestId}] Failed to bind parameters");
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Parameter binding failed']);
        return;
    }

    logActivity("[BANK_STATUS_EXECUTE] [ID:{$requestId}] Executing status update");

    if ($stmt->execute()) {
        $affectedRows = $stmt->affected_rows;
        $status = $is_active ? 'activated' : 'deactivated';

        logActivity("[BANK_STATUS_SUCCESS] [ID:{$requestId}] Bank ID {$bank_id} {$status} successfully. Affected rows: {$affectedRows}");

        // Get updated bank details for confirmation
        $confirmStmt = $conn->prepare("SELECT bank_name, bank_code, is_active FROM banks WHERE id = ?");
        if ($confirmStmt) {
            $confirmStmt->bind_param("i", $bank_id);
            $confirmStmt->execute();
            $confirmResult = $confirmStmt->get_result();
            if ($confirmResult->num_rows > 0) {
                $updated = $confirmResult->fetch_assoc();
                logActivity("[BANK_STATUS_CONFIRM] [ID:{$requestId}] Updated bank state - Name: {$updated['bank_name']}, Code: {$updated['bank_code']}, New Status: " . ($updated['is_active'] ? 'active' : 'inactive'));
            }
            $confirmStmt->close();
        }

        echo json_encode([
            'success' => true,
            'message' => 'Bank ' . $status . ' successfully',
            'is_active' => (bool) $is_active
        ]);
    } else {
        logActivity("[BANK_STATUS_ERROR] [ID:{$requestId}] Failed to toggle bank status: " . $stmt->error);
        echo json_encode(['success' => false, 'message' => 'Failed to update bank status']);
    }

    $stmt->close();
}

function deleteBank($conn, $user_id, $bank_id)
{
    global $requestId;

    logActivity("[BANK_DELETE_START] [ID:{$requestId}] Attempting to delete/deactivate bank - Bank ID: {$bank_id}, By User: {$user_id}");

    if (!$bank_id) {
        logActivity("[BANK_DELETE_ERROR] [ID:{$requestId}] Bank ID is required but not provided");
        echo json_encode(['success' => false, 'message' => 'Bank ID is required']);
        return;
    }

    // First, get bank details for logging
    $detailsStmt = $conn->prepare("SELECT bank_name, bank_code, is_active FROM banks WHERE id = ?");
    if (!$detailsStmt) {
        logActivity("[BANK_DELETE_WARNING] [ID:{$requestId}] Could not prepare details query: " . $conn->error);
    } else {
        $detailsStmt->bind_param("i", $bank_id);
        $detailsStmt->execute();
        $detailsResult = $detailsStmt->get_result();

        if ($detailsResult->num_rows > 0) {
            $bank = $detailsResult->fetch_assoc();
            logActivity("[BANK_DELETE_DETAILS] [ID:{$requestId}] Bank to delete - Name: {$bank['bank_name']}, Code: {$bank['bank_code']}, Current Status: " . ($bank['is_active'] ? 'active' : 'inactive'));
        } else {
            logActivity("[BANK_DELETE_WARNING] [ID:{$requestId}] Bank ID {$bank_id} not found in database");
        }
        $detailsStmt->close();
    }

    // Check if bank is being used by drivers (optional dependency check)
    logActivity("[BANK_DELETE_CHECK] [ID:{$requestId}] Checking if bank is referenced by other tables");

    // Check driver_banks table for any drivers using this bank
    $usageStmt = $conn->prepare("
        SELECT COUNT(*) as usage_count 
        FROM driver_banks db
        JOIN banks b ON db.bank_code = b.bank_code
        WHERE b.id = ?
    ");

    if ($usageStmt) {
        $usageStmt->bind_param("i", $bank_id);
        $usageStmt->execute();
        $usageResult = $usageStmt->get_result();
        $usageData = $usageResult->fetch_assoc();
        $usageCount = $usageData['usage_count'] ?? 0;
        $usageStmt->close();

        logActivity("[BANK_DELETE_USAGE] [ID:{$requestId}] Bank is used by {$usageCount} driver(s)");

        if ($usageCount > 0) {
            logActivity("[BANK_DELETE_WARNING] [ID:{$requestId}] Bank has dependent records - cannot hard delete, will soft delete instead");
        }
    } else {
        logActivity("[BANK_DELETE_WARNING] [ID:{$requestId}] Could not check driver_banks table: " . $conn->error);
    }

    // Soft delete by deactivating instead of hard delete
    logActivity("[BANK_DELETE_QUERY] [ID:{$requestId}] Preparing soft delete: UPDATE banks SET is_active = 0, updated_by = {$user_id} WHERE id = {$bank_id}");

    $stmt = $conn->prepare("UPDATE banks SET is_active = 0, updated_by = ? WHERE id = ?");
    if (!$stmt) {
        logActivity("[BANK_DELETE_ERROR] [ID:{$requestId}] Failed to prepare delete statement: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }

    $bindResult = $stmt->bind_param("ii", $user_id, $bank_id);
    if (!$bindResult) {
        logActivity("[BANK_DELETE_ERROR] [ID:{$requestId}] Failed to bind parameters");
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Parameter binding failed']);
        return;
    }

    logActivity("[BANK_DELETE_EXECUTE] [ID:{$requestId}] Executing soft delete");

    if ($stmt->execute()) {
        $affectedRows = $stmt->affected_rows;

        if ($affectedRows > 0) {
            logActivity("[BANK_DELETE_SUCCESS] [ID:{$requestId}] Bank ID {$bank_id} deactivated successfully. Affected rows: {$affectedRows}");

            // Verify the deactivation
            $verifyStmt = $conn->prepare("SELECT is_active FROM banks WHERE id = ?");
            if ($verifyStmt) {
                $verifyStmt->bind_param("i", $bank_id);
                $verifyStmt->execute();
                $verifyResult = $verifyStmt->get_result();
                if ($verifyResult->num_rows > 0) {
                    $verifyData = $verifyResult->fetch_assoc();
                    logActivity("[BANK_DELETE_VERIFY] [ID:{$requestId}] Bank status after update: " . ($verifyData['is_active'] ? 'active' : 'inactive'));
                }
                $verifyStmt->close();
            }

            echo json_encode([
                'success' => true,
                'message' => 'Bank deactivated successfully'
            ]);
        } else {
            logActivity("[BANK_DELETE_WARNING] [ID:{$requestId}] No rows affected. Bank ID {$bank_id} may not exist or already inactive");
            echo json_encode([
                'success' => true,
                'message' => 'Bank already deactivated or not found'
            ]);
        }
    } else {
        logActivity("[BANK_DELETE_ERROR] [ID:{$requestId}] Failed to delete bank: " . $stmt->error);
        echo json_encode(['success' => false, 'message' => 'Failed to delete bank']);
    }

    $stmt->close();
}
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

function handleGet($conn, $user_id) {
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

function handlePost($conn, $user_id) {
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

function handlePut($conn, $user_id) {
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

function handleDelete($conn, $user_id) {
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

function getBanks($conn) {
    global $requestId;
    
    // Pagination
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(100, intval($_GET['limit'])) : 20;
    $offset = ($page - 1) * $limit;
    $search = $_GET['search'] ?? '';
    $show_inactive = isset($_GET['show_inactive']) && $_GET['show_inactive'] === 'true';
    
    // Build query
    $query = "SELECT * FROM banks WHERE 1=1";
    $countQuery = "SELECT COUNT(*) as total FROM banks WHERE 1=1";
    $params = [];
    $types = "";
    
    if (!$show_inactive) {
        $query .= " AND is_active = 1";
        $countQuery .= " AND is_active = 1";
    }
    
    if (!empty($search)) {
        $query .= " AND (bank_name LIKE ? OR bank_code LIKE ?)";
        $countQuery .= " AND (bank_name LIKE ? OR bank_code LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= "ss";
    }
    
    // Get total count
    $countStmt = $conn->prepare($countQuery);
    if (!empty($search)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalCount = $countResult->fetch_assoc()['total'];
    $countStmt->close();
    
    // Add pagination
    $query .= " ORDER BY bank_name ASC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    
    // Execute main query
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $banks = [];
    while ($row = $result->fetch_assoc()) {
        $banks[] = [
            'id' => $row['id'],
            'bank_code' => $row['bank_code'],
            'bank_name' => $row['bank_name'],
            'sort_code' => $row['sort_code'],
            'is_active' => (bool)$row['is_active'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }
    
    $stmt->close();
    
    logActivity("[BANKS_LIST] [ID:{$requestId}] Retrieved " . count($banks) . " banks");
    
    echo json_encode([
        'success' => true,
        'banks' => $banks,
        'pagination' => [
            'total' => $totalCount,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($totalCount / $limit)
        ]
    ]);
}

function getBank($conn, $params) {
    global $requestId;
    
    $bank_id = $params['bank_id'] ?? 0;
    $bank_code = $params['bank_code'] ?? '';
    
    if (!$bank_id && !$bank_code) {
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
    } else {
        $query .= "bank_code = ?";
        $types .= "s";
        $values[] = $bank_code;
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Bank not found']);
        $stmt->close();
        return;
    }
    
    $row = $result->fetch_assoc();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'bank' => [
            'id' => $row['id'],
            'bank_code' => $row['bank_code'],
            'bank_name' => $row['bank_name'],
            'sort_code' => $row['sort_code'],
            'is_active' => (bool)$row['is_active'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ]
    ]);
}

function getBankCodes($conn) {
    global $requestId;
    
    $stmt = $conn->prepare("SELECT bank_code, bank_name FROM banks WHERE is_active = 1 ORDER BY bank_name ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $codes = [];
    while ($row = $result->fetch_assoc()) {
        $codes[] = [
            'code' => $row['bank_code'],
            'name' => $row['bank_name']
        ];
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'codes' => $codes
    ]);
}

function createBank($conn, $user_id, $data) {
    global $requestId;
    
    $bank_code = trim(strtoupper($data['bank_code'] ?? ''));
    $bank_name = trim($data['bank_name'] ?? '');
    $sort_code = trim($data['sort_code'] ?? '');
    
    // Validate inputs
    $errors = [];
    if (empty($bank_code)) $errors[] = 'Bank code is required';
    if (empty($bank_name)) $errors[] = 'Bank name is required';
    
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => implode('. ', $errors)]);
        return;
    }
    
    // Check if bank code already exists
    $checkStmt = $conn->prepare("SELECT id FROM banks WHERE bank_code = ?");
    $checkStmt->bind_param("s", $bank_code);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        $checkStmt->close();
        echo json_encode(['success' => false, 'message' => 'Bank code already exists']);
        return;
    }
    $checkStmt->close();
    
    // Insert new bank
    $stmt = $conn->prepare("
        INSERT INTO banks (bank_code, bank_name, sort_code, created_by, updated_by) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param("sssii", $bank_code, $bank_name, $sort_code, $user_id, $user_id);
    
    if ($stmt->execute()) {
        $bank_id = $stmt->insert_id;
        logActivity("[BANK_CREATE] [ID:{$requestId}] Bank created: {$bank_code} - {$bank_name}");
        
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

function updateBank($conn, $user_id, $data) {
    global $requestId;
    
    $bank_id = $data['bank_id'] ?? 0;
    $bank_code = trim(strtoupper($data['bank_code'] ?? ''));
    $bank_name = trim($data['bank_name'] ?? '');
    $sort_code = trim($data['sort_code'] ?? '');
    
    if (!$bank_id) {
        echo json_encode(['success' => false, 'message' => 'Bank ID is required']);
        return;
    }
    
    // Validate inputs
    $errors = [];
    if (empty($bank_code)) $errors[] = 'Bank code is required';
    if (empty($bank_name)) $errors[] = 'Bank name is required';
    
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => implode('. ', $errors)]);
        return;
    }
    
    // Check if bank code exists for another bank
    $checkStmt = $conn->prepare("SELECT id FROM banks WHERE bank_code = ? AND id != ?");
    $checkStmt->bind_param("si", $bank_code, $bank_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        $checkStmt->close();
        echo json_encode(['success' => false, 'message' => 'Bank code already exists for another bank']);
        return;
    }
    $checkStmt->close();
    
    // Update bank
    $stmt = $conn->prepare("
        UPDATE banks 
        SET bank_code = ?, bank_name = ?, sort_code = ?, updated_by = ? 
        WHERE id = ?
    ");
    
    $stmt->bind_param("sssii", $bank_code, $bank_name, $sort_code, $user_id, $bank_id);
    
    if ($stmt->execute()) {
        logActivity("[BANK_UPDATE] [ID:{$requestId}] Bank updated: ID {$bank_id}");
        
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

function toggleBankStatus($conn, $user_id, $data) {
    global $requestId;
    
    $bank_id = $data['bank_id'] ?? 0;
    $is_active = isset($data['is_active']) ? (int)$data['is_active'] : null;
    
    if (!$bank_id || $is_active === null) {
        echo json_encode(['success' => false, 'message' => 'Bank ID and status are required']);
        return;
    }
    
    $stmt = $conn->prepare("UPDATE banks SET is_active = ?, updated_by = ? WHERE id = ?");
    $stmt->bind_param("iii", $is_active, $user_id, $bank_id);
    
    if ($stmt->execute()) {
        $status = $is_active ? 'activated' : 'deactivated';
        logActivity("[BANK_STATUS] [ID:{$requestId}] Bank ID {$bank_id} {$status}");
        
        echo json_encode([
            'success' => true,
            'message' => 'Bank ' . $status . ' successfully',
            'is_active' => (bool)$is_active
        ]);
    } else {
        logActivity("[BANK_STATUS_ERROR] [ID:{$requestId}] Failed to toggle bank status: " . $stmt->error);
        echo json_encode(['success' => false, 'message' => 'Failed to update bank status']);
    }
    
    $stmt->close();
}

function deleteBank($conn, $user_id, $bank_id) {
    global $requestId;
    
    // Check if bank is being used (optional - add checks for foreign key constraints)
    // For example, check if any drivers have this bank saved
    // $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM driver_banks WHERE bank_code = (SELECT bank_code FROM banks WHERE id = ?)");
    // $checkStmt->bind_param("i", $bank_id);
    // $checkStmt->execute();
    // ... etc
    
    // Soft delete by deactivating instead of hard delete
    $stmt = $conn->prepare("UPDATE banks SET is_active = 0, updated_by = ? WHERE id = ?");
    $stmt->bind_param("ii", $user_id, $bank_id);
    
    if ($stmt->execute()) {
        logActivity("[BANK_DELETE] [ID:{$requestId}] Bank ID {$bank_id} deactivated");
        
        echo json_encode([
            'success' => true,
            'message' => 'Bank deactivated successfully'
        ]);
    } else {
        logActivity("[BANK_DELETE_ERROR] [ID:{$requestId}] Failed to delete bank: " . $stmt->error);
        echo json_encode(['success' => false, 'message' => 'Failed to delete bank']);
    }
    
    $stmt->close();
}

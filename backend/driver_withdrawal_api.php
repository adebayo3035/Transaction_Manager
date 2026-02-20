<?php
// backend/admin_withdrawal_api.php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
session_start();

// Start logging
$requestId = uniqid('admin_wd_', true);
logActivity("[ADMIN_WITHDRAWAL_API_START] [ID:{$requestId}] Request started. Method: {$_SERVER['REQUEST_METHOD']}, URI: {$_SERVER['REQUEST_URI']}");

// Check authentication
if (!isset($_SESSION['unique_id'])) {
    logActivity("[ADMIN_WITHDRAWAL_API_ERROR] [ID:{$requestId}] No session found - User not authenticated");
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Please login first']);
    exit;
}

// Check if user is admin
$admin_id = $_SESSION['unique_id'];
$admin_role = $_SESSION['role'] ?? '';

logActivity("[ADMIN_WITHDRAWAL_API_AUTH] [ID:{$requestId}] Admin ID: {$admin_id}, Role: {$admin_role}");

if (!in_array($admin_role, ['Super Admin', 'Admin'])) {
    logActivity("[ADMIN_WITHDRAWAL_API_ERROR] [ID:{$requestId}] Insufficient permissions for user: {$admin_id}, role: {$admin_role}");
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

logActivity("[ADMIN_WITHDRAWAL_API] [ID:{$requestId}] Admin ID: {$admin_id}, Role: {$admin_role}, Method: {$method}");

try {
    switch ($method) {
        case 'GET':
            logActivity("[ADMIN_WITHDRAWAL_API] [ID:{$requestId}] Handling GET request");
            handleGet($conn, $admin_id);
            break;
        case 'POST':
            logActivity("[ADMIN_WITHDRAWAL_API] [ID:{$requestId}] Handling POST request");
            handlePost($conn, $admin_id);
            break;
        default:
            logActivity("[ADMIN_WITHDRAWAL_API_ERROR] [ID:{$requestId}] Method not allowed: {$method}");
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    logActivity("[ADMIN_WITHDRAWAL_API_EXCEPTION] [ID:{$requestId}] Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}

function handleGet($conn, $admin_id) {
    global $requestId;
    
    $action = $_GET['action'] ?? 'list';
    
    logActivity("[ADMIN_WITHDRAWAL_API_GET] [ID:{$requestId}] Action: {$action}, GET params: " . json_encode($_GET));
    
    switch ($action) {
        case 'summary':
            getWithdrawalSummary($conn);
            break;
        case 'list':
            getWithdrawalsList($conn);
            break;
        case 'details':
            getWithdrawalDetails($conn);
            break;
        case 'driver':
            getDriverDetails($conn, $_GET);
            break;
        default:
            logActivity("[ADMIN_WITHDRAWAL_API_GET_ERROR] [ID:{$requestId}] Invalid action: {$action}");
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

function handlePost($conn, $admin_id) {
    global $requestId;
    
    $input = file_get_contents('php://input');
    logActivity("[ADMIN_WITHDRAWAL_API_POST] [ID:{$requestId}] Raw input: " . substr($input, 0, 500));
    
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        logActivity("[ADMIN_WITHDRAWAL_API_POST_ERROR] [ID:{$requestId}] JSON decode error: " . json_last_error_msg());
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
        return;
    }
    
    $action = $data['action'] ?? '';
    
    logActivity("[ADMIN_WITHDRAWAL_API_POST] [ID:{$requestId}] Action: {$action}, Data: " . json_encode($data));
    
    switch ($action) {
        case 'process':
            processWithdrawal($conn, $admin_id, $data);
            break;
        case 'bulk_process':
            bulkProcessWithdrawals($conn, $admin_id, $data);
            break;
        default:
            logActivity("[ADMIN_WITHDRAWAL_API_POST_ERROR] [ID:{$requestId}] Invalid action: {$action}");
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

function getWithdrawalSummary($conn) {
    global $requestId;
    
    logActivity("[ADMIN_WITHDRAWAL_SUMMARY] [ID:{$requestId}] Fetching withdrawal summary");
    
    // Get pending summary
    $pendingQuery = "SELECT 
                        COUNT(*) as count,
                        COALESCE(SUM(amount), 0) as amount
                     FROM withdrawal_requests 
                     WHERE status = 'pending'";
    
    $pendingResult = $conn->query($pendingQuery);
    if (!$pendingResult) {
        logActivity("[ADMIN_WITHDRAWAL_SUMMARY_ERROR] [ID:{$requestId}] Pending query failed: " . $conn->error);
    }
    $pending = $pendingResult->fetch_assoc();
    
    // Get processing summary
    $processingQuery = "SELECT 
                        COUNT(*) as count,
                        COALESCE(SUM(amount), 0) as amount
                       FROM withdrawal_requests 
                       WHERE status = 'processing'";
    
    $processingResult = $conn->query($processingQuery);
    if (!$processingResult) {
        logActivity("[ADMIN_WITHDRAWAL_SUMMARY_ERROR] [ID:{$requestId}] Processing query failed: " . $conn->error);
    }
    $processing = $processingResult->fetch_assoc();
    
    // Get completed today
    $todayQuery = "SELECT 
                    COUNT(*) as count,
                    COALESCE(SUM(amount), 0) as amount
                   FROM withdrawal_requests 
                   WHERE status = 'completed' 
                   AND DATE(processed_at) = CURDATE()";
    
    $todayResult = $conn->query($todayQuery);
    if (!$todayResult) {
        logActivity("[ADMIN_WITHDRAWAL_SUMMARY_ERROR] [ID:{$requestId}] Completed today query failed: " . $conn->error);
    }
    $completedToday = $todayResult->fetch_assoc();
    
    // Get total processed
    $totalQuery = "SELECT 
                    COUNT(*) as count,
                    COALESCE(SUM(amount), 0) as amount
                   FROM withdrawal_requests 
                   WHERE status IN ('completed', 'failed', 'cancelled')";
    
    $totalResult = $conn->query($totalQuery);
    if (!$totalResult) {
        logActivity("[ADMIN_WITHDRAWAL_SUMMARY_ERROR] [ID:{$requestId}] Total query failed: " . $conn->error);
    }
    $total = $totalResult->fetch_assoc();
    
    $summary = [
        'pending' => [
            'count' => intval($pending['count'] ?? 0),
            'amount' => floatval($pending['amount'] ?? 0),
            'formatted_amount' => '₦' . number_format($pending['amount'] ?? 0, 2)
        ],
        'processing' => [
            'count' => intval($processing['count'] ?? 0),
            'amount' => floatval($processing['amount'] ?? 0),
            'formatted_amount' => '₦' . number_format($processing['amount'] ?? 0, 2)
        ],
        'completed_today' => [
            'count' => intval($completedToday['count'] ?? 0),
            'amount' => floatval($completedToday['amount'] ?? 0),
            'formatted_amount' => '₦' . number_format($completedToday['amount'] ?? 0, 2)
        ],
        'total' => [
            'count' => intval($total['count'] ?? 0),
            'amount' => floatval($total['amount'] ?? 0),
            'formatted_amount' => '₦' . number_format($total['amount'] ?? 0, 2)
        ]
    ];
    
    logActivity("[ADMIN_WITHDRAWAL_SUMMARY_SUCCESS] [ID:{$requestId}] Summary retrieved: " . json_encode($summary));
    
    echo json_encode([
        'success' => true,
        'summary' => $summary
    ]);
}

function getWithdrawalsList($conn) {
    global $requestId;
    
    // Pagination parameters
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(50, intval($_GET['limit'])) : 10;
    $offset = ($page - 1) * $limit;
    
    // Filters
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    $driver = isset($_GET['driver']) ? trim($_GET['driver']) : '';
    $from_date = isset($_GET['from_date']) ? trim($_GET['from_date']) : '';
    $to_date = isset($_GET['to_date']) ? trim($_GET['to_date']) : '';
    
    logActivity("[ADMIN_WITHDRAWAL_LIST] [ID:{$requestId}] Params - page: {$page}, limit: {$limit}, status: {$status}, driver: {$driver}, from: {$from_date}, to: {$to_date}");
    
    // Build query
    $query = "SELECT 
                w.id,
                w.reference,
                w.amount,
                w.bank_name,
                w.bank_code,
                w.account_number,
                w.account_name,
                w.note,
                w.status,
                w.admin_notes,
                w.created_at,
                w.updated_at,
                w.processed_at,
                w.processed_by,
                d.id as driver_id,
                d.firstname,
                d.lastname,
                d.email as driver_email,
                d.phone_number as driver_phone
              FROM withdrawal_requests w
              INNER JOIN driver d ON w.driver_id = d.id
              WHERE 1=1";
    
    $countQuery = "SELECT COUNT(*) as total 
                   FROM withdrawal_requests w
                   INNER JOIN driver d ON w.driver_id = d.id
                   WHERE 1=1";
    
    $params = [];
    $types = "";
    
    // Apply filters
    if (!empty($status)) {
        $query .= " AND w.status = ?";
        $countQuery .= " AND w.status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    if (!empty($driver)) {
        $query .= " AND (d.firstname LIKE ? OR d.lastname LIKE ? OR d.email LIKE ? OR d.phone_number LIKE ?)";
        $countQuery .= " AND (d.firstname LIKE ? OR d.lastname LIKE ? OR d.email LIKE ? OR d.phone_number LIKE ?)";
        $searchTerm = "%{$driver}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= "ssss";
    }
    
    if (!empty($from_date)) {
        $query .= " AND DATE(w.created_at) >= ?";
        $countQuery .= " AND DATE(w.created_at) >= ?";
        $params[] = $from_date;
        $types .= "s";
    }
    
    if (!empty($to_date)) {
        $query .= " AND DATE(w.created_at) <= ?";
        $countQuery .= " AND DATE(w.created_at) <= ?";
        $params[] = $to_date;
        $types .= "s";
    }
    
    logActivity("[ADMIN_WITHDRAWAL_LIST] [ID:{$requestId}] Query built: " . $query);
    
    // Get total count
    $countStmt = $conn->prepare($countQuery);
    if (!$countStmt) {
        logActivity("[ADMIN_WITHDRAWAL_LIST_ERROR] [ID:{$requestId}] Count prepare failed: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }
    
    if (!empty($params)) {
        logActivity("[ADMIN_WITHDRAWAL_LIST] [ID:{$requestId}] Count params: " . json_encode($params) . ", types: {$types}");
        $countStmt->bind_param($types, ...$params);
    }
    
    if (!$countStmt->execute()) {
        logActivity("[ADMIN_WITHDRAWAL_LIST_ERROR] [ID:{$requestId}] Count execute failed: " . $countStmt->error);
        $countStmt->close();
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }
    
    $countResult = $countStmt->get_result();
    $totalCount = $countResult->fetch_assoc()['total'] ?? 0;
    $countStmt->close();
    
    logActivity("[ADMIN_WITHDRAWAL_LIST] [ID:{$requestId}] Total count: {$totalCount}");
    
    // Add pagination to main query
    $query .= " ORDER BY w.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    
    // Execute main query
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        logActivity("[ADMIN_WITHDRAWAL_LIST_ERROR] [ID:{$requestId}] Main prepare failed: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }
    
    if (!empty($params)) {
        logActivity("[ADMIN_WITHDRAWAL_LIST] [ID:{$requestId}] Main params: " . json_encode($params) . ", types: {$types}");
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        logActivity("[ADMIN_WITHDRAWAL_LIST_ERROR] [ID:{$requestId}] Main execute failed: " . $stmt->error);
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }
    
    $result = $stmt->get_result();
    
    $withdrawals = [];
    while ($row = $result->fetch_assoc()) {
        $driver_name = trim($row['firstname'] . ' ' . $row['lastname']);
        
        $withdrawals[] = [
            'id' => intval($row['id']),
            'reference' => $row['reference'],
            'amount' => floatval($row['amount']),
            'formatted_amount' => '₦' . number_format($row['amount'], 2),
            'bank_name' => $row['bank_name'],
            'bank_code' => $row['bank_code'],
            'account_number' => $row['account_number'],
            'masked_account' => '****' . substr($row['account_number'], -4),
            'account_name' => $row['account_name'],
            'note' => $row['note'],
            'status' => $row['status'],
            'status_text' => getStatusText($row['status']),
            'admin_notes' => $row['admin_notes'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'processed_at' => $row['processed_at'],
            'processed_by' => $row['processed_by'],
            'date_formatted' => date('M d, Y', strtotime($row['created_at'])),
            'time_formatted' => date('h:i A', strtotime($row['created_at'])),
            'time_ago' => timeAgo($row['created_at']),
            'driver_id' => intval($row['driver_id']),
            'driver_name' => $driver_name,
            'driver_email' => $row['driver_email'],
            'driver_phone' => $row['driver_phone']
        ];
    }
    
    $stmt->close();
    
    logActivity("[ADMIN_WITHDRAWAL_LIST_SUCCESS] [ID:{$requestId}] Retrieved " . count($withdrawals) . " withdrawals out of {$totalCount} total");
    
    echo json_encode([
        'success' => true,
        'withdrawals' => $withdrawals,
        'pagination' => [
            'total' => intval($totalCount),
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($totalCount / $limit),
            'has_more' => ($page * $limit) < $totalCount
        ]
    ]);
}

function getWithdrawalDetails($conn) {
    global $requestId;
    
    $withdrawal_id = isset($_GET['withdrawal_id']) ? intval($_GET['withdrawal_id']) : 0;
    
    logActivity("[ADMIN_WITHDRAWAL_DETAILS] [ID:{$requestId}] Fetching details for withdrawal ID: {$withdrawal_id}");
    
    if (!$withdrawal_id) {
        logActivity("[ADMIN_WITHDRAWAL_DETAILS_ERROR] [ID:{$requestId}] Withdrawal ID is required");
        echo json_encode(['success' => false, 'message' => 'Withdrawal ID is required']);
        return;
    }
    
    $query = "SELECT 
                w.*,
                d.firstname as driver_firstname,
                d.lastname as driver_lastname,
                d.email as driver_email,
                d.phone_number as driver_phone,
                d.wallet_balance,
                a.firstname as admin_firstname,
                a.lastname as admin_lastname
              FROM withdrawal_requests w
              INNER JOIN driver d ON w.driver_id = d.id
              LEFT JOIN admin_tbl a ON w.processed_by = a.unique_id
              WHERE w.id = ?";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        logActivity("[ADMIN_WITHDRAWAL_DETAILS_ERROR] [ID:{$requestId}] Prepare failed: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }
    
    $stmt->bind_param("i", $withdrawal_id);
    
    if (!$stmt->execute()) {
        logActivity("[ADMIN_WITHDRAWAL_DETAILS_ERROR] [ID:{$requestId}] Execute failed: " . $stmt->error);
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        logActivity("[ADMIN_WITHDRAWAL_DETAILS_ERROR] [ID:{$requestId}] Withdrawal not found: {$withdrawal_id}");
        echo json_encode(['success' => false, 'message' => 'Withdrawal not found']);
        return;
    }
    
    $row = $result->fetch_assoc();
    $stmt->close();
    
    $driver_name = trim($row['driver_firstname'] . ' ' . $row['driver_lastname']);
    $processed_by_name = null;
    if ($row['processed_by'] && $row['admin_firstname']) {
        $processed_by_name = trim($row['admin_firstname'] . ' ' . $row['admin_lastname']);
    }
    
    logActivity("[ADMIN_WITHDRAWAL_DETAILS_SUCCESS] [ID:{$requestId}] Withdrawal details retrieved for ID: {$withdrawal_id}");
    
    echo json_encode([
        'success' => true,
        'withdrawal' => [
            'id' => intval($row['id']),
            'reference' => $row['reference'],
            'amount' => floatval($row['amount']),
            'formatted_amount' => '₦' . number_format($row['amount'], 2),
            'bank_name' => $row['bank_name'],
            'bank_code' => $row['bank_code'],
            'account_number' => $row['account_number'],
            'masked_account' => '****' . substr($row['account_number'], -4),
            'account_name' => $row['account_name'],
            'note' => $row['note'],
            'status' => $row['status'],
            'status_text' => getStatusText($row['status']),
            'admin_notes' => $row['admin_notes'],
            'created_at' => $row['created_at'],
            'created_at_formatted' => date('M d, Y h:i A', strtotime($row['created_at'])),
            'updated_at' => $row['updated_at'],
            'updated_at_formatted' => $row['updated_at'] ? date('M d, Y h:i A', strtotime($row['updated_at'])) : null,
            'processed_at' => $row['processed_at'],
            'processed_at_formatted' => $row['processed_at'] ? date('M d, Y h:i A', strtotime($row['processed_at'])) : null,
            'processed_by' => $row['processed_by'],
            'processed_by_name' => $processed_by_name,
            'driver_id' => intval($row['driver_id']),
            'driver_name' => $driver_name,
            'driver_email' => $row['driver_email'],
            'driver_phone' => $row['driver_phone'],
            'driver_wallet_balance' => floatval($row['wallet_balance']),
            'driver_wallet_formatted' => '₦' . number_format($row['wallet_balance'], 2)
        ]
    ]);
}

function getDriverDetails($conn, $params) {
    global $requestId;
    
    $driver_id = isset($params['driver_id']) ? intval($params['driver_id']) : 0;
    
    logActivity("[ADMIN_DRIVER_DETAILS] [ID:{$requestId}] Fetching details for driver ID: {$driver_id}");
    
    if (!$driver_id) {
        logActivity("[ADMIN_DRIVER_DETAILS_ERROR] [ID:{$requestId}] Driver ID is required");
        echo json_encode(['success' => false, 'message' => 'Driver ID is required']);
        return;
    }
    
    $query = "SELECT 
                id,
                firstname,
                lastname,
                email,
                phone,
                wallet_balance,
                status,
                created_at
              FROM driver 
              WHERE id = ?";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        logActivity("[ADMIN_DRIVER_DETAILS_ERROR] [ID:{$requestId}] Prepare failed: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }
    
    $stmt->bind_param("i", $driver_id);
    
    if (!$stmt->execute()) {
        logActivity("[ADMIN_DRIVER_DETAILS_ERROR] [ID:{$requestId}] Execute failed: " . $stmt->error);
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        logActivity("[ADMIN_DRIVER_DETAILS_ERROR] [ID:{$requestId}] Driver not found: {$driver_id}");
        echo json_encode(['success' => false, 'message' => 'Driver not found']);
        return;
    }
    
    $row = $result->fetch_assoc();
    $stmt->close();
    
    $driver_name = trim($row['firstname'] . ' ' . $row['lastname']);
    
    // Get driver's bank accounts
    $bankQuery = "SELECT 
                    bank_name,
                    account_number,
                    account_name,
                    is_default
                  FROM driver_banks 
                  WHERE driver_id = ?";
    
    $bankStmt = $conn->prepare($bankQuery);
    if (!$bankStmt) {
        logActivity("[ADMIN_DRIVER_DETAILS_ERROR] [ID:{$requestId}] Bank query prepare failed: " . $conn->error);
        $banks = [];
    } else {
        $bankStmt->bind_param("i", $driver_id);
        if (!$bankStmt->execute()) {
            logActivity("[ADMIN_DRIVER_DETAILS_ERROR] [ID:{$requestId}] Bank query execute failed: " . $bankStmt->error);
            $banks = [];
        } else {
            $bankResult = $bankStmt->get_result();
            
            $banks = [];
            while ($bank = $bankResult->fetch_assoc()) {
                $banks[] = [
                    'bank_name' => $bank['bank_name'],
                    'account_number' => $bank['account_number'],
                    'masked_account' => '****' . substr($bank['account_number'], -4),
                    'account_name' => $bank['account_name'],
                    'is_default' => (bool)$bank['is_default']
                ];
            }
        }
        $bankStmt->close();
    }
    
    // Get recent withdrawals
    $withdrawalQuery = "SELECT 
                        reference,
                        amount,
                        status,
                        created_at
                        FROM withdrawal_requests 
                        WHERE driver_id = ?
                        ORDER BY created_at DESC
                        LIMIT 5";
    
    $withdrawalStmt = $conn->prepare($withdrawalQuery);
    if (!$withdrawalStmt) {
        logActivity("[ADMIN_DRIVER_DETAILS_ERROR] [ID:{$requestId}] Withdrawal query prepare failed: " . $conn->error);
        $recentWithdrawals = [];
    } else {
        $withdrawalStmt->bind_param("i", $driver_id);
        if (!$withdrawalStmt->execute()) {
            logActivity("[ADMIN_DRIVER_DETAILS_ERROR] [ID:{$requestId}] Withdrawal query execute failed: " . $withdrawalStmt->error);
            $recentWithdrawals = [];
        } else {
            $withdrawalResult = $withdrawalStmt->get_result();
            
            $recentWithdrawals = [];
            while ($wd = $withdrawalResult->fetch_assoc()) {
                $recentWithdrawals[] = [
                    'reference' => $wd['reference'],
                    'amount' => floatval($wd['amount']),
                    'formatted_amount' => '₦' . number_format($wd['amount'], 2),
                    'status' => $wd['status'],
                    'status_text' => getStatusText($wd['status']),
                    'created_at' => $wd['created_at'],
                    'time_ago' => timeAgo($wd['created_at'])
                ];
            }
        }
        $withdrawalStmt->close();
    }
    
    logActivity("[ADMIN_DRIVER_DETAILS_SUCCESS] [ID:{$requestId}] Driver details retrieved for ID: {$driver_id}");
    
    echo json_encode([
        'success' => true,
        'driver' => [
            'id' => intval($row['id']),
            'name' => $driver_name,
            'firstname' => $row['firstname'],
            'lastname' => $row['lastname'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'wallet_balance' => floatval($row['wallet_balance']),
            'formatted_balance' => '₦' . number_format($row['wallet_balance'], 2),
            'status' => $row['status'],
            'joined_date' => date('M d, Y', strtotime($row['created_at'])),
            'banks' => $banks,
            'recent_withdrawals' => $recentWithdrawals
        ]
    ]);
}

function processWithdrawal($conn, $admin_id, $data) {
    global $requestId;
    
    $withdrawal_id = $data['withdrawal_id'] ?? 0;
    $process_action = $data['process_action'] ?? '';
    $admin_notes = trim($data['admin_notes'] ?? '');
    $transaction_ref = trim($data['transaction_ref'] ?? '');
    $reject_reason = trim($data['reject_reason'] ?? '');
    $failed_reason = trim($data['failed_reason'] ?? '');
    
    logActivity("[ADMIN_PROCESS_WITHDRAWAL] [ID:{$requestId}] Processing withdrawal ID: {$withdrawal_id}, Action: {$process_action}");
    
    if (!$withdrawal_id) {
        logActivity("[ADMIN_PROCESS_WITHDRAWAL_ERROR] [ID:{$requestId}] Withdrawal ID is required");
        echo json_encode(['success' => false, 'message' => 'Withdrawal ID is required']);
        return;
    }
    
    if (!in_array($process_action, ['approve', 'reject', 'mark_failed'])) {
        logActivity("[ADMIN_PROCESS_WITHDRAWAL_ERROR] [ID:{$requestId}] Invalid process action: {$process_action}");
        echo json_encode(['success' => false, 'message' => 'Invalid process action']);
        return;
    }
    
    // Start transaction
    $conn->begin_transaction();
    logActivity("[ADMIN_PROCESS_WITHDRAWAL] [ID:{$requestId}] Transaction started");
    
    try {
        // Get withdrawal details
        $stmt = $conn->prepare("
            SELECT w.*, d.wallet_balance 
            FROM withdrawal_requests w
            INNER JOIN driver d ON w.driver_id = d.id
            WHERE w.id = ? FOR UPDATE
        ");
        $stmt->bind_param("i", $withdrawal_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Withdrawal not found');
        }
        
        $withdrawal = $result->fetch_assoc();
        $stmt->close();
        
        logActivity("[ADMIN_PROCESS_WITHDRAWAL] [ID:{$requestId}] Withdrawal details: " . json_encode($withdrawal));
        
        // Check if withdrawal can be processed
        if (!in_array($withdrawal['status'], ['pending', 'processing'])) {
            throw new Exception('This withdrawal cannot be processed in its current state');
        }
        
        $new_status = '';
        $status_note = '';
        
        switch ($process_action) {
            case 'approve':
                if (empty($transaction_ref)) {
                    throw new Exception('Transaction reference is required for approval');
                }
                $new_status = 'completed';
                $status_note = "Approved. Transaction ref: {$transaction_ref}";
                break;
                
            case 'reject':
                if (empty($reject_reason)) {
                    throw new Exception('Rejection reason is required');
                }
                $new_status = 'failed';
                $status_note = "Rejected: {$reject_reason}";
                
                // Refund amount to wallet
                $refundStmt = $conn->prepare("
                    UPDATE driver 
                    SET wallet_balance = wallet_balance + ? 
                    WHERE id = ?
                ");
                $refundStmt->bind_param("di", $withdrawal['amount'], $withdrawal['driver_id']);
                if (!$refundStmt->execute()) {
                    throw new Exception('Failed to refund amount to wallet');
                }
                $refundStmt->close();
                logActivity("[ADMIN_PROCESS_WITHDRAWAL] [ID:{$requestId}] Amount refunded to driver wallet: ₦{$withdrawal['amount']}");
                break;
                
            case 'mark_failed':
                if (empty($failed_reason)) {
                    throw new Exception('Failure reason is required');
                }
                $new_status = 'failed';
                $status_note = "Failed: {$failed_reason}";
                
                // Refund amount to wallet
                $refundStmt = $conn->prepare("
                    UPDATE driver 
                    SET wallet_balance = wallet_balance + ?, total_withdrawn = total_withdrawn - ? 
                    WHERE id = ?
                ");
                $refundStmt->bind_param("ddi", $withdrawal['amount'], $withdrawal['amount'], $withdrawal['driver_id']);
                if (!$refundStmt->execute()) {
                    throw new Exception('Failed to refund amount to wallet');
                }
                $refundStmt->close();
                logActivity("[ADMIN_PROCESS_WITHDRAWAL] [ID:{$requestId}] Amount refunded to driver wallet: ₦{$withdrawal['amount']}");
                break;
        }
        
        // Combine notes
        $combinedNotes = trim($withdrawal['admin_notes'] . "\n[" . date('Y-m-d H:i:s') . "] Admin #{$admin_id}: {$status_note}" . ($admin_notes ? " - {$admin_notes}" : ""));
        
        // Update withdrawal
        $updateStmt = $conn->prepare("
            UPDATE withdrawal_requests 
            SET status = ?,
                admin_notes = ?,
                processed_by = ?,
                processed_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->bind_param("ssii", $new_status, $combinedNotes, $admin_id, $withdrawal_id);
        
        if (!$updateStmt->execute()) {
            throw new Exception('Failed to update withdrawal status');
        }
        
        $updateStmt->close();
        
        // Commit transaction
        $conn->commit();
        logActivity("[ADMIN_PROCESS_WITHDRAWAL] [ID:{$requestId}] Transaction committed");
        
        logActivity("[ADMIN_WITHDRAWAL_PROCESSED] [ID:{$requestId}] Withdrawal {$withdrawal_id} changed to {$new_status} by admin {$admin_id}");
        
        // Create notification for driver
        try {
            $message = '';
            if ($new_status === 'completed') {
                $message = "Your withdrawal of ₦" . number_format($withdrawal['amount'], 2) . " has been completed. Ref: {$transaction_ref}";
            } else {
                $message = "Your withdrawal of ₦" . number_format($withdrawal['amount'], 2) . " has been " . $new_status . ". " . ($reject_reason ?: $failed_reason);
            }
            
            // Uncomment when notification function is available
            // createNotification($conn, [
            //     'user_id' => $withdrawal['driver_id'],
            //     'title' => 'Withdrawal ' . ucfirst($new_status),
            //     'message' => $message,
            //     'type' => $new_status === 'completed' ? 'SUCCESS' : 'WARNING',
            //     'category' => 'withdrawal'
            // ]);
            
            logActivity("[ADMIN_WITHDRAWAL_NOTIFICATION] [ID:{$requestId}] Notification would be sent to driver {$withdrawal['driver_id']}: {$message}");
        } catch (Exception $e) {
            logActivity("[ADMIN_WITHDRAWAL_NOTIFICATION_ERROR] [ID:{$requestId}] " . $e->getMessage());
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Withdrawal processed successfully',
            'new_status' => $new_status
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        logActivity("[ADMIN_PROCESS_WITHDRAWAL_ERROR] [ID:{$requestId}] Transaction rolled back. Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function bulkProcessWithdrawals($conn, $admin_id, $data) {
    global $requestId;
    
    $withdrawal_ids = $data['withdrawal_ids'] ?? [];
    $process_action = $data['process_action'] ?? '';
    $admin_notes = trim($data['admin_notes'] ?? '');
    
    logActivity("[ADMIN_BULK_PROCESS] [ID:{$requestId}] Bulk processing " . count($withdrawal_ids) . " withdrawals. Action: {$process_action}");
    
    if (empty($withdrawal_ids)) {
        logActivity("[ADMIN_BULK_PROCESS_ERROR] [ID:{$requestId}] No withdrawals selected");
        echo json_encode(['success' => false, 'message' => 'No withdrawals selected']);
        return;
    }
    
    if (!in_array($process_action, ['approve', 'reject', 'mark_failed'])) {
        logActivity("[ADMIN_BULK_PROCESS_ERROR] [ID:{$requestId}] Invalid process action: {$process_action}");
        echo json_encode(['success' => false, 'message' => 'Invalid process action']);
        return;
    }
    
    $conn->begin_transaction();
    logActivity("[ADMIN_BULK_PROCESS] [ID:{$requestId}] Transaction started");
    
    try {
        $processed = 0;
        $failed = 0;
        $errors = [];
        
        foreach ($withdrawal_ids as $withdrawal_id) {
            try {
                logActivity("[ADMIN_BULK_PROCESS] [ID:{$requestId}] Processing withdrawal ID: {$withdrawal_id}");
                
                // Get withdrawal details
                $stmt = $conn->prepare("
                    SELECT w.*, d.wallet_balance 
                    FROM withdrawal_requests w
                    INNER JOIN driver d ON w.driver_id = d.id
                    WHERE w.id = ? FOR UPDATE
                ");
                $stmt->bind_param("i", $withdrawal_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    $failed++;
                    $errors[] = "Withdrawal ID {$withdrawal_id} not found";
                    continue;
                }
                
                $withdrawal = $result->fetch_assoc();
                $stmt->close();
                
                // Check if withdrawal can be processed
                if (!in_array($withdrawal['status'], ['pending', 'processing'])) {
                    $failed++;
                    $errors[] = "Withdrawal ID {$withdrawal_id} cannot be processed (status: {$withdrawal['status']})";
                    continue;
                }
                
                $new_status = '';
                
                switch ($process_action) {
                    case 'approve':
                        $new_status = 'completed';
                        break;
                    case 'reject':
                        $new_status = 'failed';
                        // Refund amount to wallet
                        $refundStmt = $conn->prepare("
                            UPDATE driver 
                            SET wallet_balance = wallet_balance + ? 
                            WHERE id = ?
                        ");
                        $refundStmt->bind_param("di", $withdrawal['amount'], $withdrawal['driver_id']);
                        if (!$refundStmt->execute()) {
                            throw new Exception('Failed to refund amount');
                        }
                        $refundStmt->close();
                        logActivity("[ADMIN_BULK_PROCESS] [ID:{$requestId}] Amount refunded to driver {$withdrawal['driver_id']}: ₦{$withdrawal['amount']}");
                        break;
                        
                    case 'mark_failed':
                        $new_status = 'failed';
                        // Refund amount to wallet and adjust total_withdrawn
                        $refundStmt = $conn->prepare("
                            UPDATE driver 
                            SET wallet_balance = wallet_balance + ?, total_withdrawn = total_withdrawn - ? 
                            WHERE id = ?
                        ");
                        $refundStmt->bind_param("ddi", $withdrawal['amount'], $withdrawal['amount'], $withdrawal['driver_id']);
                        if (!$refundStmt->execute()) {
                            throw new Exception('Failed to refund amount');
                        }
                        $refundStmt->close();
                        logActivity("[ADMIN_BULK_PROCESS] [ID:{$requestId}] Amount refunded to driver {$withdrawal['driver_id']}: ₦{$withdrawal['amount']}");
                        break;
                }
                
                // Combine notes
                $combinedNotes = trim($withdrawal['admin_notes'] . "\n[" . date('Y-m-d H:i:s') . "] Admin #{$admin_id}: Bulk {$process_action}" . ($admin_notes ? " - {$admin_notes}" : ""));
                
                // Update withdrawal
                $updateStmt = $conn->prepare("
                    UPDATE withdrawal_requests 
                    SET status = ?,
                        admin_notes = ?,
                        processed_by = ?,
                        processed_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->bind_param("ssii", $new_status, $combinedNotes, $admin_id, $withdrawal_id);
                
                if (!$updateStmt->execute()) {
                    throw new Exception('Failed to update withdrawal');
                }
                
                $updateStmt->close();
                $processed++;
                logActivity("[ADMIN_BULK_PROCESS] [ID:{$requestId}] Withdrawal {$withdrawal_id} processed successfully");
                
            } catch (Exception $e) {
                $failed++;
                $errors[] = "Withdrawal ID {$withdrawal_id}: " . $e->getMessage();
                logActivity("[ADMIN_BULK_PROCESS_ERROR] [ID:{$requestId}] Failed to process {$withdrawal_id}: " . $e->getMessage());
            }
        }
        
        $conn->commit();
        logActivity("[ADMIN_BULK_PROCESS] [ID:{$requestId}] Transaction committed. Processed: {$processed}, Failed: {$failed}");
        
        echo json_encode([
            'success' => true,
            'message' => "Processed {$processed} withdrawals" . ($failed ? ", {$failed} failed" : ""),
            'processed' => $processed,
            'failed' => $failed,
            'errors' => $errors
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        logActivity("[ADMIN_BULK_PROCESS_ERROR] [ID:{$requestId}] Transaction rolled back. Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Helper functions
function getStatusText($status) {
    $texts = [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'completed' => 'Completed',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled'
    ];
    return $texts[$status] ?? ucfirst($status);
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('M j, Y', $time);
}
?>
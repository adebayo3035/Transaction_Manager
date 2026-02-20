<?php
// backend/driver_withdrawal_api.php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
session_start();


// Start logging
$requestId = uniqid('wd_', true);
logActivity("[DRIVER_WITHDRAWAL_API_START] [ID:{$requestId}] Request started");

// Check authentication
if (!isset($_SESSION['driver_id'])) {
    logActivity("[DRIVER_WITHDRAWAL_API_ERROR] [ID:{$requestId}] No session found");
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Please login first']);
    exit;
}

$driver_id = $_SESSION['driver_id'];
$method = $_SERVER['REQUEST_METHOD'];

logActivity("[DRIVER_WITHDRAWAL_API] [ID:{$requestId}] Driver ID: {$driver_id}, Method: {$method}");

try {
    switch ($method) {
        case 'GET':
            handleGet($conn, $driver_id);
            break;
        case 'POST':
            handlePost($conn, $driver_id);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    logActivity("[DRIVER_WITHDRAWAL_API_EXCEPTION] [ID:{$requestId}] Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}

function handleGet($conn, $driver_id) {
    global $requestId;
    
    $action = $_GET['action'] ?? 'history';
    
    logActivity("[DRIVER_WITHDRAWAL_API_GET] [ID:{$requestId}] Action: {$action}");
    
    switch ($action) {
        case 'history':
            getWithdrawalHistory($conn, $driver_id);
            break;
        case 'summary':
            getWithdrawalSummary($conn, $driver_id);
            break;
        case 'details':
            getWithdrawalDetails($conn, $driver_id, $_GET);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

function handlePost($conn, $driver_id) {
    global $requestId;
    
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        logActivity("[DRIVER_WITHDRAWAL_API_POST_ERROR] [ID:{$requestId}] Invalid JSON");
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
        return;
    }
    
    $action = $data['action'] ?? 'create';
    
    logActivity("[DRIVER_WITHDRAWAL_API_POST] [ID:{$requestId}] Action: {$action}");
    
    switch ($action) {
        case 'create':
            createWithdrawal($conn, $driver_id, $data);
            break;
        case 'cancel':
            cancelWithdrawal($conn, $driver_id, $data);
            break;
        case 'verify_balance':
            verifyWithdrawalBalance($conn, $driver_id, $data);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

function getWithdrawalHistory($conn, $driver_id) {
    global $requestId;
    
    // Pagination parameters
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(50, intval($_GET['limit'])) : 20;
    $offset = ($page - 1) * $limit;
    $status = isset($_GET['status']) ? $_GET['status'] : '';
    $from_date = isset($_GET['from_date']) ? $_GET['from_date'] : '';
    $to_date = isset($_GET['to_date']) ? $_GET['to_date'] : '';
    
    // Build query
    $query = "SELECT 
                id,
                reference,
                amount,
                bank_name,
                bank_code,
                account_number,
                account_name,
                note,
                status,
                created_at,
                updated_at,
                admin_notes
              FROM withdrawal_requests 
              WHERE driver_id = ?";
    
    $countQuery = "SELECT COUNT(*) as total FROM withdrawal_requests WHERE driver_id = ?";
    $params = [$driver_id];
    $types = "i";
    
    // Apply filters
    if (!empty($status)) {
        $query .= " AND status = ?";
        $countQuery .= " AND status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    if (!empty($from_date)) {
        $query .= " AND DATE(created_at) >= ?";
        $countQuery .= " AND DATE(created_at) >= ?";
        $params[] = $from_date;
        $types .= "s";
    }
    
    if (!empty($to_date)) {
        $query .= " AND DATE(created_at) <= ?";
        $countQuery .= " AND DATE(created_at) <= ?";
        $params[] = $to_date;
        $types .= "s";
    }
    
    // Get total count
    $countStmt = $conn->prepare($countQuery);
    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalCount = $countResult->fetch_assoc()['total'];
    $countStmt->close();
    
    // Add pagination to main query
    $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    
    // Execute main query
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        logActivity("[WITHDRAWAL_HISTORY_ERROR] [ID:{$requestId}] Prepare failed: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $withdrawals = [];
    while ($row = $result->fetch_assoc()) {
        $withdrawals[] = [
            'id' => $row['id'],
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
            'status_badge' => getStatusBadge($row['status']),
            'status_color' => getStatusColor($row['status']),
            'status_text' => getStatusText($row['status']),
            'admin_notes' => $row['admin_notes'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'date_formatted' => date('M d, Y', strtotime($row['created_at'])),
            'time_formatted' => date('h:i A', strtotime($row['created_at'])),
            'time_ago' => timeAgo($row['created_at'])
        ];
    }
    
    $stmt->close();
    
    logActivity("[WITHDRAWAL_HISTORY_SUCCESS] [ID:{$requestId}] Retrieved " . count($withdrawals) . " withdrawals");
    
    echo json_encode([
        'success' => true,
        'withdrawals' => $withdrawals,
        'pagination' => [
            'total' => $totalCount,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($totalCount / $limit),
            'has_more' => ($page * $limit) < $totalCount
        ]
    ]);
}

function getWithdrawalSummary($conn, $driver_id) {
    global $requestId;
    
    $query = "SELECT 
                COUNT(*) as total_withdrawals,
                COALESCE(SUM(amount), 0) as total_amount,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
                COALESCE(SUM(CASE WHEN status = 'pending' THEN amount END), 0) as pending_amount,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count,
                COALESCE(SUM(CASE WHEN status = 'completed' THEN amount END), 0) as completed_amount,
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_count,
                COALESCE(SUM(CASE WHEN status = 'failed' THEN amount END), 0) as failed_amount,
                COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_count,
                COALESCE(SUM(CASE WHEN status = 'cancelled' THEN amount END), 0) as cancelled_amount,
                MAX(created_at) as last_withdrawal_date
              FROM withdrawal_requests 
              WHERE driver_id = ?";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        logActivity("[WITHDRAWAL_SUMMARY_ERROR] [ID:{$requestId}] Prepare failed: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }
    
    $stmt->bind_param("i", $driver_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $summary = $result->fetch_assoc();
    $stmt->close();
    
    // Get current wallet balance
    $balanceStmt = $conn->prepare("SELECT wallet_balance FROM driver WHERE id = ?");
    $balanceStmt->bind_param("i", $driver_id);
    $balanceStmt->execute();
    $balanceResult = $balanceStmt->get_result();
    $balanceData = $balanceResult->fetch_assoc();
    $balanceStmt->close();
    
    $wallet_balance = $balanceData['wallet_balance'] ?? 0;
    
    logActivity("[WITHDRAWAL_SUMMARY_SUCCESS] [ID:{$requestId}] Retrieved summary for driver {$driver_id}");
    
    echo json_encode([
        'success' => true,
        'summary' => [
            'total_withdrawals' => intval($summary['total_withdrawals']),
            'total_amount' => floatval($summary['total_amount']),
            'formatted_total_amount' => '₦' . number_format($summary['total_amount'], 2),
            'wallet_balance' => floatval($wallet_balance),
            'formatted_wallet_balance' => '₦' . number_format($wallet_balance, 2),
            'pending' => [
                'count' => intval($summary['pending_count']),
                'amount' => floatval($summary['pending_amount']),
                'formatted_amount' => '₦' . number_format($summary['pending_amount'], 2)
            ],
            'completed' => [
                'count' => intval($summary['completed_count']),
                'amount' => floatval($summary['completed_amount']),
                'formatted_amount' => '₦' . number_format($summary['completed_amount'], 2)
            ],
            'failed' => [
                'count' => intval($summary['failed_count']),
                'amount' => floatval($summary['failed_amount']),
                'formatted_amount' => '₦' . number_format($summary['failed_amount'], 2)
            ],
            'cancelled' => [
                'count' => intval($summary['cancelled_count']),
                'amount' => floatval($summary['cancelled_amount']),
                'formatted_amount' => '₦' . number_format($summary['cancelled_amount'], 2)
            ],
            'last_withdrawal_date' => $summary['last_withdrawal_date'],
            'last_withdrawal_formatted' => $summary['last_withdrawal_date'] ? 
                date('M d, Y', strtotime($summary['last_withdrawal_date'])) : 'Never'
        ]
    ]);
}

function getWithdrawalDetails($conn, $driver_id, $params) {
    global $requestId;
    
    $withdrawal_id = $params['withdrawal_id'] ?? 0;
    $reference = $params['reference'] ?? '';
    
    if (!$withdrawal_id && !$reference) {
        echo json_encode(['success' => false, 'message' => 'Withdrawal ID or reference is required']);
        return;
    }
    
    $query = "SELECT 
                id,
                reference,
                amount,
                bank_name,
                bank_code,
                account_number,
                account_name,
                note,
                status,
                admin_notes,
                created_at,
                updated_at,
                processed_at,
                processed_by
              FROM withdrawal_requests 
              WHERE driver_id = ? AND (";
    
    $params = [$driver_id];
    $types = "i";
    
    if ($withdrawal_id) {
        $query .= "id = ?";
        $params[] = $withdrawal_id;
        $types .= "i";
    } else {
        $query .= "reference = ?";
        $params[] = $reference;
        $types .= "s";
    }
    
    $query .= ")";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        logActivity("[WITHDRAWAL_DETAILS_ERROR] [ID:{$requestId}] Prepare failed: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Withdrawal not found']);
        return;
    }
    
    $row = $result->fetch_assoc();
    $stmt->close();
    
    // Get processed by name if available
    $processed_by_name = null;
    if ($row['processed_by']) {
        $adminStmt = $conn->prepare("SELECT firstname, lastname FROM admin_tbl WHERE unique_id = ?");
        $adminStmt->bind_param("i", $row['processed_by']);
        $adminStmt->execute();
        $adminResult = $adminStmt->get_result();
        if ($adminData = $adminResult->fetch_assoc()) {
            $processed_by_name = $adminData['firstname'] . ' ' . $adminData['lastname'];
        }
        $adminStmt->close();
    }
    
    echo json_encode([
        'success' => true,
        'withdrawal' => [
            'id' => $row['id'],
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
            'status_badge' => getStatusBadge($row['status']),
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
            'time_ago' => timeAgo($row['created_at'])
        ]
    ]);
}

function createWithdrawal($conn, $driver_id, $data) {
    global $requestId;
    
    // Validate required fields
    $amount = floatval($data['amount'] ?? 0);
    $bank_code = trim($data['bank_code'] ?? '');
    $account_number = trim($data['account_number'] ?? '');
    $account_name = trim($data['account_name'] ?? '');
    $secret_answer = trim($data['secret_answer'] ?? '');
    $note = trim($data['note'] ?? '');
    
    // Validation
    $errors = [];
    if ($amount < 100) {
        $errors[] = 'Minimum withdrawal amount is ₦100';
    }
    if (empty($bank_code)) $errors[] = 'Bank code is required';
    if (empty($account_number)) $errors[] = 'Account number is required';
    if (empty($account_name)) $errors[] = 'Account name is required';
    if (empty($secret_answer)) $errors[] = 'Secret answer is required for authorization';
    
    if (!preg_match('/^\d{10}$/', $account_number)) {
        $errors[] = 'Account number must be 10 digits';
    }
    
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => implode('. ', $errors)]);
        return;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get driver details and verify secret answer
        $driverStmt = $conn->prepare("
            SELECT wallet_balance, secret_answer, firstname, lastname, email 
            FROM driver 
            WHERE id = ? FOR UPDATE
        ");
        $driverStmt->bind_param("i", $driver_id);
        $driverStmt->execute();
        $driverResult = $driverStmt->get_result();
        
        if ($driverResult->num_rows === 0) {
            throw new Exception('Driver not found or inactive');
        }
        
        $driver = $driverResult->fetch_assoc();
        $driverStmt->close();
        
        // Verify secret answer
        if (!password_verify($secret_answer, $driver['secret_answer'])) {
            logActivity("[WITHDRAWAL_SECRET_FAILED] [ID:{$requestId}] Invalid secret answer for driver {$driver_id}");
            throw new Exception('Invalid secret answer. Authorization failed.');
        }
        
        // Check if secret answer needs rehash
        if (password_needs_rehash($driver['secret_answer'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($secret_answer, PASSWORD_DEFAULT);
            $rehashStmt = $conn->prepare("UPDATE driver SET secret_answer = ? WHERE driver_id = ?");
            $rehashStmt->bind_param("si", $newHash, $driver_id);
            $rehashStmt->execute();
            $rehashStmt->close();
        }
        
        // Check sufficient balance
        if ($driver['wallet_balance'] < $amount) {
            logActivity("[WITHDRAWAL_AMOUNT_FAILED] [ID:{$requestId}] Attempt to withdraw amount greater than wallet balance by driver {$driver_id}");
            throw new Exception('Insufficient wallet balance');
        }


        // Get Bank name from Bank table
        $bankStmt = $conn->prepare("
            SELECT bank_name from banks where bank_code = ? AND is_active = '1'
        ");
        $bankStmt->bind_param("s", $bank_code);
        $bankStmt->execute();
        $bankResult = $bankStmt->get_result();
        
        if ($bankResult->num_rows === 0) {
            throw new Exception('Selected Bank Cannot be found or not Active');
        }
        
        $bank = $bankResult->fetch_assoc();
        $bankStmt->close();

        $bank_name = $bank['bank_name'];
        
        
        // Check for existing pending withdrawal (prevent duplicates)
        $pendingStmt = $conn->prepare("
            SELECT id FROM withdrawal_requests 
            WHERE driver_id = ? AND status = 'pending' 
            AND amount = ? AND DATE(created_at) = CURDATE()
        ");
        $pendingStmt->bind_param("id", $driver_id, $amount);
        $pendingStmt->execute();
        $pendingResult = $pendingStmt->get_result();
        
        if ($pendingResult->num_rows > 0) {
            $pendingStmt->close();
            throw new Exception('You already have a pending withdrawal request for this amount today');
        }
        $pendingStmt->close();
        
        // Generate unique reference
        $reference = generateWithdrawalReference($conn, $driver_id);
        
        // Create withdrawal request
        $insertStmt = $conn->prepare("
            INSERT INTO withdrawal_requests 
            (driver_id, reference, amount, bank_name, bank_code, account_number, account_name, note, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        
        $insertStmt->bind_param(
            "isdsssss", 
            $driver_id, 
            $reference, 
            $amount, 
            $bank_name,
            $bank_code,
            $account_number,
            $account_name,
            $note
        );
        
        if (!$insertStmt->execute()) {
            throw new Exception('Failed to create withdrawal request: ' . $insertStmt->error);
        }
        
        $withdrawal_id = $insertStmt->insert_id;
        $insertStmt->close();
        
        // Deduct from wallet (pending amount)
        $updateStmt = $conn->prepare("
            UPDATE driver 
            SET wallet_balance = wallet_balance - ? 
            WHERE id = ? AND wallet_balance >= ?
        ");
        
        $updateStmt->bind_param("dii", $amount, $driver_id, $amount);
        
        if (!$updateStmt->execute() || $updateStmt->affected_rows === 0) {
            throw new Exception('Failed to update wallet balance');
        }
        
        $updateStmt->close();
        
        // Commit transaction
        $conn->commit();
        
        logActivity("[WITHDRAWAL_CREATED] [ID:{$requestId}] Withdrawal created: {$reference}, Amount: ₦{$amount}");
        
        // // Create notification for driver
        // try {
        //     createNotification($conn, [
        //         'user_id' => $driver_id,
        //         'title' => 'Withdrawal Request Submitted',
        //         'message' => "Your withdrawal request of ₦" . number_format($amount, 2) . " has been submitted. Reference: {$reference}",
        //         'type' => 'INFO',
        //         'category' => 'withdrawal'
        //     ]);
        // } catch (Exception $e) {
        //     logActivity("[WITHDRAWAL_NOTIFICATION_ERROR] [ID:{$requestId}] " . $e->getMessage());
        // }
        
        // Get updated balance
        $newBalanceStmt = $conn->prepare("SELECT wallet_balance FROM driver WHERE id = ?");
        $newBalanceStmt->bind_param("i", $driver_id);
        $newBalanceStmt->execute();
        $newBalanceResult = $newBalanceStmt->get_result();
        $newBalance = $newBalanceResult->fetch_assoc()['wallet_balance'];
        $newBalanceStmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'Withdrawal request submitted successfully',
            'withdrawal' => [
                'id' => $withdrawal_id,
                'reference' => $reference,
                'amount' => $amount,
                'formatted_amount' => '₦' . number_format($amount, 2),
                'bank_name' => $bank_name,
                'masked_account' => '****' . substr($account_number, -4),
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s'),
                'time_ago' => 'Just now'
            ],
            'new_balance' => floatval($newBalance),
            'formatted_new_balance' => '₦' . number_format($newBalance, 2)
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        logActivity("[WITHDRAWAL_CREATE_ERROR] [ID:{$requestId}] " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Withdrawal failed']);

    }
}

function cancelWithdrawal($conn, $driver_id, $data) {
    global $requestId;
    
    $withdrawal_id = $data['withdrawal_id'] ?? 0;
    $secret_answer = trim($data['secret_answer'] ?? '');
    $cancel_reason = trim($data['cancel_reason'] ?? 'User cancelled');
    
    if (!$withdrawal_id) {
        echo json_encode(['success' => false, 'message' => 'Withdrawal ID is required']);
        return;
    }
    
    if (empty($secret_answer)) {
        echo json_encode(['success' => false, 'message' => 'Secret answer is required']);
        return;
    }
    
    $conn->begin_transaction();
    
    try {
        // Verify driver and secret answer
        $driverStmt = $conn->prepare("SELECT secret_answer, wallet_balance FROM driver_tbl WHERE unique_id = ?");
        $driverStmt->bind_param("i", $driver_id);
        $driverStmt->execute();
        $driverResult = $driverStmt->get_result();
        
        if ($driverResult->num_rows === 0) {
            throw new Exception('Driver not found');
        }
        
        $driver = $driverResult->fetch_assoc();
        $driverStmt->close();
        
        if (!password_verify($secret_answer, $driver['secret_answer'])) {
            throw new Exception('Invalid secret answer');
        }
        
        // Get withdrawal details
        $withdrawalStmt = $conn->prepare("
            SELECT id, amount, status, reference 
            FROM withdrawal_requests 
            WHERE id = ? AND driver_id = ?
        ");
        $withdrawalStmt->bind_param("ii", $withdrawal_id, $driver_id);
        $withdrawalStmt->execute();
        $withdrawalResult = $withdrawalStmt->get_result();
        
        if ($withdrawalResult->num_rows === 0) {
            $withdrawalStmt->close();
            throw new Exception('Withdrawal not found');
        }
        
        $withdrawal = $withdrawalResult->fetch_assoc();
        $withdrawalStmt->close();
        
        // Check if withdrawal can be cancelled
        if ($withdrawal['status'] !== 'pending') {
            throw new Exception('Only pending withdrawals can be cancelled');
        }
        
        // Update withdrawal status
        $updateStmt = $conn->prepare("
            UPDATE withdrawal_requests 
            SET status = 'cancelled', 
                admin_notes = CONCAT(IFNULL(admin_notes, ''), ' | Cancelled by user: ', ?),
                updated_at = NOW() 
            WHERE id = ? AND driver_id = ?
        ");
        $updateStmt->bind_param("sii", $cancel_reason, $withdrawal_id, $driver_id);
        
        if (!$updateStmt->execute()) {
            throw new Exception('Failed to cancel withdrawal');
        }
        
        $updateStmt->close();
        
        // Refund amount to wallet
        $refundStmt = $conn->prepare("
            UPDATE driver_tbl 
            SET wallet_balance = wallet_balance + ? 
            WHERE unique_id = ?
        ");
        $refundStmt->bind_param("di", $withdrawal['amount'], $driver_id);
        
        if (!$refundStmt->execute()) {
            throw new Exception('Failed to refund amount');
        }
        
        $refundStmt->close();
        
        $conn->commit();
        
        logActivity("[WITHDRAWAL_CANCELLED] [ID:{$requestId}] Withdrawal {$withdrawal['reference']} cancelled, refunded ₦{$withdrawal['amount']}");
        
        // Get new balance
        $balanceStmt = $conn->prepare("SELECT wallet_balance FROM driver_tbl WHERE unique_id = ?");
        $balanceStmt->bind_param("i", $driver_id);
        $balanceStmt->execute();
        $balanceResult = $balanceStmt->get_result();
        $newBalance = $balanceResult->fetch_assoc()['wallet_balance'];
        $balanceStmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'Withdrawal cancelled and amount refunded',
            'refunded_amount' => $withdrawal['amount'],
            'new_balance' => floatval($newBalance),
            'formatted_new_balance' => '₦' . number_format($newBalance, 2)
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        logActivity("[WITHDRAWAL_CANCEL_ERROR] [ID:{$requestId}] " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function verifyWithdrawalBalance($conn, $driver_id, $data) {
    global $requestId;
    
    $amount = floatval($data['amount'] ?? 0);
    
    if ($amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid amount']);
        return;
    }
    
    $stmt = $conn->prepare("SELECT wallet_balance FROM driver_tbl WHERE unique_id = ?");
    $stmt->bind_param("i", $driver_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $driver = $result->fetch_assoc();
    $stmt->close();
    
    $balance = floatval($driver['wallet_balance'] ?? 0);
    $has_sufficient = $balance >= $amount;
    
    echo json_encode([
        'success' => true,
        'has_sufficient' => $has_sufficient,
        'balance' => $balance,
        'formatted_balance' => '₦' . number_format($balance, 2),
        'amount' => $amount,
        'formatted_amount' => '₦' . number_format($amount, 2),
        'difference' => $has_sufficient ? 0 : ($amount - $balance),
        'formatted_difference' => '₦' . number_format($amount - $balance, 2)
    ]);
}

function generateWithdrawalReference($conn, $driver_id) {
    $prefix = 'WDV';
    $date = date('Ymd');
    $unique = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    $reference = $prefix . '-' . $date . '-' . $unique . '-' . $driver_id;
    
    // Check if reference exists
    $stmt = $conn->prepare("SELECT id FROM withdrawal_requests WHERE reference = ?");
    $stmt->bind_param("s", $reference);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        // Reference exists, generate new one
        $reference = $prefix . '-' . $date . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT) . '-' . $driver_id;
    }
    
    $stmt->close();
    
    return $reference;
}

// Helper functions
function getStatusBadge($status) {
    $badges = [
        'pending' => '<span class="badge badge-warning">Pending</span>',
        'processing' => '<span class="badge badge-info">Processing</span>',
        'completed' => '<span class="badge badge-success">Completed</span>',
        'failed' => '<span class="badge badge-danger">Failed</span>',
        'cancelled' => '<span class="badge badge-secondary">Cancelled</span>'
    ];
    return $badges[$status] ?? '<span class="badge badge-secondary">Unknown</span>';
}

function getStatusColor($status) {
    $colors = [
        'pending' => 'warning',
        'processing' => 'info',
        'completed' => 'success',
        'failed' => 'danger',
        'cancelled' => 'secondary'
    ];
    return $colors[$status] ?? 'secondary';
}

function getStatusText($status) {
    $texts = [
        'pending' => 'Pending Approval',
        'processing' => 'Processing',
        'completed' => 'Completed',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled'
    ];
    return $texts[$status] ?? 'Unknown';
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

// Close connection if this is the end
// Note: Connection closing is handled by the calling script

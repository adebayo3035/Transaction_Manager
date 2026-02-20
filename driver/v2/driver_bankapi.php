<?php
// backend/driver_bank_crud.php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
session_start();

// Start logging
$requestId = uniqid('drv_bank_', true);
logActivity("[DRIVER_BANK_CRUD_START] [ID:{$requestId}] Request started");

// Check authentication
if (!isset($_SESSION['driver_id'])) {
    logActivity("[DRIVER_BANK_CRUD_ERROR] [ID:{$requestId}] No session found");
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Please login first']);
    exit;
}

$driver_id = $_SESSION['driver_id'];
$method = $_SERVER['REQUEST_METHOD'];

logActivity("[DRIVER_BANK_CRUD] [ID:{$requestId}] Driver ID: {$driver_id}, Method: {$method}");

try {
    switch ($method) {
        case 'GET':
            handleGet($conn, $driver_id);
            break;
        case 'POST':
            handlePost($conn, $driver_id);
            break;
        case 'PUT':
            handlePut($conn, $driver_id);
            break;
        case 'DELETE':
            handleDelete($conn, $driver_id);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    logActivity("[DRIVER_BANK_CRUD_EXCEPTION] [ID:{$requestId}] Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}

function handleGet($conn, $driver_id) {
    global $requestId;
    
    $action = $_GET['action'] ?? 'list';
    
    logActivity("[DRIVER_BANK_CRUD_GET] [ID:{$requestId}] Action: {$action}");
    
    switch ($action) {
        case 'list':
            getDriverBanks($conn, $driver_id);
            break;
        case 'get':
            getDriverBank($conn, $driver_id, $_GET);
            break;
        case 'default':
            getDefaultBank($conn, $driver_id);
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
        logActivity("[DRIVER_BANK_CRUD_POST_ERROR] [ID:{$requestId}] Invalid JSON");
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
        return;
    }
    
    $action = $data['action'] ?? 'add';
    
    logActivity("[DRIVER_BANK_CRUD_POST] [ID:{$requestId}] Action: {$action}");
    
    switch ($action) {
        case 'add':
            addDriverBank($conn, $driver_id, $data);
            break;
        case 'verify':
            verifyBankDetails($conn, $driver_id, $data);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

function handlePut($conn, $driver_id) {
    global $requestId;
    
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        logActivity("[DRIVER_BANK_CRUD_PUT_ERROR] [ID:{$requestId}] Invalid JSON");
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
        return;
    }
    
    $action = $data['action'] ?? 'update';
    
    logActivity("[DRIVER_BANK_CRUD_PUT] [ID:{$requestId}] Action: {$action}");
    
    switch ($action) {
        case 'update':
            updateDriverBank($conn, $driver_id, $data);
            break;
        case 'set_default':
            setDefaultBank($conn, $driver_id, $data);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

function handleDelete($conn, $driver_id) {
    global $requestId;
    
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        logActivity("[DRIVER_BANK_CRUD_DELETE_ERROR] [ID:{$requestId}] Invalid JSON");
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
        return;
    }
    
    $bank_id = $data['bank_id'] ?? 0;
    $secret_answer = $data['secret_answer'] ?? '';
    
    if (!$bank_id) {
        echo json_encode(['success' => false, 'message' => 'Bank ID is required']);
        return;
    }
    
    if (empty($secret_answer)) {
        echo json_encode(['success' => false, 'message' => 'Secret answer is required for authorization']);
        return;
    }
    
    deleteDriverBank($conn, $driver_id, $bank_id, $secret_answer);
}

function getDriverBanks($conn, $driver_id) {
    global $requestId;
    
    $stmt = $conn->prepare("
        SELECT 
            id,
            bank_name,
            bank_code,
            account_number,
            account_name,
            is_default,
            created_at,
            updated_at
        FROM driver_banks 
        WHERE driver_id = ? 
        ORDER BY is_default DESC, created_at DESC
    ");
    
    if (!$stmt) {
        logActivity("[GET_BANKS_ERROR] [ID:{$requestId}] Prepare failed: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }
    
    $stmt->bind_param("i", $driver_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $banks = [];
    while ($row = $result->fetch_assoc()) {
        $banks[] = [
            'id' => $row['id'],
            'bank_name' => $row['bank_name'],
            'bank_code' => $row['bank_code'],
            'account_number' => $row['account_number'],
            'masked_account' => '****' . substr($row['account_number'], -4),
            'account_name' => $row['account_name'],
            'is_default' => (bool)$row['is_default'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }
    
    $stmt->close();
    
    logActivity("[GET_BANKS_SUCCESS] [ID:{$requestId}] Retrieved " . count($banks) . " banks for driver {$driver_id}");
    
    echo json_encode([
        'success' => true,
        'banks' => $banks,
        'count' => count($banks)
    ]);
}

function getDriverBank($conn, $driver_id, $params) {
    global $requestId;
    
    $bank_id = $params['bank_id'] ?? 0;
    
    if (!$bank_id) {
        echo json_encode(['success' => false, 'message' => 'Bank ID is required']);
        return;
    }
    
    $stmt = $conn->prepare("
        SELECT 
            id,
            bank_name,
            bank_code,
            account_number,
            account_name,
            is_default,
            created_at,
            updated_at
        FROM driver_banks 
        WHERE id = ? AND driver_id = ?
    ");
    
    if (!$stmt) {
        logActivity("[GET_BANK_ERROR] [ID:{$requestId}] Prepare failed: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }
    
    $stmt->bind_param("ii", $bank_id, $driver_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Bank not found']);
        return;
    }
    
    $row = $result->fetch_assoc();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'bank' => [
            'id' => $row['id'],
            'bank_name' => $row['bank_name'],
            'bank_code' => $row['bank_code'],
            'account_number' => $row['account_number'],
            'account_name' => $row['account_name'],
            'is_default' => (bool)$row['is_default'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ]
    ]);
}

function getDefaultBank($conn, $driver_id) {
    global $requestId;
    
    $stmt = $conn->prepare("
        SELECT 
            id,
            bank_name,
            bank_code,
            account_number,
            account_name
        FROM driver_banks 
        WHERE driver_id = ? AND is_default = 1 
        LIMIT 1
    ");
    
    if (!$stmt) {
        logActivity("[GET_DEFAULT_BANK_ERROR] [ID:{$requestId}] Prepare failed: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }
    
    $stmt->bind_param("i", $driver_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'No default bank found']);
        return;
    }
    
    $row = $result->fetch_assoc();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'bank' => [
            'id' => $row['id'],
            'bank_name' => $row['bank_name'],
            'bank_code' => $row['bank_code'],
            'account_number' => '****' . substr($row['account_number'], -4),
            'account_name' => $row['account_name']
        ]
    ]);
}

function verifyBankDetails($conn, $driver_id, $data) {
    global $requestId;
    
    $bank_code = trim($data['bank_code'] ?? '');
    $account_number = trim($data['account_number'] ?? '');
    
    if (empty($bank_code) || empty($account_number)) {
        echo json_encode(['success' => false, 'message' => 'Bank code and account number are required']);
        return;
    }
    
    if (!preg_match('/^\d{10}$/', $account_number)) {
        echo json_encode(['success' => false, 'message' => 'Invalid account number format (must be 10 digits)']);
        return;
    }
    
    // Get bank name from banks table
    $bankStmt = $conn->prepare("SELECT bank_name FROM banks WHERE bank_code = ? AND is_active = 1");
    $bankStmt->bind_param("s", $bank_code);
    $bankStmt->execute();
    $bankResult = $bankStmt->get_result();
    
    if ($bankResult->num_rows === 0) {
        $bankStmt->close();
        echo json_encode(['success' => false, 'message' => 'Invalid bank selected']);
        return;
    }
    
    $bankData = $bankResult->fetch_assoc();
    $bankStmt->close();
    
    // Simulate bank verification - Replace with actual bank API integration
    // This is where you'd call Paystack, Flutterwave, etc.
    $verificationResult = simulateAccountVerification($bank_code, $account_number);
    
    if (!$verificationResult['success']) {
        logActivity("[BANK_VERIFY_FAILED] [ID:{$requestId}] Verification failed for {$account_number}");
        echo json_encode(['success' => false, 'message' => $verificationResult['message'] ?? 'Account verification failed']);
        return;
    }
    
    logActivity("[BANK_VERIFY_SUCCESS] [ID:{$requestId}] Account verified: {$account_number}");
    
    echo json_encode([
        'success' => true,
        'account_name' => $verificationResult['account_name'],
        'bank_name' => $bankData['bank_name'],
        'bank_code' => $bank_code
    ]);
}

function addDriverBank($conn, $driver_id, $data) {
    global $requestId;
    
    $bank_code = trim($data['bank_code'] ?? '');
    $account_number = trim($data['account_number'] ?? '');
    $account_name = trim($data['account_name'] ?? '');
    $secret_answer = trim($data['secret_answer'] ?? '');
    $set_default = isset($data['set_default']) ? (bool)$data['set_default'] : false;
    
    // Validate inputs
    $errors = [];
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
    
    // Verify secret answer
    if (!verifyDriverSecretAnswer($conn, $driver_id, $secret_answer)) {
        logActivity("[ADD_BANK_SECRET_FAILED] [ID:{$requestId}] Invalid secret answer for driver {$driver_id}");
        echo json_encode(['success' => false, 'message' => 'Invalid secret answer. Authorization failed.']);
        return;
    }
    
    // Check if driver has reached maximum banks (3)
    $countStmt = $conn->prepare("SELECT COUNT(*) as bank_count FROM driver_banks WHERE driver_id = ?");
    $countStmt->bind_param("i", $driver_id);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $bankCount = $countResult->fetch_assoc()['bank_count'];
    $countStmt->close();
    
    if ($bankCount >= 3) {
        logActivity("[ADD_BANK_LIMIT] [ID:{$requestId}] Driver {$driver_id} has reached maximum banks ({$bankCount})");
        echo json_encode(['success' => false, 'message' => 'You cannot have more than 3 banks. Please delete a bank before adding a new one.']);
        return;
    }
    
    // Check if this bank account already exists for this driver
    $dupStmt = $conn->prepare("
        SELECT id FROM driver_banks 
        WHERE driver_id = ? AND bank_code = ? AND account_number = ?
    ");
    $dupStmt->bind_param("iss", $driver_id, $bank_code, $account_number);
    $dupStmt->execute();
    $dupResult = $dupStmt->get_result();
    
    if ($dupResult->num_rows > 0) {
        $dupStmt->close();
        logActivity("[ADD_BANK_DUPLICATE] [ID:{$requestId}] Duplicate bank for driver {$driver_id}");
        echo json_encode(['success' => false, 'message' => 'This bank account has already been added to your profile.']);
        return;
    }
    $dupStmt->close();
    
    // Check if driver already has this bank (same bank code)
    $bankCheckStmt = $conn->prepare("
        SELECT id FROM driver_banks 
        WHERE driver_id = ? AND bank_code = ?
    ");
    $bankCheckStmt->bind_param("is", $driver_id, $bank_code);
    $bankCheckStmt->execute();
    $bankCheckResult = $bankCheckStmt->get_result();
    $hasSameBank = $bankCheckResult->num_rows > 0;
    $bankCheckStmt->close();
    
    // Get bank name from banks table
    $bankNameStmt = $conn->prepare("SELECT bank_name FROM banks WHERE bank_code = ? AND is_active = 1");
    $bankNameStmt->bind_param("s", $bank_code);
    $bankNameStmt->execute();
    $bankNameResult = $bankNameStmt->get_result();
    
    if ($bankNameResult->num_rows === 0) {
        $bankNameStmt->close();
        echo json_encode(['success' => false, 'message' => 'Invalid bank selected']);
        return;
    }
    
    $bankData = $bankNameResult->fetch_assoc();
    $bank_name = $bankData['bank_name'];
    $bankNameStmt->close();
    
    $conn->begin_transaction();
    
    try {
        // If setting as default, remove default from other banks
        if ($set_default) {
            $resetDefault = $conn->prepare("UPDATE driver_banks SET is_default = 0 WHERE driver_id = ?");
            $resetDefault->bind_param("i", $driver_id);
            $resetDefault->execute();
            $resetDefault->close();
        } else if ($bankCount === 0) {
            // If this is the first bank, make it default automatically
            $set_default = true;
        }
        
        // Insert new bank
        $is_default = $set_default ? 1 : 0;
        $stmt = $conn->prepare("
            INSERT INTO driver_banks 
            (driver_id, bank_name, bank_code, account_number, account_name, is_default) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param(
            "issssi", 
            $driver_id, 
            $bank_name, 
            $bank_code, 
            $account_number, 
            $account_name, 
            $is_default
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to save bank: " . $stmt->error);
        }
        
        $bank_id = $stmt->insert_id;
        $stmt->close();
        
        $conn->commit();
        
        logActivity("[ADD_BANK_SUCCESS] [ID:{$requestId}] Bank added for driver {$driver_id}: {$bank_code} - {$account_number}");
        
        echo json_encode([
            'success' => true,
            'message' => 'Bank added successfully',
            'bank' => [
                'id' => $bank_id,
                'bank_name' => $bank_name,
                'bank_code' => $bank_code,
                'account_number' => $account_number,
                'masked_account' => '****' . substr($account_number, -4),
                'account_name' => $account_name,
                'is_default' => $set_default,
                'warning' => $hasSameBank ? 'Note: You now have multiple accounts with the same bank.' : null
            ]
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        logActivity("[ADD_BANK_ERROR] [ID:{$requestId}] Failed to add bank: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to add bank: ' . $e->getMessage()]);
    }
}

function updateDriverBank($conn, $driver_id, $data) {
    global $requestId;
    
    $bank_id = $data['bank_id'] ?? 0;
    $account_name = trim($data['account_name'] ?? '');
    $secret_answer = trim($data['secret_answer'] ?? '');
    $set_default = isset($data['set_default']) ? (bool)$data['set_default'] : false;
    
    if (!$bank_id) {
        echo json_encode(['success' => false, 'message' => 'Bank ID is required']);
        return;
    }
    
    if (empty($account_name)) {
        echo json_encode(['success' => false, 'message' => 'Account name is required']);
        return;
    }
    
    if (empty($secret_answer)) {
        echo json_encode(['success' => false, 'message' => 'Secret answer is required for authorization']);
        return;
    }
    
    // Verify secret answer
    if (!verifyDriverSecretAnswer($conn, $driver_id, $secret_answer)) {
        logActivity("[UPDATE_BANK_SECRET_FAILED] [ID:{$requestId}] Invalid secret answer for driver {$driver_id}");
        echo json_encode(['success' => false, 'message' => 'Invalid secret answer. Authorization failed.']);
        return;
    }
    
    // Check if bank belongs to driver
    $checkStmt = $conn->prepare("SELECT id FROM driver_banks WHERE id = ? AND driver_id = ?");
    $checkStmt->bind_param("ii", $bank_id, $driver_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        $checkStmt->close();
        echo json_encode(['success' => false, 'message' => 'Bank not found or does not belong to you']);
        return;
    }
    $checkStmt->close();
    
    $conn->begin_transaction();
    
    try {
        // If setting as default, remove default from other banks
        if ($set_default) {
            $resetDefault = $conn->prepare("UPDATE driver_banks SET is_default = 0 WHERE driver_id = ?");
            $resetDefault->bind_param("i", $driver_id);
            $resetDefault->execute();
            $resetDefault->close();
        }
        
        // Update bank
        $is_default = $set_default ? 1 : 0;
        $stmt = $conn->prepare("
            UPDATE driver_banks 
            SET account_name = ?, is_default = ? 
            WHERE id = ? AND driver_id = ?
        ");
        
        $stmt->bind_param("siii", $account_name, $is_default, $bank_id, $driver_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update bank: " . $stmt->error);
        }
        
        $stmt->close();
        $conn->commit();
        
        logActivity("[UPDATE_BANK_SUCCESS] [ID:{$requestId}] Bank updated for driver {$driver_id}: ID {$bank_id}");
        
        echo json_encode([
            'success' => true,
            'message' => 'Bank updated successfully',
            'is_default' => $set_default
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        logActivity("[UPDATE_BANK_ERROR] [ID:{$requestId}] Failed to update bank: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to update bank']);
    }
}

function setDefaultBank($conn, $driver_id, $data) {
    global $requestId;
    
    $bank_id = $data['bank_id'] ?? 0;
    $secret_answer = trim($data['secret_answer'] ?? '');
    
    if (!$bank_id) {
        echo json_encode(['success' => false, 'message' => 'Bank ID is required']);
        return;
    }
    
    if (empty($secret_answer)) {
        echo json_encode(['success' => false, 'message' => 'Secret answer is required for authorization']);
        return;
    }
    
    // Verify secret answer
    if (!verifyDriverSecretAnswer($conn, $driver_id, $secret_answer)) {
        logActivity("[SET_DEFAULT_SECRET_FAILED] [ID:{$requestId}] Invalid secret answer for driver {$driver_id}");
        echo json_encode(['success' => false, 'message' => 'Invalid secret answer. Authorization failed.']);
        return;
    }
    
    // Check if bank belongs to driver
    $checkStmt = $conn->prepare("SELECT id FROM driver_banks WHERE id = ? AND driver_id = ?");
    $checkStmt->bind_param("ii", $bank_id, $driver_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        $checkStmt->close();
        echo json_encode(['success' => false, 'message' => 'Bank not found or does not belong to you']);
        return;
    }
    $checkStmt->close();
    
    $conn->begin_transaction();
    
    try {
        // Remove default from all banks
        $resetDefault = $conn->prepare("UPDATE driver_banks SET is_default = 0 WHERE driver_id = ?");
        $resetDefault->bind_param("i", $driver_id);
        $resetDefault->execute();
        $resetDefault->close();
        
        // Set new default
        $setDefault = $conn->prepare("UPDATE driver_banks SET is_default = 1 WHERE id = ?");
        $setDefault->bind_param("i", $bank_id);
        $setDefault->execute();
        $setDefault->close();
        
        $conn->commit();
        
        logActivity("[SET_DEFAULT_SUCCESS] [ID:{$requestId}] Default bank set for driver {$driver_id}: ID {$bank_id}");
        
        echo json_encode([
            'success' => true,
            'message' => 'Default bank updated successfully'
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        logActivity("[SET_DEFAULT_ERROR] [ID:{$requestId}] Failed to set default bank: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to set default bank']);
    }
}

function deleteDriverBank($conn, $driver_id, $bank_id, $secret_answer) {
    global $requestId;
    
    // Verify secret answer
    if (!verifyDriverSecretAnswer($conn, $driver_id, $secret_answer)) {
        logActivity("[DELETE_BANK_SECRET_FAILED] [ID:{$requestId}] Invalid secret answer for driver {$driver_id}");
        echo json_encode(['success' => false, 'message' => 'Invalid secret answer. Authorization failed.']);
        return;
    }
    
    // Check if bank belongs to driver and get its details
    $checkStmt = $conn->prepare("
        SELECT is_default, bank_name, account_number 
        FROM driver_banks 
        WHERE id = ? AND driver_id = ?
    ");
    $checkStmt->bind_param("ii", $bank_id, $driver_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        $checkStmt->close();
        echo json_encode(['success' => false, 'message' => 'Bank not found or does not belong to you']);
        return;
    }
    
    $bank = $checkResult->fetch_assoc();
    $wasDefault = $bank['is_default'];
    $checkStmt->close();
    
    $conn->begin_transaction();
    
    try {
        // Delete the bank
        $deleteStmt = $conn->prepare("DELETE FROM driver_banks WHERE id = ? AND driver_id = ?");
        $deleteStmt->bind_param("ii", $bank_id, $driver_id);
        
        if (!$deleteStmt->execute()) {
            throw new Exception("Failed to delete bank");
        }
        
        $deleteStmt->close();
        
        // If this was the default bank, set another bank as default if available
        if ($wasDefault) {
            $newDefaultStmt = $conn->prepare("
                UPDATE driver_banks 
                SET is_default = 1 
                WHERE driver_id = ? 
                ORDER BY created_at ASC 
                LIMIT 1
            ");
            $newDefaultStmt->bind_param("i", $driver_id);
            $newDefaultStmt->execute();
            $newDefaultStmt->close();
        }
        
        $conn->commit();
        
        logActivity("[DELETE_BANK_SUCCESS] [ID:{$requestId}] Bank deleted for driver {$driver_id}: ID {$bank_id}");
        
        echo json_encode([
            'success' => true,
            'message' => 'Bank deleted successfully',
            'was_default' => $wasDefault
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        logActivity("[DELETE_BANK_ERROR] [ID:{$requestId}] Failed to delete bank: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to delete bank']);
    }
}

function verifyDriverSecretAnswer($conn, $driver_id, $secret_answer) {
    $stmt = $conn->prepare("SELECT secret_answer FROM driver WHERE id = ?");
    $stmt->bind_param("i", $driver_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        return false;
    }
    
    $driver = $result->fetch_assoc();
    $stmt->close();
    
    return password_verify($secret_answer, $driver['secret_answer']);
}

function simulateAccountVerification($bank_code, $account_number) {
    // This is a simulation - Replace with actual bank API integration
    // In production, integrate with Paystack, Flutterwave, etc.
    
    $testNames = [
        '044' => 'JOHN DOE', // Access Bank
        '011' => 'JANE SMITH', // First Bank
        '058' => 'MICHAEL JOHNSON', // GTBank
        '033' => 'SARAH WILLIAMS', // UBA
        '057' => 'DAVID BROWN' // Zenith Bank
    ];
    
    // Simulate validation
    if (strlen($account_number) !== 10) {
        return ['success' => false, 'message' => 'Invalid account number length'];
    }
    
    // Check if account number is all zeros (invalid)
    if ($account_number === '0000000000') {
        return ['success' => false, 'message' => 'Invalid account number'];
    }
    
    // Return a name based on bank code (in production, this comes from bank API)
    $account_name = $testNames[$bank_code] ?? $_SESSION['driver_name'];
    
    return [
        'success' => true,
        'account_name' => $account_name,
        'account_number' => $account_number,
        'bank_code' => $bank_code
    ];
}

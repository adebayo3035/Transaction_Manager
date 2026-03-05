<?php
// backend/import_banks_api.php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
session_start();

// Start logging
$requestId = uniqid('import_', true);
logActivity("[BANK_IMPORT_API_START] [ID:{$requestId}] Bank import API request started");

// Check authentication
if (!isset($_SESSION['unique_id'])) {
    logActivity("[BANK_IMPORT_API_ERROR] [ID:{$requestId}] No session found");
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Please login first']);
    exit;
}

// Check if user is admin
$user_id = $_SESSION['unique_id'];
$user_role = $_SESSION['role'] ?? '';

if (!in_array($user_role, ['Super Admin', 'Admin'])) {
    logActivity("[BANK_IMPORT_API_ERROR] [ID:{$requestId}] Insufficient permissions for user: {$user_id}");
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit;
}

// Check if it's a POST request with file
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logActivity("[BANK_IMPORT_API_ERROR] [ID:{$requestId}] Invalid request method: {$_SERVER['REQUEST_METHOD']}");
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $error = isset($_FILES['file']) ? $_FILES['file']['error'] : 'No file uploaded';
    logActivity("[BANK_IMPORT_API_ERROR] [ID:{$requestId}] File upload error: " . $error);
    echo json_encode(['success' => false, 'message' => 'File upload failed']);
    exit;
}

$file = $_FILES['file'];

// Validate file type
if ($file['type'] !== 'application/json' && !pathinfo($file['name'], PATHINFO_EXTENSION) === 'json') {
    logActivity("[BANK_IMPORT_API_ERROR] [ID:{$requestId}] Invalid file type: {$file['type']}");
    echo json_encode(['success' => false, 'message' => 'Only JSON files are allowed']);
    exit;
}

// Validate file size (5MB max)
if ($file['size'] > 5 * 1024 * 1024) {
    logActivity("[BANK_IMPORT_API_ERROR] [ID:{$requestId}] File too large: {$file['size']} bytes");
    echo json_encode(['success' => false, 'message' => 'File size must be less than 5MB']);
    exit;
}

// Read and parse JSON file
$jsonContent = file_get_contents($file['tmp_name']);
if ($jsonContent === false) {
    logActivity("[BANK_IMPORT_API_ERROR] [ID:{$requestId}] Failed to read uploaded file");
    echo json_encode(['success' => false, 'message' => 'Failed to read uploaded file']);
    exit;
}

$data = json_decode($jsonContent, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    logActivity("[BANK_IMPORT_API_ERROR] [ID:{$requestId}] Invalid JSON: " . json_last_error_msg());
    echo json_encode(['success' => false, 'message' => 'Invalid JSON format: ' . json_last_error_msg()]);
    exit;
}

// Validate JSON structure
if (!isset($data['status']) || $data['status'] !== true || !isset($data['data']) || !is_array($data['data'])) {
    logActivity("[BANK_IMPORT_API_ERROR] [ID:{$requestId}] Invalid JSON structure");
    echo json_encode(['success' => false, 'message' => 'JSON must contain "status": true and a "data" array']);
    exit;
}

$banks = $data['data'];
$totalBanks = count($banks);
logActivity("[BANK_IMPORT_API] [ID:{$requestId}] Found {$totalBanks} banks in JSON file");

// Get import options
$truncate = isset($_POST['truncate']) && $_POST['truncate'] === '1';
$updateExisting = isset($_POST['update_existing']) && $_POST['update_existing'] === '1';
$createTable = isset($_POST['create_table']) && $_POST['create_table'] === '1';

logActivity("[BANK_IMPORT_API_OPTIONS] [ID:{$requestId}] Options - Truncate: " . ($truncate ? 'Yes' : 'No') . ", Update: " . ($updateExisting ? 'Yes' : 'No') . ", Create Table: " . ($createTable ? 'Yes' : 'No'));

// Check if banks table exists
$tableCheckQuery = "SHOW TABLES LIKE 'banks_paystack'";
$tableCheckResult = $conn->query($tableCheckQuery);
$tableExists = $tableCheckResult->num_rows > 0;

if (!$tableExists && !$createTable) {
    logActivity("[BANK_IMPORT_API_ERROR] [ID:{$requestId}] Banks table does not exist and create_table option is false");
    echo json_encode(['success' => false, 'message' => 'Banks table does not exist. Enable "Create table" option.']);
    exit;
}

// Start transaction
$conn->begin_transaction();

try {
    // Create table if requested and doesn't exist
    if (!$tableExists && $createTable) {
        logActivity("[BANK_IMPORT_API] [ID:{$requestId}] Creating banks table");
        
        $createTableSQL = "
        CREATE TABLE IF NOT EXISTS banks_paystack (
            id INT AUTO_INCREMENT PRIMARY KEY,
            bank_id INT,
            bank_code VARCHAR(20) NOT NULL,
            bank_name VARCHAR(255) NOT NULL,
            longcode VARCHAR(50),
            slug VARCHAR(255),
            gateway VARCHAR(50),
            pay_with_bank TINYINT(1) DEFAULT 0,
            supports_transfer TINYINT(1) DEFAULT 1,
            available_for_direct_debit TINYINT(1) DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            country VARCHAR(100) DEFAULT 'Nigeria',
            currency VARCHAR(10) DEFAULT 'NGN',
            type VARCHAR(50) DEFAULT 'nuban',
            external_id VARCHAR(50),
            sort_code VARCHAR(20),
            created_by INT DEFAULT NULL,
            updated_by INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_bank_code (bank_code),
            INDEX idx_bank_name (bank_name),
            INDEX idx_is_active (is_active),
            INDEX idx_bank_id (bank_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        if (!$conn->query($createTableSQL)) {
            throw new Exception("Failed to create table: " . $conn->error);
        }
        
        logActivity("[BANK_IMPORT_API] [ID:{$requestId}] Banks table created successfully");
    }

    // Truncate table if requested
    if ($truncate) {
        logActivity("[BANK_IMPORT_API] [ID:{$requestId}] Truncating banks table");
        if (!$conn->query("TRUNCATE TABLE banks")) {
            throw new Exception("Failed to truncate table: " . $conn->error);
        }
    }

    // Prepare insert/update statement
    if ($updateExisting) {
        $insertSQL = "INSERT INTO banks_paystack (
            bank_id, bank_code, bank_name, longcode, slug, gateway, 
            pay_with_bank, supports_transfer, available_for_direct_debit, 
            is_active, country, currency, type, created_by, updated_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            bank_name = VALUES(bank_name),
            longcode = VALUES(longcode),
            slug = VALUES(slug),
            gateway = VALUES(gateway),
            pay_with_bank = VALUES(pay_with_bank),
            supports_transfer = VALUES(supports_transfer),
            available_for_direct_debit = VALUES(available_for_direct_debit),
            is_active = VALUES(is_active),
            updated_by = VALUES(updated_by),
            updated_at = NOW()";
    } else {
        $insertSQL = "INSERT IGNORE INTO banks_paystack (
            bank_id, bank_code, bank_name, longcode, slug, gateway, 
            pay_with_bank, supports_transfer, available_for_direct_debit, 
            is_active, country, currency, type, created_by, updated_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    }

    $stmt = $conn->prepare($insertSQL);
    if (!$stmt) {
        throw new Exception("Failed to prepare insert statement: " . $conn->error);
    }

    // Bind parameters
    $stmt->bind_param(
        "isssssiiiisssii",
        $bank_id,
        $bank_code,
        $bank_name,
        $longcode,
        $slug,
        $gateway,
        $pay_with_bank,
        $supports_transfer,
        $available_for_direct_debit,
        $is_active,
        $country,
        $currency,
        $type,
        $created_by,
        $updated_by
    );

    // Process banks
    $successCount = 0;
    $errorCount = 0;
    $skippedCount = 0;
    $errors = [];

    foreach ($banks as $index => $bank) {
        // Extract data with defaults
        $bank_id = isset($bank['id']) ? intval($bank['id']) : null;
        $bank_code = trim($bank['code'] ?? '');
        $bank_name = trim($bank['name'] ?? '');
        $longcode = trim($bank['longcode'] ?? '');
        $slug = trim($bank['slug'] ?? '');
        $gateway = isset($bank['gateway']) ? trim($bank['gateway']) : null;
        $pay_with_bank = isset($bank['pay_with_bank']) ? (int)$bank['pay_with_bank'] : 0;
        $supports_transfer = isset($bank['supports_transfer']) ? (int)$bank['supports_transfer'] : 1;
        $available_for_direct_debit = isset($bank['available_for_direct_debit']) ? (int)$bank['available_for_direct_debit'] : 0;
        $is_active = isset($bank['active']) ? (int)$bank['active'] : 1;
        $country = $bank['country'] ?? 'Nigeria';
        $currency = $bank['currency'] ?? 'NGN';
        $type = $bank['type'] ?? 'nuban';
        $created_by = $user_id;
        $updated_by = $user_id;

        // Validate required fields
        if (empty($bank_code) || empty($bank_name)) {
            $skippedCount++;
            $errors[] = "Row " . ($index + 1) . ": Missing bank code or name";
            logActivity("[BANK_IMPORT_API_SKIP] [ID:{$requestId}] Skipping bank at index {$index} - missing required fields");
            continue;
        }

        // Execute insert
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $successCount++;
            } else {
                $skippedCount++;
            }
        } else {
            $errorCount++;
            $errors[] = "Failed to import bank {$bank_code}: " . $stmt->error;
            logActivity("[BANK_IMPORT_API_ERROR] [ID:{$requestId}] Failed to import bank {$bank_code}: " . $stmt->error);
        }
    }

    $stmt->close();

    // Commit transaction
    $conn->commit();

    // Get final count
    $countQuery = "SELECT COUNT(*) as total FROM banks_paystack";
    $countResult = $conn->query($countQuery);
    $finalCount = $countResult->fetch_assoc()['total'];

    logActivity("[BANK_IMPORT_API_SUCCESS] [ID:{$requestId}] Import completed. Total: {$successCount}, Skipped: {$skippedCount}, Errors: {$errorCount}");

    echo json_encode([
        'success' => true,
        'message' => "Bank import completed successfully",
        'stats' => [
            'total' => $totalBanks,
            'successful' => $successCount,
            'skipped' => $skippedCount,
            'errors' => $errorCount,
            'errors_list' => $errors,
            'final_count' => $finalCount
        ]
    ]);

} catch (Exception $e) {
    $conn->rollback();
    logActivity("[BANK_IMPORT_API_EXCEPTION] [ID:{$requestId}] " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Import failed: ' . $e->getMessage()
    ]);
}

// Close connection
$conn->close();
logActivity("[BANK_IMPORT_API_END] [ID:{$requestId}] Request completed");

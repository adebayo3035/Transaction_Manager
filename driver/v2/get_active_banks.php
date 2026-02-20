<?php
// backend/get_active_banks.php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
session_start();

// Start logging
$requestId = uniqid('banks_', true);
logActivity("[GET_ACTIVE_BANKS_START] [ID:{$requestId}] Request started");

// Check if user is authenticated (optional - can be public or authenticated)
if (!isset($_SESSION['driver_id'])) {
    logActivity("[GET_ACTIVE_BANKS_ERROR] [ID:{$requestId}] No session found");
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Please login first']);
    exit;
}

$driver_id = $_SESSION['driver_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    logActivity("[GET_ACTIVE_BANKS_ERROR] [ID:{$requestId}] Invalid method: {$method}");
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

logActivity("[GET_ACTIVE_BANKS] [ID:{$requestId}] Driver ID: {$driver_id}");

try {
    // Get search term if provided (for filtering)
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    // Build query
    $query = "SELECT bank_code, bank_name, sort_code FROM banks WHERE is_active = 1";
    $params = [];
    $types = "";
    
    if (!empty($search)) {
        $query .= " AND (bank_name LIKE ? OR bank_code LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= "ss";
    }
    
    $query .= " ORDER BY bank_name ASC";
    
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute query: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    $banks = [];
    while ($row = $result->fetch_assoc()) {
        $banks[] = [
            'code' => $row['bank_code'],
            'name' => $row['bank_name'],
            'sort_code' => $row['sort_code'],
            'display' => $row['bank_name'] . ' (' . $row['bank_code'] . ')'
        ];
    }
    
    $stmt->close();
    
    logActivity("[GET_ACTIVE_BANKS_SUCCESS] [ID:{$requestId}] Retrieved " . count($banks) . " active banks");
    
    echo json_encode([
        'success' => true,
        'banks' => $banks,
        'count' => count($banks)
    ]);
    
} catch (Exception $e) {
    logActivity("[GET_ACTIVE_BANKS_EXCEPTION] [ID:{$requestId}] Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load banks'
    ]);
}

// Close connection
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}

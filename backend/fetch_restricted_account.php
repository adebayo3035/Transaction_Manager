<?php
// backend/fetch_deactivated.php
header('Content-Type: application/json');
include 'config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logActivity("Invalid Request method from credentials from IP: " . $_SERVER['REMOTE_ADDR']);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Verify super admin
if ($_SESSION['role'] !== 'Super Admin') {
    logActivity("Unauthorized access attempt from IP: " . $_SERVER['REMOTE_ADDR']);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}


logActivity("Script started. Request received from IP: " . $_SERVER['REMOTE_ADDR']);

// 2. Get and validate account type
$accountType = $_POST['typeOfAccount'] ?? '';
logActivity("Received account type: " . ($accountType ?: 'NULL'));

$allowed_tables = [
    'customers' => 'customers',
    'driver' => 'driver'
];

if (!array_key_exists($accountType, $allowed_tables)) {
    logActivity("Invalid account type requested: " . $accountType);
    die(json_encode(['success' => false, 'message' => 'Invalid Account Type']));
}

// 3. Database connection
try {
    
    // 4. Execute query
    $table = $allowed_tables[$accountType];
   $query = "SELECT id, email, CONCAT(firstname, ' ', lastname) AS name FROM $table WHERE restriction != 0";
    logActivity("Executing query: " . $query);
    
    $result = $conn->query($query);
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }
    
    $accounts = $result->fetch_all(MYSQLI_ASSOC);
    $count = count($accounts);
    logActivity("Fetched $count Restricted accounts for type: $accountType");
    
    // 5. Return response
    echo json_encode([
        'success' => true,
        'accounts' => $accounts
    ]);
    
    logActivity("Script completed successfully");
    
} catch (Exception $e) {
    logActivity("ERROR: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
} finally {
    if (isset($conn)) $conn->close();
}
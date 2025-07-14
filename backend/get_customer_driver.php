<?php
// backend/fetch_deactivated.php
header('Content-Type: application/json');
include 'config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logActivity("Invalid Request method from IP: " . $_SERVER['REMOTE_ADDR']);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if ($_SESSION['role'] !== 'Super Admin') {
    logActivity("Unauthorized access attempt from IP: " . $_SERVER['REMOTE_ADDR']);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

logActivity("Script started. Request received from IP: " . $_SERVER['REMOTE_ADDR']);

$input = json_decode(file_get_contents('php://input'), true);
$accountType = $input['account_type'] ?? '';
logActivity("Received account type: " . ($accountType ?: 'NULL'));

$allowed_tables = [
    'customers' => 'customers',
    'driver' => 'driver'
];

if (!array_key_exists($accountType, $allowed_tables)) {
    logActivity("Invalid account type requested: " . $accountType);
    die(json_encode(['success' => false, 'message' => 'Invalid Account Type']));
}

$table = $allowed_tables[$accountType];

try {
    // Build dynamic query safely using whitelisted table name
    $query = "
        SELECT 
    id, firstname, lastname, email, restriction, delete_status
FROM 
    $table
WHERE 
    NOT (restriction = 1 AND delete_status = 'Yes')
ORDER BY 
    lastname ASC, firstname ASC;

    ";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $accounts = [];
    while ($row = $result->fetch_assoc()) {
        $accounts[] = $row;
    }

    echo json_encode([
        'success' => true,
        'account_type' => $accountType,
        'data' => $accounts,
        'total' => count($accounts)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching accounts',
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($stmt))
        $stmt->close();
    if (isset($conn))
        $conn->close();
}





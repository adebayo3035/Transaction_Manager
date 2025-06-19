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

$table = $allowed_tables[$accountType];

try {
    // 👇 Fetch restricted accounts along with their latest RESTRICT reference_id
    $query = "
        SELECT 
            t.id, 
            t.email, 
            CONCAT(t.firstname, ' ', t.lastname) AS name,
            (
                SELECT reference_id 
                FROM account_restriction_audit_log ar 
                WHERE ar.account_id = t.id 
                  AND ar.account_type = UPPER(?) 
                  AND ar.action_type = 'RESTRICT'
                ORDER BY ar.id DESC 
                LIMIT 1
            ) AS reference_id
        FROM $table t
        WHERE t.restriction != 0
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $table); // use lowercase table name
    logActivity("Executing query for restricted $accountType accounts with reference_id");

    $stmt->execute();
    $result = $stmt->get_result();

    $accounts = $result->fetch_all(MYSQLI_ASSOC);
    $count = count($accounts);
    logActivity("Fetched $count restricted accounts for type: $accountType");

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

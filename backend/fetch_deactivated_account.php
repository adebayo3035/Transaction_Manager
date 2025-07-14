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
$data = json_decode(file_get_contents("php://input"), true);
// 2. Get and validate account type
$accountType = $_POST['selectAccountType'] ?? '';
logActivity("Received account type: " . ($accountType ?: 'NULL'));

$allowed = [
    'admin_tbl' => 'admin_tbl',
    'customers' => 'customers',
    'driver' => 'driver'
];

if (!array_key_exists($accountType, $allowed)) {
    logActivity("Invalid account type requested: " . $accountType);
    die(json_encode(['success' => false, 'message' => 'Invalid type']));
}

try {
    $table = $allowed[$accountType];

    $query = "
    SELECT 
        t.id, 
        t.email, 
        CONCAT(t.firstname, ' ', t.lastname) AS name,
        (
            SELECT reference_id 
            FROM account_deactivation_audit_log ar 
            WHERE ar.account_id = t.id 
              AND UPPER(ar.account_type) = UPPER(?) 
              AND UPPER(ar.action_type) = 'DEACTIVATE'
            ORDER BY ar.id DESC 
            LIMIT 1
        ) AS reference_id,
        (
            SELECT initiated_by 
            FROM account_deactivation_audit_log ar 
            WHERE ar.account_id = t.id 
              AND UPPER(ar.account_type) = UPPER(?) 
              AND UPPER(ar.action_type) = 'DEACTIVATE'
            ORDER BY ar.id DESC 
            LIMIT 1
        ) AS initiated_by
    FROM $table t
    WHERE t.delete_status = 'Yes'
";


    logActivity("Executing query for Deactivated $accountType accounts with reference_id");

    $stmt = $conn->prepare($query);
$stmt->bind_param("ss", $accountType, $accountType); // Bind for both subqueries

    $stmt->execute();
    $result = $stmt->get_result();

    $accounts = $result->fetch_all(MYSQLI_ASSOC);
    $count = count($accounts);
    logActivity("Fetched $count deactivated accounts for type: $accountType");

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
    if (isset($conn))
        $conn->close();
}
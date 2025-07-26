<?php
include 'config.php';
include 'auth_utils.php';
session_start();
header('Content-Type: application/json');

// Validate Super Admin access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Super Admin') {
    logActivity("Unauthorized access attempt by User ID: " . ($_SESSION['unique_id'] ?? 'Unknown'));
    echo json_encode(['success' => false, 'message' => 'Unauthorized! Contact your Admin.']);
    exit();
}

$adminID = $_SESSION['unique_id']; // Get Super Admin's ID

// Allowed tables to prevent SQL injection
$allowedTables = ['admin_tbl', 'customers', 'driver'];

// Ensure the request is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logActivity("Invalid request method used for account reactivation.");
    echo json_encode(['success' => false, 'message' => 'Access Denied! Invalid method.']);
    exit();
}

// Decode JSON request
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['userID'], $data['accountType'], $data['secretAnswer'])) {
    logActivity("Invalid request data received from Admin ID: $adminID");
    echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
    exit();
}

$user_id = filter_var($data['userID'], FILTER_SANITIZE_NUMBER_INT);
$account_type = filter_var($data['accountType'], FILTER_SANITIZE_STRING);
$secret_answer = trim($data['secretAnswer']);

// Validate account type
if (!in_array($account_type, $allowedTables)) {
    logActivity("Invalid account type attempted by Admin ID: $adminID. Provided: $account_type");
    echo json_encode(['success' => false, 'message' => 'Invalid account type.']);
    exit();
}

// Call function to reactivate account
$response = reactivateAccount($conn, $account_type, $user_id, $secret_answer, $adminID);

echo $response;
exit();
function reactivateAccount($conn, $accountType, $user_id, $providedSecretAnswer, $reactivatedBy)
{
    // Define allowed tables to prevent SQL injection
    $allowedTables = ['admin_tbl', 'customers', 'driver'];

    if (!in_array($accountType, $allowedTables)) {
        logActivity("Invalid account type provided: $accountType");
        return json_encode(['success' => false, 'message' => 'Invalid account type.']);
    }

    // Fetch user data
    $stmt = $conn->prepare("SELECT email, secret_answer, delete_status FROM $accountType WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        logActivity("No account found for ID: $user_id in table: $accountType");
        return json_encode(['success' => false, 'message' => 'Account not found.']);
    }

    // Validate secret answer
    // if (md5($providedSecretAnswer) !== $user['secret_answer']) {
    //     logActivity("Secret answer mismatch for ID: $user_id in table: $accountType");
    //     return json_encode(['success' => false, 'message' => 'Invalid secret answer.']);
    // }

    if (!verifyAndUpgradeSecretAnswer($conn, $user_id, $providedSecretAnswer, $user['secret_answer'])) {
             logActivity("Secret answer mismatch for ID: $user_id in table: $accountType");
            return json_encode(['success' => false, 'message' => 'Invalid secret answer.']);
    }

    // Update account delete status
    $stmt = $conn->prepare("UPDATE $accountType SET delete_status = NULL WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        logActivity("Failed to update delete_status for ID: $user_id in table: $accountType");
        return json_encode(['success' => false, 'message' => 'Failed to reactivate account.']);
    }
    $stmt->close();

    // Log reactivation in account_reactivation table
    // $stmt = $conn->prepare("INSERT INTO account_reactivation (unique_id, account_type, reactivated_by, reactivation_date) VALUES (?, ?, ?, NOW())");
    // $stmt->bind_param("sss", $uniqueId, $accountType, $reactivatedBy);
    // if (!$stmt->execute()) {
    //     logActivity("Failed to insert reactivation log for ID: $uniqueId in table: $accountType");
    //     return json_encode(['success' => false, 'message' => 'Failed to log reactivation.']);
    // }
    // $stmt->close();

    logActivity("Account reactivated successfully for ID: $user_id in table: $accountType by $reactivatedBy");
    return json_encode(['success' => true, 'message' => 'Account reactivated successfully.']);
}

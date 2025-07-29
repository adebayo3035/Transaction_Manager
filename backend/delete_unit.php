<?php
header('Content-Type: application/json');
include_once('config.php');
session_start();

// Initialize logging
logActivity("Unit deletion process started");

// Declare variables at the start
$stmtCheck = null;
$stmtDelete = null;

try {
    // Check authentication
    $user_id = $_SESSION['unique_id'] ?? null;
    if (!$user_id) {
        $errorMsg = "Unauthenticated access attempt";
        logActivity($errorMsg);
        echo json_encode(['success' => false, 'message' => 'User not authenticated.']);
        exit();
    }

    // Check authorization
    $staff_role = $_SESSION['role'] ?? '';
    if ($staff_role !== "Super Admin") {
        $errorMsg = "Unauthorized deletion attempt by user: " . $user_id;
        logActivity($errorMsg);
        echo json_encode(["success" => false, "message" => "You do not have permission to delete."]);
        exit();
    }

    // Get and validate input
    $data = json_decode(file_get_contents("php://input"), true);
    if (!isset($data['unit_id']) || empty($data['unit_id'])) {
        $errorMsg = "Missing unit ID in request";
        logActivity($errorMsg);
        echo json_encode(["success" => false, "message" => "Unit ID is required."]);
        exit();
    }

    $unitId = (int) $data['unit_id'];
    logActivity("Attempting to delete unit ID: " . $unitId);

    // Start transaction
    $conn->begin_transaction();
    logActivity("Transaction started for unit deletion");

    // Check if the unit has already been soft-deleted
$checkDeleteSql = "SELECT delete_status FROM unit WHERE unit_id = ?";
$stmtCheckDelete = $conn->prepare($checkDeleteSql);
if (!$stmtCheckDelete) {
    throw new Exception("Prepare failed for delete_status check: " . $conn->error);
}
$stmtCheckDelete->bind_param("i", $unitId);
$stmtCheckDelete->execute();
$resultDeleteCheck = $stmtCheckDelete->get_result();

if ($resultDeleteCheck->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Unit record not found.']);
    exit;
}

$rowDeleteCheck = $resultDeleteCheck->fetch_assoc();
if ((int)$rowDeleteCheck['delete_status'] === 1) {
    echo json_encode(['success' => false, 'message' => 'Unit has already been deleted.']);
    exit;
}

logActivity("Unit $unitId is active. Proceeding with dependency checks.");


    // First check if unit exists and has no dependent records
    $checkSql = "SELECT u.unit_id, 
            (SELECT COUNT(*) FROM team WHERE unit_id = u.unit_id) as team_count,
            (SELECT COUNT(*) FROM customers WHERE unit_id = u.unit_id) as customer_count
            FROM unit u WHERE u.unit_id = ? FOR UPDATE";

    logActivity("Preparing dependency check query: " . $checkSql);
    $stmtCheck = $conn->prepare($checkSql);
    if (!$stmtCheck) {
        $errorMsg = "Prepare failed for check: " . $conn->error;
        logActivity($errorMsg);
        throw new Exception($errorMsg);
    }

    logActivity("Binding unit ID parameter: " . $unitId);
    $stmtCheck->bind_param("i", $unitId);
    if (!$stmtCheck->execute()) {
        $errorMsg = "Execute failed for check: " . $stmtCheck->error;
        logActivity($errorMsg);
        throw new Exception($errorMsg);
    }

    $result = $stmtCheck->get_result();
    if ($result->num_rows === 0) {
        $errorMsg = "Unit not found with ID: " . $unitId;
        logActivity($errorMsg);
        throw new Exception($errorMsg);
    }

    $unitData = $result->fetch_assoc();
    logActivity("Dependency check results for unit $unitId - Teams: " .
        $unitData['team_count'] . ", Customers: " . $unitData['customer_count']);

    // Debug: Log actual team and customer records if counts > 0
    if ($unitData['team_count'] > 0) {
        $teamDebugSql = "SELECT team_id, team_name FROM team WHERE unit_id = ?";
        $stmtTeamDebug = $conn->prepare($teamDebugSql);
        if ($stmtTeamDebug) {
            $stmtTeamDebug->bind_param("i", $unitId);
            $stmtTeamDebug->execute();
            $teams = $stmtTeamDebug->get_result()->fetch_all(MYSQLI_ASSOC);
            logActivity("Associated Teams: " . print_r($teams, true));
            $stmtTeamDebug->close();
        }
    }

    if ($unitData['customer_count'] > 0) {
        $customerDebugSql = "SELECT customer_id, CONCAT(firstname, ' ', lastname) AS customer_name FROM customers WHERE unit_id = ?";
        $stmtCustomerDebug = $conn->prepare($customerDebugSql);
        if ($stmtCustomerDebug) {
            $stmtCustomerDebug->bind_param("i", $unitId);
            $stmtCustomerDebug->execute();
            $customers = $stmtCustomerDebug->get_result()->fetch_all(MYSQLI_ASSOC);
            logActivity("Associated Customers: " . print_r($customers, true));
            $stmtCustomerDebug->close();
        }
    }

    $stmtCheck->close();
    $stmtCheck = null;

    // Check for dependent records
    if ($unitData['team_count'] > 0 || $unitData['customer_count'] > 0) {
        $errorMsg = sprintf(
            "Cannot delete Unit %d - It has %d associated team(s) and %d associated customer(s)",
            $unitId,
            $unitData['team_count'],
            $unitData['customer_count']
        );
        logActivity($errorMsg);
        throw new Exception($errorMsg);
    }

    logActivity("No dependencies found for unit $unitId - proceeding with soft delete (update delete_status)");

    $deleteSql = "UPDATE unit SET delete_status = 1 WHERE unit_id = ?";
    $stmtDelete = $conn->prepare($deleteSql);
    if (!$stmtDelete) {
        throw new Exception("Prepare failed for soft delete: " . $conn->error);
    }

    $stmtDelete->bind_param("i", $unitId);
    if (!$stmtDelete->execute()) {
        throw new Exception("Execute failed for soft delete: " . $stmtDelete->error);
    }


    $conn->commit();
    $successMsg = "Unit deleted successfully. ID: " . $unitId;
    logActivity($successMsg);
    echo json_encode([
        "success" => true,
        "message" => "Unit has been successfully deleted.",
        "unit_id" => $unitId,
        "deleted_by" => $user_id
    ]);

} catch (Exception $e) {
    if (isset($conn) && method_exists($conn, 'rollback')) {
        $conn->rollback();
    }
    $errorMsg = "Unit deletion failed: " . $e->getMessage();
    logActivity($errorMsg);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage(),
        "error" => $e->getMessage()
    ]);
} finally {
    // Close statements only if they exist and haven't been closed yet
    if ($stmtCheck instanceof mysqli_stmt) {
        $stmtCheck->close();
    }
    if ($stmtDelete instanceof mysqli_stmt) {
        $stmtDelete->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
    logActivity("Unit deletion process completed");
}
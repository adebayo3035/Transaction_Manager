<?php
header('Content-Type: application/json');
include_once('config.php');
session_start();

// Constants for logging
const LOG_PREFIX = "GROUP_DELETION";
const MAX_LOG_LENGTH = 1000; // Maximum length for log messages

// Initialize logging with timestamp and prefix
// function logActivity(string $message): void {
//     $truncatedMessage = strlen($message) > MAX_LOG_LENGTH 
//         ? substr($message, 0, MAX_LOG_LENGTH) . "...[TRUNCATED]" 
//         : $message;
//     logActivity(LOG_PREFIX . " - " . $truncatedMessage);
// }

logActivity("Process started");

try {
    // Validate session and authentication
    $user_id = $_SESSION['unique_id'] ?? null;
    if (!$user_id) {
        $errorMsg = "Unauthenticated access attempt";
        logActivity($errorMsg);
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit();
    }

    // Check authorization
    $staff_role = $_SESSION['role'] ?? '';
    if ($staff_role !== "Super Admin") {
        $errorMsg = "Unauthorized attempt by user $user_id (Role: $staff_role)";
        logActivity($errorMsg);
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Insufficient permissions"]);
        exit();
    }

    // Validate input
    $data = json_decode(file_get_contents("php://input"), true);
    if (!isset($data['group_id']) || !is_numeric($data['group_id'])) {
        $errorMsg = "Invalid group ID in request: " . json_encode($data);
        logActivity($errorMsg);
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Valid group ID is required"]);
        exit();
    }

    $groupId = (int) $data['group_id'];
    logActivity("Processing deletion for group ID: $groupId");

    // Start transaction
    $conn->begin_transaction();
    logActivity("Transaction started");

    // Check if group exists and is not already deleted
    $checkDeleteSql = "SELECT group_id, group_name, delete_status FROM groups WHERE group_id = ? FOR UPDATE";
    $stmtCheckDelete = $conn->prepare($checkDeleteSql);
    if (!$stmtCheckDelete) {
        throw new Exception("Prepare failed for existence check: " . $conn->error);
    }

    $stmtCheckDelete->bind_param("i", $groupId);
    if (!$stmtCheckDelete->execute()) {
        throw new Exception("Execute failed for existence check: " . $stmtCheckDelete->error);
    }

    $resultDeleteCheck = $stmtCheckDelete->get_result();
    if ($resultDeleteCheck->num_rows === 0) {
        logActivity("Group not found with ID: $groupId");
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Group not found']);
        exit();
    }

    $group = $resultDeleteCheck->fetch_assoc();
    if ($group['delete_status'] == 1) {
        logActivity("Group already deleted - ID: $groupId, Name: {$group['group_name']}");
        echo json_encode(['success' => false, 'message' => 'Group already deleted']);
        exit();
    }

    logActivity("Group found - ID: $groupId, Name: {$group['group_name']}");

    // Check dependencies with a single query
    $dependencySql = "SELECT 
        (SELECT COUNT(*) FROM team WHERE group_id = ?) as team_count,
        (SELECT COUNT(*) FROM unit WHERE group_id = ?) as unit_count,
        (SELECT COUNT(*) FROM customers WHERE group_id = ?) as customer_count";

    $stmtDependencies = $conn->prepare($dependencySql);
    if (!$stmtDependencies) {
        throw new Exception("Prepare failed for dependency check: " . $conn->error);
    }

    $stmtDependencies->bind_param("iii", $groupId, $groupId, $groupId);
    if (!$stmtDependencies->execute()) {
        throw new Exception("Execute failed for dependency check: " . $stmtDependencies->error);
    }

    $dependencies = $stmtDependencies->get_result()->fetch_assoc();
    // $stmtDependencies->close();

    logActivity(sprintf(
        "Dependency check - Teams: %d, Units: %d, Customers: %d",
        $dependencies['team_count'],
        $dependencies['unit_count'],
        $dependencies['customer_count']
    ));

    // Log detailed dependency information if any exist
    if (array_sum($dependencies) > 0) {
        // $this->logDependencyDetails($conn, $groupId, $dependencies);
        $errorMsg = sprintf(
            "Group has dependencies - Teams: %d, Units: %d, Customers: %d",
            $dependencies['team_count'],
            $dependencies['unit_count'],
            $dependencies['customer_count']
        );
        throw new Exception($errorMsg);
    }

    // Perform soft delete
    $deleteSql = "UPDATE groups SET delete_status = 1 WHERE group_id = ?";
    $stmtDelete = $conn->prepare($deleteSql);
    if (!$stmtDelete) {
        throw new Exception("Prepare failed for deletion: " . $conn->error);
    }

    $stmtDelete->bind_param("i",$groupId);
    if (!$stmtDelete->execute()) {
        throw new Exception("Execute failed for deletion: " . $stmtDelete->error);
    }

    $affectedRows = $stmtDelete->affected_rows;
    $stmtDelete->close();

    if ($affectedRows === 0) {
        throw new Exception("No rows affected - group may have been deleted by another process");
    }

    $conn->commit();
    logActivity("Successfully deleted group ID: $groupId");

    echo json_encode([
        "success" => true,
        "message" => "Group deleted successfully",
        "data" => [
            "group_id" => $groupId,
            "deleted_by" => $user_id,
            "timestamp" => date('c')
        ]
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn instanceof mysqli && !$conn->connect_errno) {
        $conn->rollback();
    }
    
    $errorMsg = "Deletion failed: " . $e->getMessage();
    logActivity($errorMsg);
    
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Group deletion failed",
        "error" => $e->getMessage(),
        "error_code" => $e->getCode()
    ]);
} finally {
    foreach (['stmtDependencies', 'stmtDelete'] as $stmtVar) {
        if (isset($$stmtVar) && $$stmtVar instanceof mysqli_stmt) {
            try {
                $$stmtVar->close();
            } catch (Throwable $e) {
                // Silently catch to avoid fatal errors on double close
            }
        }
    }

    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }

    logActivity("Group deletion process completed");
}
/**
 * Log detailed information about dependencies
 */
function logDependencyDetails(mysqli $conn, int $groupId, array $dependencies): void {
    $dependencyTypes = [
        'team' => ['table' => 'team', 'fields' => 'team_id, team_name'],
        'unit' => ['table' => 'unit', 'fields' => 'unit_id, unit_name'],
        'customer' => ['table' => 'customers', 'fields' => 'customer_id, CONCAT(firstname, " ", lastname) AS name']
    ];

    foreach ($dependencyTypes as $type => $config) {
        if ($dependencies["{$type}_count"] > 0) {
            $sql = "SELECT {$config['fields']} FROM {$config['table']} WHERE group_id = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("i", $groupId);
                $stmt->execute();
                $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                logActivity("$type dependencies: " . json_encode($results));
                $stmt->close();
            }
        }
    }
}
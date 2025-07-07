<?php
header('Content-Type: application/json');
include_once('config.php');
session_start();

// Initialize logging
logActivity("Group deletion process started");

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
    if (!isset($data['group_id']) || empty($data['group_id'])) {
        $errorMsg = "Missing group ID in request";
        logActivity($errorMsg);
        echo json_encode(["success" => false, "message" => "Group ID is required."]);
        exit();
    }

    $groupId = (int) $data['group_id'];
    logActivity("Attempting to delete group ID: " . $groupId);

    // Start transaction
    $conn->begin_transaction();
    logActivity("Transaction started for group deletion");

    try {
        // Step 1: Check if group exists and has dependent records
        $checkSql = "SELECT g.group_id, 
                    (SELECT COUNT(*) FROM team WHERE group_id = g.group_id) as team_count,
                    (SELECT COUNT(*) FROM unit WHERE group_id = g.group_id) as unit_count,
                    (SELECT COUNT(*) FROM customers WHERE group_id = g.group_id) as customer_count
                 FROM groups g WHERE g.group_id = ? FOR UPDATE";

        logActivity("Preparing dependency check query: " . $checkSql);

        $stmtCheck = $conn->prepare($checkSql);
        if (!$stmtCheck) {
            $errorMsg = "Prepare failed for check: " . $conn->error;
            logActivity($errorMsg);
            throw new Exception($errorMsg);
        }

        logActivity("Binding group ID parameter: " . $groupId);
        $stmtCheck->bind_param("i", $groupId);
        if (!$stmtCheck->execute()) {
            $errorMsg = "Execute failed for check: " . $stmtCheck->error;
            logActivity($errorMsg);
            throw new Exception($errorMsg);
        }

        $result = $stmtCheck->get_result();
        if ($result->num_rows === 0) {
            $errorMsg = "Group not found with ID: " . $groupId;
            logActivity($errorMsg);
            throw new Exception($errorMsg);
        }

        $groupData = $result->fetch_assoc();
        logActivity("Dependency check results for group $groupId - Teams: " .
            $groupData['team_count'] . ", Units: " . $groupData['unit_count'] .
            ", Customers: " . $groupData['customer_count']);
        $stmtCheck->close();
        $stmtCheck = null;

        // Step 2: Log actual dependent records if any exist
        if ($groupData['team_count'] > 0) {
            $teamDebugSql = "SELECT team_id, team_name FROM team WHERE group_id = ?";
            $stmtTeamDebug = $conn->prepare($teamDebugSql);
            if ($stmtTeamDebug) {
                $stmtTeamDebug->bind_param("i", $groupId);
                $stmtTeamDebug->execute();
                $teams = $stmtTeamDebug->get_result()->fetch_all(MYSQLI_ASSOC);
                logActivity("Associated Teams: " . print_r($teams, true));
                $stmtTeamDebug->close();
            }
        }

        if ($groupData['unit_count'] > 0) {
            $unitDebugSql = "SELECT unit_id, unit_name FROM unit WHERE group_id = ?";
            $stmtUnitDebug = $conn->prepare($unitDebugSql);
            if ($stmtUnitDebug) {
                $stmtUnitDebug->bind_param("i", $groupId);
                $stmtUnitDebug->execute();
                $units = $stmtUnitDebug->get_result()->fetch_all(MYSQLI_ASSOC);
                logActivity("Associated Units: " . print_r($units, true));
                $stmtUnitDebug->close();
            }
        }

        if ($groupData['customer_count'] > 0) {
            $customerDebugSql = "SELECT customer_id, CONCAT(firstname, ' ', lastname) AS customer_name FROM customers WHERE group_id = ?";
            $stmtCustomerDebug = $conn->prepare($customerDebugSql);
            if ($stmtCustomerDebug) {
                $stmtCustomerDebug->bind_param("i", $groupId);
                $stmtCustomerDebug->execute();
                $customers = $stmtCustomerDebug->get_result()->fetch_all(MYSQLI_ASSOC);
                logActivity("Associated Customers: " . print_r($customers, true));
                $stmtCustomerDebug->close();
            }
        }

        // Step 3: Abort if any dependencies found
        if ($groupData['team_count'] > 0 || $groupData['unit_count'] > 0 || $groupData['customer_count'] > 0) {
            $errorMsg = sprintf(
                "Cannot delete Group %d - It has %d associated team(s), %d unit(s), and %d customer(s)",
                $groupId,
                $groupData['team_count'],
                $groupData['unit_count'],
                $groupData['customer_count']
            );
            logActivity($errorMsg);
            throw new Exception($errorMsg);
        }

        logActivity("No dependencies found for group $groupId - proceeding with deletion");

        // Step 4: Proceed with deletion
        $deleteSql = "DELETE FROM groups WHERE group_id = ?";
        logActivity("Preparing DELETE query: " . $deleteSql);

        $stmtDelete = $conn->prepare($deleteSql);
        if (!$stmtDelete) {
            $errorMsg = "Prepare failed for delete: " . $conn->error;
            logActivity($errorMsg);
            throw new Exception($errorMsg);
        }

        logActivity("Binding group ID parameter for deletion: " . $groupId);
        $stmtDelete->bind_param("i", $groupId);
        if (!$stmtDelete->execute()) {
            $errorMsg = "Execute failed for delete: " . $stmtDelete->error;
            logActivity($errorMsg);
            throw new Exception($errorMsg);
        }

        $conn->commit();
        $successMsg = "Group deleted successfully. ID: " . $groupId;
        logActivity($successMsg);
        echo json_encode([
            "success" => true,
            "message" => "Group has been successfully deleted.",
            "group_id" => $groupId,
            "deleted_by" => $user_id
        ]);

    } catch (Exception $e) {
        if (isset($conn) && method_exists($conn, 'rollback')) {
            $conn->rollback();
        }
        $errorMsg = "Group deletion failed: " . $e->getMessage();
        logActivity($errorMsg);
        echo json_encode([
            "success" => false,
            "message" => $e->getMessage(),
            "error" => $e->getMessage()
        ]);
    } finally {
        if (isset($stmtCheck) && $stmtCheck instanceof mysqli_stmt) {
            $stmtCheck->close();
        }
        if (isset($stmtDelete) && $stmtDelete instanceof mysqli_stmt) {
            $stmtDelete->close();
        }
        // if (isset($conn)) {
        //     $conn->close();
        // }
        logActivity("Group deletion process completed");
    }

} catch (Exception $e) {
    logActivity("System error: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => "An unexpected error occurred."
    ]);
} finally {
    $conn->close();
    logActivity("Group deletion process completed");
}
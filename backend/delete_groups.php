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

    $groupId = (int)$data['group_id'];
    logActivity("Attempting to delete group ID: " . $groupId);

    // Start transaction
    $conn->begin_transaction();
    logActivity("Transaction started for group deletion");

    try {
        // First check if group exists and has no dependent records
        $checkSql = "SELECT g.group_id, 
                    (SELECT COUNT(*) FROM team WHERE group_id = g.group_id) as team_count,
                    (SELECT COUNT(*) FROM unit WHERE group_id = g.group_id) as unit_count
                    FROM groups g WHERE g.group_id = ? FOR UPDATE";
        
        $stmtCheck = $conn->prepare($checkSql);
        if (!$stmtCheck) {
            throw new Exception("Prepare failed for check: " . $conn->error);
        }

        $stmtCheck->bind_param("i", $groupId);
        if (!$stmtCheck->execute()) {
            throw new Exception("Execute failed for check: " . $stmtCheck->error);
        }

        $result = $stmtCheck->get_result();
        if ($result->num_rows === 0) {
            throw new Exception("Group not found with ID: " . $groupId);
        }

        $groupData = $result->fetch_assoc();
        $stmtCheck->close();

        // Check for dependent records
        if ($groupData['team_count'] > 0 || $groupData['unit_count'] > 0) {
            throw new Exception("Cannot delete group - it has associated teams or units");
        }

        // Delete group
        $deleteSql = "DELETE FROM groups WHERE group_id = ?";
        $stmtDelete = $conn->prepare($deleteSql);
        if (!$stmtDelete) {
            throw new Exception("Prepare failed for delete: " . $conn->error);
        }

        $stmtDelete->bind_param("i", $groupId);
        if (!$stmtDelete->execute()) {
            throw new Exception("Execute failed for delete: " . $stmtDelete->error);
        }

        $conn->commit();
        $successMsg = "Group deleted successfully. ID: " . $groupId;
        logActivity($successMsg);
        echo json_encode([
            "success" => true, 
            "message" => "Group has been successfully deleted.",
            "group_id" => $groupId
        ]);

    } catch (Exception $e) {
        $conn->rollback();
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
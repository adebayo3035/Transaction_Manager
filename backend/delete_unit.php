<?php
header('Content-Type: application/json');
include_once('config.php');
session_start();

// Initialize logging
logActivity("Unit deletion process started");

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

    $unitId = (int)$data['unit_id'];
    logActivity("Attempting to delete unit ID: " . $unitId);

    // Start transaction
    $conn->begin_transaction();
    logActivity("Transaction started for unit deletion");

    try {
        // First check if unit exists and has no dependent records
        $checkSql = "SELECT u.unit_id, 
                    (SELECT COUNT(*) FROM team WHERE unit_id = u.unit_id) as team_count,
                    (SELECT COUNT(*) FROM customers WHERE unit_id = u.unit_id) as customer_count
                    FROM unit u WHERE u.unit_id = ? FOR UPDATE";
        
        $stmtCheck = $conn->prepare($checkSql);
        if (!$stmtCheck) {
            throw new Exception("Prepare failed for check: " . $conn->error);
        }

        $stmtCheck->bind_param("i", $unitId);
        if (!$stmtCheck->execute()) {
            throw new Exception("Execute failed for check: " . $stmtCheck->error);
        }

        $result = $stmtCheck->get_result();
        if ($result->num_rows === 0) {
            throw new Exception("Unit not found with ID: " . $unitId);
        }

        $unitData = $result->fetch_assoc();
        $stmtCheck->close();

        // Check for dependent records
        if ($unitData['team_count'] > 0 || $unitData['customer_count'] > 0) {
            throw new Exception("Cannot delete unit - it has associated teams or customers");
        }

        // Delete unit
        $deleteSql = "DELETE FROM unit WHERE unit_id = ?";
        $stmtDelete = $conn->prepare($deleteSql);
        if (!$stmtDelete) {
            throw new Exception("Prepare failed for delete: " . $conn->error);
        }

        $stmtDelete->bind_param("i", $unitId);
        if (!$stmtDelete->execute()) {
            throw new Exception("Execute failed for delete: " . $stmtDelete->error);
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
        $conn->rollback();
        $errorMsg = "Unit deletion failed: " . $e->getMessage();
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
    logActivity("Unit deletion process completed");
}
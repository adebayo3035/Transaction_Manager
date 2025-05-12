<?php
header('Content-Type: application/json');
include_once('config.php');
session_start();

// Initialize logging
logActivity("Revenue type deletion process started");

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
    if (!isset($data['revenue_id']) || empty($data['revenue_id'])) {
        $errorMsg = "Missing revenue ID in request";
        logActivity($errorMsg);
        echo json_encode(["success" => false, "message" => "Revenue ID is required."]);
        exit();
    }

    $revenueId = (int)$data['revenue_id'];
    logActivity("Attempting to delete revenue type ID: " . $revenueId);

    // Start transaction
    $conn->begin_transaction();
    logActivity("Transaction started for revenue type deletion");

    try {
        // First check if revenue type exists and has no dependent records
        $checkSql = "SELECT rt.revenue_type_id, 
                    (SELECT COUNT(*) FROM revenue_entries WHERE revenue_type_id = rt.revenue_type_id) as entry_count
                    FROM revenue_types rt WHERE rt.revenue_type_id = ? FOR UPDATE";
        
        $stmtCheck = $conn->prepare($checkSql);
        if (!$stmtCheck) {
            throw new Exception("Prepare failed for check: " . $conn->error);
        }

        $stmtCheck->bind_param("i", $revenueId);
        if (!$stmtCheck->execute()) {
            throw new Exception("Execute failed for check: " . $stmtCheck->error);
        }

        $result = $stmtCheck->get_result();
        if ($result->num_rows === 0) {
            throw new Exception("Revenue type not found with ID: " . $revenueId);
        }

        $revenueData = $result->fetch_assoc();
        $stmtCheck->close();

        // Check for dependent records in revenue_entries
        if ($revenueData['entry_count'] > 0) {
            throw new Exception("Cannot delete revenue type - it has associated revenue entries");
        }

        // Delete revenue type
        $deleteSql = "DELETE FROM revenue_types WHERE revenue_type_id = ?";
        $stmtDelete = $conn->prepare($deleteSql);
        if (!$stmtDelete) {
            throw new Exception("Prepare failed for delete: " . $conn->error);
        }

        $stmtDelete->bind_param("i", $revenueId);
        if (!$stmtDelete->execute()) {
            throw new Exception("Execute failed for delete: " . $stmtDelete->error);
        }

        $conn->commit();
        $successMsg = "Revenue type deleted successfully. ID: " . $revenueId;
        logActivity($successMsg);
        echo json_encode([
            "success" => true, 
            "message" => "Revenue type has been successfully deleted.",
            "revenue_type_id" => $revenueId,
            "deleted_by" => $user_id
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        $errorMsg = "Revenue type deletion failed: " . $e->getMessage();
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
    logActivity("Revenue type deletion process completed");
}
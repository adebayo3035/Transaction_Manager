<?php
header('Content-Type: application/json');
include('config.php');
session_start();

// Initialize logging
logActivity("Promo soft deletion process started");

try {
    // Check authentication
    if (!isset($_SESSION['unique_id'])) {
        $errorMsg = "Unauthenticated access attempt";
        logActivity($errorMsg);
        echo json_encode(["success" => false, "message" => "Not logged in."]);
        exit();
    }

    // Check authorization (assuming only certain roles can delete promos)
    $allowedRoles = ["Super Admin", "Marketing Manager"];
    if (!in_array($_SESSION['role'] ?? '', $allowedRoles)) {
        $errorMsg = "Unauthorized deletion attempt by user: " . ($_SESSION['unique_id'] ?? 'unknown');
        logActivity($errorMsg);
        echo json_encode(["success" => false, "message" => "You don't have permission to delete promos."]);
        exit();
    }

    // Get and validate input
    $data = json_decode(file_get_contents("php://input"), true);
    if (!isset($data['promo_id']) || empty($data['promo_id'])) {
        $errorMsg = "Missing promo ID in request";
        logActivity($errorMsg);
        echo json_encode(["success" => false, "message" => "Promo ID is required."]);
        exit();
    }

    $promoId = (int)$data['promo_id'];
    $deleteFlag = 1; // Soft delete flag
    logActivity("Attempting to soft delete promo ID: " . $promoId);

    // Start transaction
    $conn->begin_transaction();
    logActivity("Transaction started for promo soft deletion");

    try {
        // First check if promo exists
        $checkSql = "SELECT promo_id FROM promo WHERE promo_id = ? FOR UPDATE";
        $stmtCheck = $conn->prepare($checkSql);
        
        if (!$stmtCheck) {
            throw new Exception("Prepare failed for check: " . $conn->error);
        }

        $stmtCheck->bind_param("i", $promoId);
        
        if (!$stmtCheck->execute()) {
            throw new Exception("Execute failed for check: " . $stmtCheck->error);
        }

        $result = $stmtCheck->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Promo not found with ID: " . $promoId);
        }
        $stmtCheck->close();

        // Prepare the soft delete update
        $updateSql = "UPDATE promo SET 
                     delete_id = ?, 
                     date_last_modified = NOW(),
                     modified_by = ?
                     WHERE promo_id = ?";
        
        $stmtUpdate = $conn->prepare($updateSql);
        
        if (!$stmtUpdate) {
            throw new Exception("Prepare failed for update: " . $conn->error);
        }

        $modifiedBy = $_SESSION['unique_id'];
        $stmtUpdate->bind_param("iii", $deleteFlag, $modifiedBy, $promoId);
        
        if (!$stmtUpdate->execute()) {
            throw new Exception("Execute failed for update: " . $stmtUpdate->error);
        }

        $conn->commit();
        $successMsg = "Promo soft deleted successfully. ID: " . $promoId;
        logActivity($successMsg);
        echo json_encode([
            "success" => true, 
            "message" => "Promo has been successfully marked as deleted.",
            "promo_id" => $promoId,
            "deleted_by" => $modifiedBy
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        $errorMsg = "Promo soft deletion failed: " . $e->getMessage();
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
        if (isset($stmtUpdate) && $stmtUpdate instanceof mysqli_stmt) {
            $stmtUpdate->close();
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
    logActivity("Promo soft deletion process completed");
}
<?php
include_once('config.php');
session_start();
header('Content-Type: application/json');

// Initialize logging
logActivity("Food deletion process started");

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
        echo json_encode(["success" => false, "message" => "You do not have permission to delete this item."]);
        exit();
    }

    // Get and validate JSON input
    $data = json_decode(file_get_contents("php://input"), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $errorMsg = "Invalid JSON input: " . json_last_error_msg();
        logActivity($errorMsg);
        echo json_encode(["success" => false, "message" => "Invalid JSON input."]);
        exit();
    }

    // Validate food ID
    if (!isset($data['food_id']) || empty($data['food_id'])) {
        $errorMsg = "Missing food ID in request";
        logActivity($errorMsg);
        echo json_encode(["success" => false, "message" => "Food ID is required."]);
        exit();
    }

    $foodId = (int)$data['food_id'];
    logActivity("Attempting to delete food ID: " . $foodId);

    // Start transaction
    $conn->begin_transaction();
    logActivity("Transaction started for food deletion");

    try {
        // Check if food exists and get photo filename if exists
        $checkSql = "SELECT food_id, photo FROM food WHERE food_id = ? FOR UPDATE";
        $stmtCheck = $conn->prepare($checkSql);
        
        if (!$stmtCheck) {
            throw new Exception("Prepare failed for check: " . $conn->error);
        }

        $stmtCheck->bind_param("i", $foodId);
        
        if (!$stmtCheck->execute()) {
            throw new Exception("Execute failed for check: " . $stmtCheck->error);
        }

        $resultCheck = $stmtCheck->get_result();
        
        if ($resultCheck->num_rows == 0) {
            throw new Exception("Food item not found with ID: " . $foodId);
        }

        $foodData = $resultCheck->fetch_assoc();
        $photoFilename = $foodData['photo'] ?? null;
        $stmtCheck->close();

        // Delete food item
        $deleteSql = "DELETE FROM food WHERE food_id = ?";
        $stmtDelete = $conn->prepare($deleteSql);
        
        if (!$stmtDelete) {
            throw new Exception("Prepare failed for delete: " . $conn->error);
        }

        $stmtDelete->bind_param("i", $foodId);
        
        if (!$stmtDelete->execute()) {
            throw new Exception("Execute failed for delete: " . $stmtDelete->error);
        }

        // Delete associated photo if exists
        if ($photoFilename) {
            $photoPath = '../food_photos/' . $photoFilename;
            if (file_exists($photoPath)) {
                if (!unlink($photoPath)) {
                    throw new Exception("Failed to delete food photo");
                }
                logActivity("Deleted food photo: " . $photoFilename);
            }
        }

        $conn->commit();
        $successMsg = "Food deleted successfully. ID: " . $foodId;
        logActivity($successMsg);
        echo json_encode([
            "success" => true, 
            "message" => "Food has been successfully deleted.",
            "food_id" => $foodId
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        $errorMsg = "Food deletion failed: " . $e->getMessage();
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
    logActivity("Food deletion process completed");
}
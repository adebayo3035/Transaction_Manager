<?php
header('Content-Type: application/json');

include('restriction_checker.php');

// Initialize logging
logActivity("Customer deletion process started");

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
    if (!isset($data['customer_id']) || empty($data['customer_id'])) {
        $errorMsg = "Missing customer ID in request";
        logActivity($errorMsg);
        echo json_encode(["success" => false, "message" => "Customer ID is required."]);
        exit();
    }

    $customerId = (int)$data['customer_id'];
    logActivity("Attempting to delete customer ID: " . $customerId);

    // Start transaction
    $conn->begin_transaction();
    logActivity("Transaction started for customer deletion");

    try {
        // Get customer photo filename
        $selectSql = "SELECT photo FROM customers WHERE customer_id = ?";
        $stmt = $conn->prepare($selectSql);
        
        if (!$stmt) {
            throw new Exception("Prepare failed for select: " . $conn->error);
        }

        $stmt->bind_param("i", $customerId);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed for select: " . $stmt->error);
        }

        $stmt->bind_result($photoFilename);
        $stmt->fetch();
        $stmt->close();

        // Delete customer record
        $deleteSql = "DELETE FROM customers WHERE customer_id = ?";
        $stmt = $conn->prepare($deleteSql);
        
        if (!$stmt) {
            throw new Exception("Prepare failed for delete: " . $conn->error);
        }

        $stmt->bind_param("i", $customerId);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed for delete: " . $stmt->error);
        }

        // Delete associated photo if exists
        if ($photoFilename) {
            $photoPath = '../backend/customer_photos/' . $photoFilename;
            if (file_exists($photoPath)) {
                if (!unlink($photoPath)) {
                    throw new Exception("Failed to delete customer photo");
                }
                logActivity("Deleted customer photo: " . $photoFilename);
            }
        }

        $conn->commit();
        $successMsg = "Customer deleted successfully. ID: " . $customerId;
        logActivity($successMsg);
        echo json_encode([
            "success" => true, 
            "message" => "Customer has been successfully deleted.",
            "customer_id" => $customerId
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        $errorMsg = "Customer deletion failed: " . $e->getMessage();
        logActivity($errorMsg);
        echo json_encode([
            "success" => false, 
            "message" => "Failed to delete customer.",
            "error" => $e->getMessage()
        ]);
    } finally {
        if (isset($stmt) && $stmt instanceof mysqli_stmt) {
            $stmt->close();
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
    logActivity("Customer deletion process completed");
}
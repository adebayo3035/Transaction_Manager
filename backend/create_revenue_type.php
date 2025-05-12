<?php
header('Content-Type: application/json');
include_once "config.php";
session_start();

// Initialize logging
logActivity("Revenue type creation process started");

// Check authentication and authorization
if (!isset($_SESSION['unique_id'])) {
    $errorMsg = "Unauthorized access attempt - Not logged in";
    logActivity($errorMsg);
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}

if ($_SESSION['role'] !== "Super Admin") {
    $errorMsg = "Unauthorized access attempt - User: " . $_SESSION['unique_id'];
    logActivity($errorMsg);
    echo json_encode(["success" => false, "message" => "Unauthorized access. Contact your Superior."]);
    exit();
}

try {
    // Validate input
    if (!isset($_POST['revenue_type_name']) || empty(trim($_POST['revenue_type_name']))) {
        $errorMsg = "Revenue type name is required";
        logActivity($errorMsg);
        echo json_encode(['success' => false, 'message' => $errorMsg]);
        exit();
    }

    // Sanitize input
    $revenue_type = trim($_POST['revenue_type_name']);
    $revenue_description = isset($_POST['revenue_description']) ? trim($_POST['revenue_description']) : '';
    
    logActivity("Received input - Type: $revenue_type, Description: $revenue_description");

    // Check for existing revenue type
    $check_sql = "SELECT revenue_type_id FROM revenue_types WHERE revenue_type_name = ?";
    $stmt_check = $conn->prepare($check_sql);
    
    if (!$stmt_check) {
        $errorMsg = "Prepare failed for check: " . $conn->error;
        logActivity($errorMsg);
        throw new Exception("Something went wrong, please try again.");
    }

    $stmt_check->bind_param("s", $revenue_type);
    
    if (!$stmt_check->execute()) {
        $errorMsg = "Execute failed for check: " . $stmt_check->error;
        logActivity($errorMsg);
        throw new Exception("Something went wrong, please try again.");
    }

    $result = $stmt_check->get_result();

    if ($result->num_rows > 0) {
        $errorMsg = "Duplicate revenue type detected: " . $revenue_type;
        logActivity($errorMsg);
        echo json_encode(['success' => false, 'message' => "A record with the revenue type name '{$revenue_type}' already exists."]);
        exit();
    }
    $stmt_check->close();

    // Start transaction
    $conn->begin_transaction();
    logActivity("Transaction started for revenue type creation");

    try {
        // Insert new revenue type
        $insert_sql = "INSERT INTO revenue_types (revenue_type_name, revenue_type_description) VALUES (?, ?)";
        $stmt_insert = $conn->prepare($insert_sql);
        
        if (!$stmt_insert) {
            $errorMsg = "Prepare failed for insert: " . $conn->error;
            logActivity($errorMsg);
            throw new Exception("Something went wrong, please try again.");
        }

        $stmt_insert->bind_param("ss", $revenue_type, $revenue_description);
        
        if ($stmt_insert->execute()) {
            $new_revenue_type_id = $conn->insert_id;
            $conn->commit();
            $successMsg = "Revenue type added successfully. ID: " . $new_revenue_type_id;
            logActivity($successMsg);
            echo json_encode([
                'success' => true, 
                'message' => "Revenue type added successfully.",
                'revenue_type_id' => $new_revenue_type_id
            ]);
        } else {
            $errorMsg = "Execute failed for insert: " . $stmt_insert->error;
            logActivity($errorMsg);
            throw new Exception("Something went wrong, please try again.");
        }
    } catch (Exception $e) {
        $conn->rollback();
        logActivity("Transaction rolled back: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } finally {
        if (isset($stmt_insert) && $stmt_insert instanceof mysqli_stmt) {
            $stmt_insert->close();
        }
    }
} catch (Exception $e) {
    logActivity("System error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred.']);
} finally {
    $conn->close();
    logActivity("Revenue type creation process completed");
}
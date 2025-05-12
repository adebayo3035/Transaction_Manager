<?php
header('Content-Type: application/json');
include_once "config.php";

// Initialize logging
logActivity("Unit creation process started");

// Function to calculate the Levenshtein similarity percentage
function levenshteinPercentage($str1, $str2) {
    $lev = levenshtein($str1, $str2);
    $maxLen = max(strlen($str1), strlen($str2));
    if ($maxLen == 0) {
        return 100; // Both strings are empty
    }
    return (1 - $lev / $maxLen) * 100;
}

try {
    // Validate and sanitize input
    if (!isset($_POST['add_group_name']) || empty(trim($_POST['add_group_name']))) {
        $errorMsg = "Group selection is required";
        logActivity($errorMsg);
        echo json_encode([
            "success" => false,
            "message" => $errorMsg
        ]);
        exit();
    }

    if (!isset($_POST['add_unit_name']) || empty(trim($_POST['add_unit_name']))) {
        $errorMsg = "Unit name is required";
        logActivity($errorMsg);
        echo json_encode([
            "success" => false,
            "message" => $errorMsg
        ]);
        exit();
    }

    $group_id = intval($_POST['add_group_name']); // Convert to integer for safety
    $unit_name = trim($_POST['add_unit_name']);
    logActivity("Received input - Group ID: $group_id, Unit Name: $unit_name");

    // Function to check if a similar unit exists
    function isSimilarUnitExists($conn, $newUnitName, $threshold = 50) {
        logActivity("Checking for similar units to: " . $newUnitName);
        $sql = "SELECT unit_name FROM unit WHERE unit_name LIKE ?";
        $likeQuery = '%' . $newUnitName . '%';
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            logActivity("Prepare failed: " . $conn->error);
            return false;
        }
        
        $stmt->bind_param('s', $likeQuery);
        
        if (!$stmt->execute()) {
            logActivity("Execute failed: " . $stmt->error);
            return false;
        }
        
        $result = $stmt->get_result();
        $similarUnits = [];

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $similarity = levenshteinPercentage($row['unit_name'], $newUnitName);
                $similarUnits[$row['unit_name']] = $similarity;
                
                if ($similarity >= $threshold) {
                    logActivity("Found similar unit: " . $row['unit_name'] . " (similarity: " . $similarity . "%)");
                    return true;
                }
            }
        }
        
        logActivity("No similar units found above threshold. Similarities: " . json_encode($similarUnits));
        return false;
    }

    // Check for similar unit names
    if (isSimilarUnitExists($conn, $unit_name)) {
        $errorMsg = "A similar unit name exists: " . $unit_name;
        logActivity($errorMsg);
        echo json_encode([
            "success" => false,
            "message" => $errorMsg
        ]);
        exit();
    }

    // Start transaction
    $conn->begin_transaction();
    logActivity("Transaction started for unit creation");

    try {
        // Insert new unit
        $sql = "INSERT INTO unit (unit_name, group_id) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param('si', $unit_name, $group_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Insert failed: " . $stmt->error);
        }

        // Retrieve the newly inserted unit
        $new_unit_id = $conn->insert_id;
        $sql_select = "SELECT * FROM unit WHERE unit_id = ?";
        $stmt_select = $conn->prepare($sql_select);
        
        if (!$stmt_select) {
            throw new Exception("Prepare failed for select: " . $conn->error);
        }

        $stmt_select->bind_param('i', $new_unit_id);
        
        if (!$stmt_select->execute()) {
            throw new Exception("Select failed: " . $stmt_select->error);
        }

        $result = $stmt_select->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Unit inserted but not retrievable");
        }

        $unit = $result->fetch_assoc();
        
        // Commit transaction if everything succeeded
        $conn->commit();
        logActivity("Transaction committed successfully. Unit created: " . $unit_name);

        echo json_encode([
            "success" => true,
            "message" => "New Unit has been successfully created.",
            "unit" => $unit
        ]);

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $errorMsg = "Unit creation failed: " . $e->getMessage();
        logActivity($errorMsg);
        
        echo json_encode([
            "success" => false,
            "message" => "Something went wrong, please try again.",
            "error" => $e->getMessage()
        ]);
    }

} catch (Exception $e) {
    logActivity("Exception: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => "An error occurred",
        "error" => $e->getMessage()
    ]);
} finally {
    // Clean up resources
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    if (isset($stmt_select) && $stmt_select instanceof mysqli_stmt) {
        $stmt_select->close();
    }
    $conn->close();
    logActivity("Unit creation process completed");
}
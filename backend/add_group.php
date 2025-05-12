<?php
header('Content-Type: application/json');
include_once "config.php";

// Initialize logging
logActivity("Group addition process started");

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
        $errorMsg = "Group name is required";
        logActivity($errorMsg);
        echo json_encode([
            "success" => false,
            "message" => $errorMsg
        ]);
        exit();
    }

    $group_name = trim($_POST['add_group_name']);
    logActivity("Received group name: " . $group_name);

    // Function to check if a similar group exists
    function isSimilarGroupExists($conn, $newGroupName, $threshold = 50) {
        logActivity("Checking for similar groups to: " . $newGroupName);
        $sql = "SELECT group_name FROM groups WHERE group_name LIKE ?";
        $likeQuery = '%' . $newGroupName . '%';
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
        $similarGroups = [];

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $similarity = levenshteinPercentage($row['group_name'], $newGroupName);
                $similarGroups[$row['group_name']] = $similarity;
                
                if ($similarity >= $threshold) {
                    logActivity("Found similar group: " . $row['group_name'] . " (similarity: " . $similarity . "%)");
                    return true;
                }
            }
        }
        
        logActivity("No similar groups found above threshold. Similarities: " . json_encode($similarGroups));
        return false;
    }

    // Check for similar group names
    if (isSimilarGroupExists($conn, $group_name)) {
        $errorMsg = "A similar group name exists: " . $group_name;
        logActivity($errorMsg);
        echo json_encode([
            "success" => false,
            "message" => $errorMsg
        ]);
        exit();
    }

    // Insert new group
    $sql = "INSERT INTO groups (group_name) VALUES (?)";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        $errorMsg = "Prepare failed: " . $conn->error;
        logActivity($errorMsg);
        throw new Exception($errorMsg);
    }
    
    $stmt->bind_param('s', $group_name);

    if ($stmt->execute()) {
        logActivity("Group successfully inserted: " . $group_name);
        
        // Retrieve the newly inserted group
        $sql_select = "SELECT * FROM groups WHERE group_name = ?";
        $stmt_select = $conn->prepare($sql_select);
        
        if (!$stmt_select) {
            $errorMsg = "Prepare failed for select: " . $conn->error;
            logActivity($errorMsg);
            throw new Exception($errorMsg);
        }
        
        $stmt_select->bind_param('s', $group_name);
        
        if (!$stmt_select->execute()) {
            $errorMsg = "Execute failed for select: " . $stmt_select->error;
            logActivity($errorMsg);
            throw new Exception($errorMsg);
        }
        
        $result = $stmt_select->get_result();

        if ($result->num_rows > 0) {
            $group = $result->fetch_assoc();
            $successMsg = "Group successfully added: " . $group_name;
            logActivity($successMsg);
            
            echo json_encode([
                "success" => true,
                "message" => $successMsg,
                "group" => $group
            ]);
        } else {
            $errorMsg = "Group inserted but not found in database";
            logActivity($errorMsg);
            echo json_encode([
                "success" => false,
                "message" => $errorMsg
            ]);
        }
        
        $stmt_select->close();
    } else {
        $errorMsg = "Insert failed: " . $stmt->error;
        logActivity($errorMsg);
        echo json_encode([
            "success" => false,
            "message" => "Failed to add group",
            "error" => $errorMsg
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
    $conn->close();
    logActivity("Group addition process completed");
}
<?php
header('Content-Type: application/json');
include_once "config.php";

// Initialize logging
logActivity("Team creation process started");

try {
    // Validate inputs
    if (!isset($_POST['group_id']) || empty(trim($_POST['group_id']))) {
        $errorMsg = "Group selection is required";
        logActivity($errorMsg);
        echo json_encode([
            "success" => false,
            "message" => $errorMsg
        ]);
        exit();
    }

    if (!isset($_POST['team_name']) || empty(trim($_POST['team_name']))) {
        $errorMsg = "Team name is required";
        logActivity($errorMsg);
        echo json_encode([
            "success" => false,
            "message" => $errorMsg
        ]);
        exit();
    }

    // Sanitize inputs
    $group_id = intval($_POST['group_id']);
    $team_name = trim($_POST['team_name']);
    logActivity("Received input - Group ID: $group_id, Team Name: $team_name");

    // Check if team exists using prepared statement
    $check_sql = "SELECT team_id FROM team WHERE team_name LIKE ?";
    $stmt_check = $conn->prepare($check_sql);
    
    if (!$stmt_check) {
        $errorMsg = "Prepare failed: " . $conn->error;
        logActivity($errorMsg);
        throw new Exception($errorMsg);
    }

    $search_term = '%' . $team_name . '%';
    $stmt_check->bind_param('s', $search_term);
    
    if (!$stmt_check->execute()) {
        $errorMsg = "Execute failed: " . $stmt_check->error;
        logActivity($errorMsg);
        throw new Exception($errorMsg);
    }

    $result = $stmt_check->get_result();

    if ($result->num_rows > 0) {
        $errorMsg = "Team name already exists: " . $team_name;
        logActivity($errorMsg);
        echo json_encode([
            "success" => false,
            "message" => $errorMsg
        ]);
        exit();
    }
    $stmt_check->close();

    // Insert new team
    $insert_sql = "INSERT INTO team (team_name, group_id) VALUES (?, ?)";
    $stmt_insert = $conn->prepare($insert_sql);
    
    if (!$stmt_insert) {
        $errorMsg = "Prepare failed: " . $conn->error;
        logActivity($errorMsg);
        throw new Exception($errorMsg);
    }

    $stmt_insert->bind_param('si', $team_name, $group_id);
    
    if ($stmt_insert->execute()) {
        logActivity("Team successfully inserted: " . $team_name);
        
        // Retrieve the newly inserted team
        $select_sql = "SELECT * FROM team WHERE team_name = ?";
        $stmt_select = $conn->prepare($select_sql);
        
        if (!$stmt_select) {
            $errorMsg = "Prepare failed for select: " . $conn->error;
            logActivity($errorMsg);
            throw new Exception($errorMsg);
        }

        $stmt_select->bind_param('s', $team_name);
        
        if (!$stmt_select->execute()) {
            $errorMsg = "Execute failed for select: " . $stmt_select->error;
            logActivity($errorMsg);
            throw new Exception($errorMsg);
        }

        $result = $stmt_select->get_result();
        
        if ($result->num_rows > 0) {
            $team = $result->fetch_assoc();
            $successMsg = "Team created successfully";
            logActivity($successMsg . " - Team ID: " . $team['team_id']);
            
            echo json_encode([
                "success" => true,
                "message" => $successMsg,
                "team" => $team
            ]);
        } else {
            $errorMsg = "Team inserted but not found in database";
            logActivity($errorMsg);
            echo json_encode([
                "success" => false,
                "message" => $errorMsg
            ]);
        }
        
        $stmt_select->close();
    } else {
        $errorMsg = "Insert failed: " . $stmt_insert->error;
        logActivity($errorMsg);
        echo json_encode([
            "success" => false,
            "message" => "Failed to create team",
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
    if (isset($stmt_check) && $stmt_check instanceof mysqli_stmt) {
        $stmt_check->close();
    }
    if (isset($stmt_insert) && $stmt_insert instanceof mysqli_stmt) {
        $stmt_insert->close();
    }
    if (isset($stmt_select) && $stmt_select instanceof mysqli_stmt) {
        $stmt_select->close();
    }
    $conn->close();
    logActivity("Team creation process completed");
}
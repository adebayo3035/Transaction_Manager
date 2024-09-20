<?php
header('Content-Type: application/json');
include_once "config.php";

// Function to calculate the Levenshtein similarity percentage
function levenshteinPercentage($str1, $str2) {
    $lev = levenshtein($str1, $str2);
    $maxLen = max(strlen($str1), strlen($str2));
    if ($maxLen == 0) {
        return 100; // Both strings are empty
    }
    return (1 - $lev / $maxLen) * 100;
}

$group_name = mysqli_real_escape_string($conn, $_POST['add_group_name']);

// Function to check if a similar group exists
function isSimilarGroupExists($conn, $newGroupName, $threshold = 50) {
    $sql = "SELECT group_name FROM groups WHERE group_name LIKE ?";
    $likeQuery = '%' . $newGroupName . '%';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $likeQuery);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $existingGroupName = $row['group_name'];
            if (levenshteinPercentage($existingGroupName, $newGroupName) >= $threshold) {
                return true;
            }
        }
    }
    return false;
}

if (!empty($group_name)) {
    // Check for similar group names
    if (isSimilarGroupExists($conn, $group_name)) {
        echo json_encode([
            "success" => false,
            "message" => "A similar group name exists. Please try another name."
        ]);
        exit();
    } 

        // Insert new group using a prepared statement
        $sql = "INSERT INTO groups (group_name) VALUES (?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $group_name);

        if ($stmt->execute()) {
            // Retrieve the newly inserted group to confirm the addition
            $sql_select = "SELECT * FROM groups WHERE group_name = ?";
            $stmt_select = $conn->prepare($sql_select);
            $stmt_select->bind_param('s', $group_name);
            $stmt_select->execute();
            $result = $stmt_select->get_result();

            if ($result->num_rows > 0) {
                $group = $result->fetch_assoc();
                echo json_encode([
                    "success" => true,
                    "message" => "Group successfully added.",
                    "group" => $group
                ]);
            } else {
                echo json_encode([
                    "success" => false,
                    "message" => "Something went wrong, please try again."
                ]);
            }
        } else {
            echo json_encode([
                "success" => false,
                "message" => "Something went wrong, please try again.",
                "error" => $stmt->error
            ]);
        }

        $stmt->close();
 
}else {
    echo json_encode([
        "success" => false,
        "message" => "Please provide the group name."
    ]);
}
$conn->close();

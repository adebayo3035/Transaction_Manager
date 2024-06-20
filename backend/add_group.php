<?php
// session_start();
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

$group_name = mysqli_real_escape_string($conn, $_POST['group_name']);

// Function to check if a similar group exists
function isSimilarGroupExists($conn, $newGroupName, $threshold = 50) {
    $sql = "SELECT group_name FROM groups WHERE group_name LIKE '%$newGroupName%'";
    $result = $conn->query($sql);

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
    // $sql_check = mysqli_query($conn, "SELECT * FROM groups WHERE group_name = '{$group_name}'");



    if (isSimilarGroupExists($conn, $group_name)) {
        echo "A similar group name exists. Please Try another Name.";
        exit();
    } else {
        $insert_query = mysqli_query($conn, "INSERT INTO groups (group_name) VALUES ('{$group_name}')");
        if ($insert_query) {
            $select_sql2 = mysqli_query($conn, "SELECT * FROM groups WHERE group_name = '{$group_name}'");
            if (mysqli_num_rows($select_sql2) > 0) {
                $result = mysqli_fetch_assoc($select_sql2);
                echo "success";
            } else {
                echo "Something Went wrong, Please Try Again" . $conn->error;
            }
        } else {
            echo "Something Went wrong, Please Try Again" . $conn->error;
        }
    }

    $conn->close();

    // $sql_check = mysqli_query($conn, "SELECT * FROM groups WHERE group_name LIKE '%$group_name%'");

    // Check if group already exist in DB
    // if (mysqli_num_rows($sql_check) > 0) {
    //     echo "Group Already Exist";
    //     exit();
    // } 
} else {
    echo "Please provide the Group Name";
}


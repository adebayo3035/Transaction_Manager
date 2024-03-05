<?php
// session_start();
include_once "config.php";

$group_name = mysqli_real_escape_string($conn, $_POST['group_name']);

if (!empty($group_name)) {
    $sql_check = mysqli_query($conn, "SELECT * FROM groups WHERE group_name = '{$group_name}'");

    // Check if group already exist in DB
    if (mysqli_num_rows($sql_check) > 0) {
        echo "Group Already Exist";
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
} else {
    echo "Please provide the Group Name";
}


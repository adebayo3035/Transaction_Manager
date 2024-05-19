<?php
if (isset($_POST['btnUpdateGroup'])) {
    include_once "config.php";
    session_start();
    $group_name = mysqli_real_escape_string($conn, $_POST['group_name']);
    $group_id = mysqli_real_escape_string($conn, $_POST['group_id']);
    if (!empty($group_name) && !empty($group_id)) {

        // Prepare and execute a query to check if the email or phone number already exists
        $sql_group = mysqli_query($conn, "SELECT group_name FROM groups WHERE group_name = '{$group_name}'");
        if (mysqli_num_rows($sql_group) > 0) {
            echo "<script>alert('$group_name already exists. Please use a different Group Name.');window.location.href='../edit_group.php?id=" . $group_id . "';</script>";
            exit();
        } else {
            $updateGroupQuery = "UPDATE groups SET group_name = '{$group_name}' WHERE group_id = '{$group_id}'";
            $updateGroupResult = mysqli_query($conn, $updateGroupQuery);

            if (!$updateGroupResult) {
                //handle_error('Error updating session: ' . mysqli_error($conn));
                echo "<script>alert('Error Updating Group Name')" . mysqli_error($conn) . " window.location.href='../edit_group.php?id=" . $group_id . "'; </script>";
            } else {
                echo "<script>alert('Group Name has been successfully Updated.'); window.location.href='../groups.php'; </script>";
            }



        }


    } else {
        echo "<script>alert(' All Input fields are required.'); </script>";
    }

}

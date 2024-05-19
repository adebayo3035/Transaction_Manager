<?php
if (isset($_POST['btnUpdateUnit'])) {
    include_once "config.php";
    session_start();
    $unit_name = mysqli_real_escape_string($conn, $_POST['unit_name']);
    $current_unit = mysqli_real_escape_string($conn, $_POST['unit_name_hidden']);
    $group_id = mysqli_real_escape_string($conn, $_POST['group_id']);
    $unit_id = mysqli_real_escape_string($conn, $_POST['unit_id']);
    if (!empty($unit_name) && !empty($group_id) && !empty($unit_id)) {
        if ($unit_name != $current_unit) {
            // Prepare and execute a query to check if the email or phone number already exists
            $sql_Unit = mysqli_query($conn, "SELECT unit_name, group_id FROM unit WHERE unit_name = '{$unit_name}'");
            if (mysqli_num_rows($sql_Unit) > 0) {
                //echo $unit_name . " and ". $group_id;
                echo "<script>alert('$unit_name already exists. Please use a different Unit Name.');window.location.href='../edit_unit.php?id=" . $unit_id . "';</script>";
                exit();
            } else {
                $updateUnitQuery = "UPDATE unit SET unit_name = '{$unit_name}', group_id = '{$group_id}' WHERE unit_id = '{$unit_id}'";
                $updateUnitResult = mysqli_query($conn, $updateUnitQuery);

                if (!$updateUnitResult) {
                    //handle_error('Error updating session: ' . mysqli_error($conn));
                    echo "<script>alert('Error Updating Unit and Group Name')" . mysqli_error($conn) . " window.location.href='../edit_unit.php?id=" . $unit_id . "'; </script>";
                } else {
                    echo "<script>alert('Unit Information has been successfully Updated.'); window.location.href='../units.php'; </script>";
                }

            }
        }
        else{
                $updateUnitQuery = "UPDATE unit SET unit_name = '{$unit_name}', group_id = '{$group_id}' WHERE unit_id = '{$unit_id}'";
                $updateUnitResult = mysqli_query($conn, $updateUnitQuery);

                if (!$updateUnitResult) {
                    //handle_error('Error updating session: ' . mysqli_error($conn));
                    echo "<script>alert('Error Updating Unit and Group Name')" . mysqli_error($conn) . " window.location.href='../edit_unit.php?id=" . $unit_id . "'; </script>";
                } else {
                    echo "<script>alert('Unit Information has been successfully Updated.'); window.location.href='../units.php'; </script>";
                }
        }

    } else {
        echo "<script>alert(' All Input fields are required.'); </script>";
    }

}

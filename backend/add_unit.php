<?php
// session_start();
include_once "config.php";

$group_id = mysqli_real_escape_string($conn, $_POST['group_id']);
$unit_name = mysqli_real_escape_string($conn, $_POST['unit_name']);

if(!empty($group_id)){
    if (!empty($unit_name)) {
        $sql_check = mysqli_query($conn, "SELECT * FROM unit WHERE unit_name = '{$unit_name}'");
    
        // Check if Unit already exist in DB
        if (mysqli_num_rows($sql_check) > 0) {
            echo "Unit Already Exist";
            exit();
        } 
        else {
            $insert_query = mysqli_query($conn, "INSERT INTO unit (unit_name, group_id) VALUES ('{$unit_name}', '{$group_id}')");
            if ($insert_query) {
                $select_sql2 = mysqli_query($conn, "SELECT * FROM unit WHERE unit_name = '{$unit_name}'");
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
    }else {
        echo "Please Enter a Valid Unit Name";
    }
    
}
else{
    echo "Please Select the Group your Unit belongs to";
}



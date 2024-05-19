<?php
// session_start();
include_once "config.php";

$group_id = mysqli_real_escape_string($conn, $_POST['group_id']);
$team_name = mysqli_real_escape_string($conn, $_POST['team_name']);

if(!empty($group_id)){
    if (!empty($team_name)) {
        $sql_check = mysqli_query($conn, "SELECT * FROM team WHERE team_name LIKE '%{$team_name}%'");
       
    
        // Check if team already exist in DB
        if (mysqli_num_rows($sql_check) > 0) {
            echo "A Team with this name already exist. Please Try Another  Name";
            exit();
        } 
        else {
            $insert_query = mysqli_query($conn, "INSERT INTO team (team_name, group_id) VALUES ('{$team_name}', '{$group_id}')");
            if ($insert_query) {
                $select_sql2 = mysqli_query($conn, "SELECT * FROM team WHERE team_name = '{$team_name}'");
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
        echo "Please Enter a Valid team Name";
    }
    
}
else{
    echo "Please Select the Group your team belongs to";
}



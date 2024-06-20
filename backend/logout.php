<?php
    session_start();
    if(isset($_SESSION['unique_id'])){
        include_once "config.php";
        $logout_id = mysqli_real_escape_string($conn, $_GET['logout_id']);
        if(isset($logout_id)){
            // $session_status_new = 0;
                // $sql2 = mysqli_query($conn, "UPDATE sessions SET session_status = '{$session_status_new}' WHERE user_id = '{$_SESSION['unique_id']}'");
                // if($sql2){
                    session_unset();
                    session_destroy();
                    header("location: ../index.php");
                // }
                // else{
                //     echo 'Something went wrong. Please Try Again';
                // }
        }else{
            header("location: ../homepage.php");
        }
    }else{  
        header("location: ../index.php");
    }
?>
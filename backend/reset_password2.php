<?php
include_once "config.php";
if (isset($_POST['btnPasswordChange'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $secretAnswer = (md5(filter_var($_POST['secret_answer'], FILTER_SANITIZE_STRING)));
    if (!empty($email) && !empty($secretAnswer)) {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // Prepare and execute a query to check if the email exists
            // Perform E-mail address Validation
            $email_query = mysqli_query($conn, "select unique_id from `admin_tbl` where email='$email' AND secret_answer = '$secretAnswer'");
            $row = mysqli_fetch_array($email_query);
            if ($row) {
                $uniqueID = $row['unique_id'];
                //store retrieved User ID in a session variable
                session_start();
                $_SESSION['customerID'] = $row['unique_id'];
                // Set session variable to indicate user is coming from reset_password.php
                $_SESSION['from_reset_password'] = true;
                echo "<script>alert('Validation Successful'); </script>";
                echo "<script>window.location.href='../password_reset.php?id=" . $_SESSION['customerID'] . "';</script>";
            } else {
                echo "<script>alert('Invalid Email Address or Secret Answer'); </script>";
                echo "<script>window.location.href='../reset_password.php';</script>";
            }
        }
        else{
            echo "<script>alert('Invalid E-mail Address'); </script>";
            echo "<script>window.location.href='../reset_password.php';</script>";
            
        }
    }
    else{
        echo "<script>alert('Please Input all required Parameters'); </script>";
        echo "<script>window.location.href='../reset_password.php';</script>";
            
    }
   
}
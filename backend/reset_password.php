<?php
include_once "config.php";
if (isset($_POST['btnPasswordChange'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $secretAnswer = ((filter_var($_POST['secret_answer'], FILTER_SANITIZE_STRING)));

    if (!empty($email) && !empty($secretAnswer)) {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // Prepare and execute a query to check if the email exists
            // Perform E-mail address Validation
            $email_query = mysqli_query($conn, "select * from `admin_tbl` where email='$email'");
            if (mysqli_num_rows($email_query) == 1) {
                $row = mysqli_fetch_assoc($email_query);
                $encrypted_Answer = md5($secretAnswer);
                $admin_email = ($row['email']);
                $admin_answer = ($row['secret_answer']);
                if (($email == $admin_email) && ($encrypted_Answer == $admin_answer)) {
                    $uniqueID = $row['unique_id']; // Get admin ID for password update
                    //store retrieved User ID in a session variable
                    session_start();
                    $_SESSION['customerID'] = $row['unique_id'];
                    // Set session variable to indicate user is coming from reset_password.php
                    $_SESSION['from_reset_password'] = true;
                    echo "<script>alert('Validation Successful'); </script>";
                    echo "<script>window.location.href='../password_reset.php?id=" . $_SESSION['customerID'] . "';</script>";
                } 
                else {
                    echo "<script>alert('Invalid Credentials. Error Resetting Password.'); window.location.href='../index.php'; </script>";
                }

            } 
            else {
                echo "<script>alert('Error Validating User Information')" . mysqli_error($conn) . " window.location.href='../index.php'; </script>";
            }

        } 
        else {
            echo "<script>alert('Invalid E-mail Address. Please Input a valid E-mail')" . mysqli_error($conn) . " window.location.href='../index.php'; </script>";
        }
    } 
    else {
        echo "<script>alert('All Input Fields are required')" . mysqli_error($conn) . " window.location.href='../index.php'; </script>";
    }
}
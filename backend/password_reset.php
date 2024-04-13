<?php
include 'config.php';
session_start();
// include_once "config.php";
// if(!isset($_SESSION['customerID'])){
//   header("location: logout.php");
// }

if (!isset($_SESSION['from_reset_password']) || !$_SESSION['from_reset_password']) {
    // Redirect the user to password_reset.php
    header("Location: reset_password.php");
    exit(); // Stop further execution
} else {
    if (isset($_SESSION['customerID'])) {
        $id = $_SESSION['customerID'];
        $new_password = mysqli_real_escape_string($conn, $_POST['new_password']);
        $confirm_password = mysqli_real_escape_string($conn, $_POST['confirm_password']);
        if ($new_password === $confirm_password) {
            // REGEX TO VALIDATE PASSWORD AND SECRET ANSWER
            $minLength = 8;
            $hasSpecialChar = preg_match('/[!@#$%^&*(),.?":{}|<>_]/', $new_password);
            $hasUpperCase = preg_match('/[A-Z]/', $new_password);
            $hasDigit = preg_match('/\d/', $new_password);
            if(!empty($new_password) && !empty($confirm_password)){
                // Apply password validation to password fields
                if(((strlen($new_password)) < $minLength) || (!$hasSpecialChar) || (!$hasUpperCase) || (!$hasDigit)){
                    echo "<script>alert(' Please Input a Valid Password.'); window.location.href='../password_reset.php?id=" . $id . "';</script>";
                }
                else{
                    $encrypted_Password = md5($new_password);
                    $updatePasswordQuery = "UPDATE admin_tbl SET password = '{$encrypted_Password}' WHERE unique_id = '{$id}'";
                    $updatePasswordResult = mysqli_query($conn, $updatePasswordQuery);

                    if (!$updatePasswordResult) {
                        //handle_error('Error updating session: ' . mysqli_error($conn));
                        echo "<script>alert('Error Updating Password')".mysqli_error($conn)." window.location.href='../password_reset.php'; </script>";
                    }
                    else{
                        echo "<script>alert('Password has been successfully Changed.'); window.location.href='../index.php'; </script>";
                    }
                }
            }
            else{
                    echo "<script>alert(' Passwords fields cannot be empty.'); window.location.href='../password_reset.php?id=" . $id . "';</script>";
            }
               
        }
        else{
            echo "<script>alert(' Your Password does not match.'); window.location.href='../password_reset.php?id=" . $id . "';</script>";
        }
    }
}

// Clear the session variable
unset($_SESSION['from_reset_password']);
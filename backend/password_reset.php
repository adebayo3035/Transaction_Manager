<?php
session_start(); // Start session for potential error messages
// Restrict access to this Page if E-mail has not been confirmed
if(!isset($_SESSION['customerID'])){
    header("location: ../index.php");
}
else{
    $unique_id = $_SESSION['customerID'];
// Include database connection file (replace with your connection details)
require_once('config.php');

$errorMsg = ""; // Initialize error message variable

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['new_password']) && isset($_POST['confirm_password'])) {
        // Password reset form submission
        $uniqueID = filter_var($unique_id, FILTER_SANITIZE_NUMBER_INT);
        $newPassword = filter_var($_POST['new_password'], FILTER_SANITIZE_STRING);
        $confirmPassword = filter_var($_POST['confirm_password'], FILTER_SANITIZE_STRING);

        // Validate password match
        if ($newPassword !== $confirmPassword) {
        $errorMsg = "Passwords do not match. Please try again.";
        }
        else {
       
            // REGEX TO VALIDATE PASSWORD 
            $minLength = 8;
            $hasSpecialChar = preg_match('/[!@#$%^&*(),.?":{}|<>_]/', $newPassword);
            $hasUpperCase = preg_match('/[A-Z]/', $newPassword);
            $hasDigit = preg_match('/\d/', $newPassword);
            if(((strlen($newPassword)) < $minLength) || (!$hasSpecialChar) || (!$hasUpperCase) || (!$hasDigit)){
                echo "<script>alert(' Please Input a Valid Password.'); window.location.href='../password_reset.php';</script>";
            }
            else{
                // Hash the new password
                $hashedPassword = md5($newPassword);
                // Update password in admin_tbl
                $sql = "UPDATE admin_tbl SET password = ? WHERE unique_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $hashedPassword, $uniqueID);
                $stmt->execute();

                if ($stmt->affected_rows === 1) {
                    // Password update successful - display success message
                    echo "<script>alert('Password Reset Successful'); window.location.href='../index.php';</script>";
                    session_destroy();
                } 
                else {
                        $errorMsg = "Error updating password. Please try again.";
                }

                $stmt->close();
            }
        }
    }
    else{
        echo "<script>alert('Something went wrong'); window.location.href='../index.php';</script>";
        session_destroy();
    }

}

}


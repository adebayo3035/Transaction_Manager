<?php
if (isset($_POST['btnUpdateAnswer'])) {
    include_once "config.php";
    session_start();
    $id = $_SESSION['unique_id'];
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $question = mysqli_real_escape_string($conn, $_POST['secret_question']);
    $secret_answer = (filter_var($_POST['secret_answer'], FILTER_SANITIZE_STRING));
    $confirm_answer = (filter_var($_POST['confirm_answer'], FILTER_SANITIZE_STRING));
        if (!empty($email) && !empty($question) && !empty($secret_answer) && !empty($confirm_answer)) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // Prepare and execute a query to check if the email exists
                // Perform E-mail address Validation
                $email_query = mysqli_query($conn, "select email from `admin_tbl` where unique_id='$id'");
                $row = mysqli_fetch_array($email_query);
                if ($row) {
                    $admin_email = ($row['email']);
                    if (($email === $admin_email) && ($secret_answer === $confirm_answer)) {
                        $encrypted_answer = md5($secret_answer);
                        //Update customer's Secret Question and Answer
                        $updateAdminQuery = "UPDATE admin_tbl SET secret_question = '{$question}', secret_answer = '{$encrypted_answer}'  WHERE unique_id = '{$id}'";
                        $updateAdminResult = mysqli_query($conn, $updateAdminQuery);

                        if (!$updateAdminResult) {
                            
                            echo "<script>alert('Error Updating Secret Question and Answer')".mysqli_error($conn)." window.location.href='../secret_answer.php_staff.php?id=" . $id . "'; </script>";
                        }
                        else{
                            echo "<script>alert('Your Secret Question and Answer has been changed Successfully.'); window.location.href='../settings.php'; </script>";
                        }
                        
                    } 
                    else {
                        echo "<script>alert('Invalid Credentials. Error Updating Secret Question and Answer.'); window.location.href='../secret_answer.php?id=" . $id . "'; </script>";
                    }
                } 
                else {
                    echo "<script>alert('Error Validating User Info')".mysqli_error($conn)." window.location.href='../secret_answer.php_staff.php?id=" . $id . "'; </script>";
                }
            }

        } 
        else {
            echo "<script>alert(' $email is Invalid. Input a valid E-mail.'); </script>";
        }
    } 
    else {
        echo "<script>alert(' All Input fields are required.'); </script>";
    }

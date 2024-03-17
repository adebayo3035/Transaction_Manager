<?php
    include_once "config.php";
    if ($_SERVER['REQUEST_METHOD'] === 'POST'){
        $email = filter_var($_POST['email'], FILTER_SANITIZE_STRING);
        $secretAnswer = md5(filter_var($_POST['secret_answer'], FILTER_SANITIZE_STRING));
         // Validate email and secret answer
        $sql = "SELECT * FROM admin_tbl WHERE email = ? AND secret_answer = ?";
        // $sql = "SELECT * FROM admin_tbl WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $email, $secretAnswer);
   
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            // Valid email and secret answer - display password reset form
            $row = $result->fetch_assoc();
            $uniqueID = $row['unique_id']; // Get admin ID for password update
            //store retrieved User ID in a session variable
            session_start();
            $_SESSION['customerID'] = $row['unique_id'];
            echo "<script>window.location.href='../password_reset.php';</script>";
        }
        else{
            echo "<script>alert(' Something went wrong, Please try again'); window.location.href='../index.php';</script>";
        }
    }
    else{
        echo "<script>window.location.href='../index.php';</script>";
    }
    
    
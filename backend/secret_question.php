<?php
    include_once "config.php";
    if ($_SERVER['REQUEST_METHOD'] === 'POST'){
        $email = filter_var($_POST['email'], FILTER_SANITIZE_STRING);
        $password = md5(filter_var($_POST['password'], FILTER_SANITIZE_STRING));
         // Validate email and secret answer
        $sql = "SELECT * FROM admin_tbl WHERE email = ? AND password = ?";
        // $sql = "SELECT * FROM admin_tbl WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $email, $password);
   
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            // Valid email and password - display Secret Question
            $row = $result->fetch_assoc();
            $secret_question = $row['secret_question']; // Get Secret Question
            
            echo "<script> alert('Your secret Question is: $secret_question;'); window.location.href='../index.php'; </script>";
        }
        else{
            echo "<script>alert(' Something went wrong, Please try again'); window.location.href='../index.php';</script>";
        }
    }
    else{
        echo "<script>window.location.href='../index.php';</script>";
    }
    
    
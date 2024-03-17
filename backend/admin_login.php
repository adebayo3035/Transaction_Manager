<?php 
    session_start();
    include_once "config.php";
    $email = mysqli_real_escape_string($conn, $_POST['username']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    if(!empty($email) && !empty($password)){
        $sql = mysqli_query($conn, "SELECT * FROM admin_tbl WHERE email = '{$email}'");
        if(mysqli_num_rows($sql) > 0){
            $row = mysqli_fetch_assoc($sql);
            $user_pass = md5($password);
            $enc_pass = $row['password'];
            if($user_pass === $enc_pass){
                $status = "Active now";
                // $sql2 = mysqli_query($conn, "UPDATE users SET status = '{$status}' WHERE unique_id = {$row['unique_id']}");
                // if($sql2){
                    $_SESSION['unique_id'] = $row['unique_id'];
                    $_SESSION['firstname'] = $row['firstname'];
                    $_SESSION['lastname'] = $row['lastname'];
                    $_SESSION['role'] = $row['role'];
                    $_SESSION['secret_answer'] = md5($row['secret_answer']);
                    echo "success";
                // }
                // else{
                //     echo "Something went wrong. Please try again!";
                // }
            }
            else{
                echo "Email or Password is Incorrect!";
            }
        }
        else{
            echo "$email - This email does not Exist!";
        }
    }
    else{
        echo "All input fields are required!";
    }
// 
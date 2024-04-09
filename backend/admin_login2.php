<?php
session_start();
include_once "config.php";
$email = mysqli_real_escape_string($conn, $_POST['username']);
$password = mysqli_real_escape_string($conn, $_POST['password']);
    if (!empty ($email) && !empty ($password)) {
        $sql = mysqli_query($conn, "SELECT * FROM admin_tbl WHERE email = '{$email}'");
        if (mysqli_num_rows($sql) > 0) {
            $row = mysqli_fetch_assoc($sql);
            $user_pass = md5($password);
            $enc_pass = $row['password'];
            $user_id = $row['unique_id'];
            if($user_pass != $enc_pass){
                echo "Invalid Login Credentials. Please Try Again";
            }
            else{
                $session_active = 1;
                $session_inactive = 0;
                $sql2 = mysqli_query($conn, "SELECT * FROM sessions WHERE user_id = '{$user_id}' AND session_status = '{$session_active}'");
                //$result = $stmt->get_result();
                if (mysqli_num_rows($sql2) > 0) {
                    $sql3 = mysqli_query($conn, "UPDATE sessions SET session_status = '{$session_inactive}' WHERE user_id = '{$user_id}'");
                    if($sql3){
                        session_unset();
                        session_destroy();
                        header("location: ../index.php");
                    }
                }
                else{
                    echo 'Something went wrong. Please Try Again';
                }
                exit();
            }

            }
            
            else{
                echo "This User does not have an active session";
                $last_activity = $currentDateTime = date('Y-m-d H:i:s');
                $sql3 = mysqli_query($conn, "UPDATE sessions SET session_status = '{$session_active}', last_activity = '{$last_activity}'  WHERE user_id = '{$user_id}'");
                if ($sql3){
                    
                        $status = "Active now";
                        $_SESSION['unique_id'] = $row['unique_id'];
                        $_SESSION['firstname'] = $row['firstname'];
                        $_SESSION['lastname'] = $row['lastname'];
                        $_SESSION['role'] = $row['role'];
                        $_SESSION['secret_answer'] = md5($row['secret_answer']);
                        echo "success";

                    }
                    else{
                        echo "Email or Password is Incorrect!";
                    } 
                }
                else{
                    echo 'Something went wrong, Please Try Again';
                    echo mysqli_error($conn);
                }
            }
        }
        else{
            echo "$email - This email does not Exist!";
        }
    }
    else{
        echo "All input fields are required!";
    } 
?>
<?php
    // session_start();
    include_once "config.php";

    $firstname = mysqli_real_escape_string($conn, $_POST['firstName']);
    $lastname = mysqli_real_escape_string($conn, $_POST['lastName']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $secret_question = mysqli_real_escape_string($conn, $_POST['secret_question']);
    $secret_answer = mysqli_real_escape_string($conn, $_POST['secret_answer']);

    // REGEX TO VALIDATE PASSWORD AND SECRET ANSWER
    $minLength = 8;
    $hasSpecialChar = preg_match('/[!@#$%^&*(),.?":{}|<>_]/', $password);
    $hasUpperCase = preg_match('/[A-Z]/', $password);
    $hasDigit = preg_match('/\d/', $password);
    // $group_id = $_SESSION['group_id'];
    if(!empty($firstname) && !empty($lastname) && !empty($email) && !empty($phone) && !empty($password) && !empty($secret_question) && !empty($secret_answer)){
        // Apply password validation to password fields
        if(((strlen($password)) < $minLength) || (!$hasSpecialChar) || (!$hasUpperCase) || (!$hasDigit)){
            echo "<script>alert(' Please Input a Valid Password.'); window.location.href='../admin_onboarding.php';</script>";
        }
        if(filter_var($email, FILTER_VALIDATE_EMAIL)){
            $sql_email = mysqli_query($conn, "SELECT * FROM admin_tbl WHERE email = '{$email}'");
            $sql_phone = mysqli_query($conn, "SELECT * FROM admin_tbl WHERE phone = '{$phone}'");
            // Check if email already exist in DB
            if(mysqli_num_rows($sql_email) > 0){
                echo "<script>alert(' $email already exists. Please use a different email.'); window.location.href='../admin_onboarding.php';</script>";
                exit();
            }
            else if(mysqli_num_rows($sql_phone) > 0){
                echo "<script>alert(' $phone already exists. Please use a different Phone Number.'); window.location.href='../admin_onboarding.php';</script>";
                exit();
            }
            else{
                // echo "$email and $phone - Does not exist in our Record";
                if(isset($_FILES['photo'])){
                    $img_name = $_FILES['photo']['name'];
                    $img_type = $_FILES['photo']['type'];
                    $tmp_name = $_FILES['photo']['tmp_name'];
                    
                    $img_explode = explode('.',$img_name);
                    $img_ext = end($img_explode);
    
                    $extensions = ["jpeg", "png", "jpg"];
                    if(in_array($img_ext, $extensions) === true){
                        $types = ["image/jpeg", "image/jpg", "image/png"];
                        if(in_array($img_type, $types) === true){
                            $time = time();
                            $new_img_name = $time.$img_name;
                            if(move_uploaded_file($tmp_name,"admin_photos/".$new_img_name)){
                                $ran_id = rand(time(), 100000000);
                                $status = "Active now";
                                $encrypt_pass = md5($password);
                                $encrypt_secret_question = md5($secret_question);
                                $encrypt_secret_answer = md5($secret_answer);
                                $insert_query = mysqli_query($conn, "INSERT INTO admin_tbl (unique_id, firstname, lastname, email, phone, password, secret_question, secret_answer, photo)
                                VALUES ({$ran_id}, '{$firstname}','{$lastname}', '{$email}', '{$phone}', '{$encrypt_pass}', '{$encrypt_secret_question}', '{$encrypt_secret_answer}', '{$new_img_name}')");
                                if($insert_query){
                                    $select_sql2 = mysqli_query($conn, "SELECT * FROM admin_tbl WHERE email = '{$email}'");
                                    if(mysqli_num_rows($select_sql2) > 0){
                                        $result = mysqli_fetch_assoc($select_sql2);
                                        // $_SESSION['unique_id'] = $result['unique_id'];
                                        echo "<script>alert(' Congratulations! You have been Onboarded Successfully.'); window.location.href='../index.php';</script>";
                                    }else{
                                        echo "<script>alert(' This email doex not exist, Please try again'); window.location.href='../admin_onboarding.php';</script>";
                                    }
                                }else{
                                    echo "<script>alert('Something went wrong, Please try again'); window.location.href='../admin_onboarding.php';</script>";
                                }
                            }
                        }
                        else{
                            echo "<script>alert(' Please Upload an Image file with jpg - png or - jpeg.'); window.location.href='../admin_onboarding.php';</script>";
                exit();
                        }
                    }
                    else{
                        echo "<script>alert(' Please Upload an Image file with jpg - png or - jpeg.'); window.location.href='../admin_onboarding.php';</script>";
                    }
                }
            }


        }
        else{
            echo "<script>alert(' $email - is not a valid email address'); window.location.href='../admin_onboarding.php';</script>";
        }
    }
    else{
        echo "<script>alert(' Please fill all input fields'); window.location.href='../admin_onboarding.php';</script>";
    }
           
?>
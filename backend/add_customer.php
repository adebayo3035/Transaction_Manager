<?php
    // session_start();
    include_once "config.php";

    $firstname = mysqli_real_escape_string($conn, $_POST['firstName']);
    $lastname = mysqli_real_escape_string($conn, $_POST['lastName']);
    $gender = mysqli_real_escape_string($conn, $_POST['gender']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phoneNumber']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $group= mysqli_real_escape_string($conn, $_POST['group']);
    $unit = mysqli_real_escape_string($conn, $_POST['unit']);
    $date_created = date('Y-m-d H:i:s');
    $date_updated = date('Y-m-d H:i:s');
    $team = 1;
    $group_id = (int)$group;
    $unit_id = (int)$unit;

    if(!empty($firstname) && !empty($lastname) && !empty($gender) && !empty($email) && !empty($phone) && !empty($address) && !empty($group) && !empty($unit)){
        //check for email validation
        if(filter_var($email, FILTER_VALIDATE_EMAIL)){
            $sql_email = mysqli_query($conn, "SELECT * FROM customer WHERE email = '{$email}'");
            $sql_phone = mysqli_query($conn, "SELECT * FROM customer WHERE phone_number = '{$phone}'");
            // Check if email already exist in DB
            if(mysqli_num_rows($sql_email) > 0){
                echo "<script>alert(' $email already exists. Please use a different email.'); window.location.href='../add_customer.php';</script>";
                exit();
               
            }
            else if(mysqli_num_rows($sql_phone) > 0){
                echo "<script>alert(' $phone already exists. Please use a different Phone Number.'); window.location.href='../add_customer.php';</script>";
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
                            if(move_uploaded_file($tmp_name,"customer_photos/".$new_img_name)){
                                $customer_id = rand(time(), 100000000);
                                $status = "Active now";
                                
                                $insert_query = mysqli_query($conn, "INSERT INTO customer (customer_id, first_name, last_name, gender, email, phone_number, address, photo, date_created, date_updated, group_id, unit_id, team_id)
                                VALUES ({$customer_id}, '{$firstname}','{$lastname}', '{$gender}', '{$email}', '{$phone}', '{$address}', '{$new_img_name}', '{$date_created}', '{$date_updated}', {$group_id}, {$unit_id}, '{$team}')");
                                if($insert_query){
                                    $select_sql2 = mysqli_query($conn, "SELECT * FROM customer WHERE email = '{$email}'");
                                    if(mysqli_num_rows($select_sql2) > 0){
                                        $result = mysqli_fetch_assoc($select_sql2);
                                        // $_SESSION['unique_id'] = $result['unique_id'];
                                        echo "<script>alert(' Congratulations! You have been Successfully Onboarded.'); window.location.href='../index.php';</script>";
                                    }else{
                                        echo "<script>alert(' This email doex not exist, Please try again'); window.location.href='../add_customer.php';</script>";
                                    }
                                }else{
                                    // echo "<script>alert('Something went wrong, Please try again'); window.location.href='../add_customer.php';</script>";
                                    echo "Error: " . mysqli_error($conn);
                                    echo "Data type of \$Group ID: " . gettype($group) . "\n";
                                }
                            }
                        }
                        else{
                            echo "<script>alert(' Please Upload an Image file with jpg - png or - jpeg.'); window.location.href='../add_customer.php';</script>";
                exit();
                        }
                    }
                    else{
                        echo "<script>alert(' Please Upload an Image file with jpg - png or - jpeg.'); window.location.href='../add_customer.php';</script>";
                    }
                }
            }


        }
        else{
            echo "<script>alert(' $email - is not a valid email address'); window.location.href='../add_customer.php';</script>";
        }
    }
    else{
        echo "<script>alert(' Please fill all input fields'); window.location.href='../add_customer.php';</script>";
    }
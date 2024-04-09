<?php
if (isset($_POST['btnUpdate'])) {
    include_once "config.php";
    session_start();
    $id = $_SESSION['unique_id'];
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $secret_answer = md5(filter_var($_POST['secret_answer'], FILTER_SANITIZE_STRING));
    if (!empty($email) && !empty($phone) && !empty($secret_answer)) {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // Prepare and execute a query to check if the email or phone number already exists
            $sql_email = mysqli_prepare($conn, "SELECT email FROM admin_tbl WHERE unique_id != ? AND email = ?");
            mysqli_stmt_bind_param($sql_email, "is", $id, $email);
            mysqli_stmt_execute($sql_email);
            $email_result = mysqli_stmt_get_result($sql_email);

            $sql_phone = mysqli_prepare($conn, "SELECT phone FROM admin_tbl WHERE unique_id != ? AND phone = ?");
            mysqli_stmt_bind_param($sql_phone, "is", $id, $phone);
            mysqli_stmt_execute($sql_phone);
            $phone_result = mysqli_stmt_get_result($sql_phone);

            // Check if email already exists in DB
            if (mysqli_num_rows($email_result) > 0) {
                echo "<script>alert('$email already exists. Please use a different email.');window.location.href='../edit_staff.php?id=" . $id . "';</script>";
                exit();
            } else if (mysqli_num_rows($phone_result) > 0) {
                echo "<script>alert('$phone already exists. Please use a different Phone Number.'); window.location.href='../edit_staff.php?id=" . $id . "'; </script>";
                exit();
            } else {
                // Perform secret answer Validation
                $secret_answer_query = mysqli_query($conn, "select secret_answer from `admin_tbl` where unique_id='$id'");
                $row = mysqli_fetch_array($secret_answer_query);
                if ($row) {
                    $decrypted_answer = ($row['secret_answer']);
                    if ($secret_answer == $decrypted_answer) {
                        //Update customer's phone number and E-mail address
                        $updateAdminQuery = "UPDATE admin_tbl SET email = '{$email}', phone = '{$phone}'  WHERE unique_id = '{$id}'";
                        $updateAdminResult = mysqli_query($conn, $updateAdminQuery);

                        if (!$updateAdminResult) {
                            //handle_error('Error updating session: ' . mysqli_error($conn));
                            echo "<script>alert('Error Updating Record')".mysqli_error($conn)." window.location.href='../edit_staff.php?id=" . $id . "'; </script>";
                        }
                        else{
                            echo "<script>alert('$email and $phone has been successfully validated.'); window.location.href='logout.php?id=" . $id . "'; </script>";
                        }
                        
                    } else {
                        echo "<script>alert('Invalid Secret Answer.'); window.location.href='../edit_staff.php?id=" . $id . "'; </script>";
                    }
                } else {
                    echo "Error: " . mysqli_error($conn);
                }
            }

        } else {
            echo "<script>alert(' $email is Invalid. Input a valid E-mail.'); </script>";
        }
    } else {
        echo "<script>alert(' All Input fields are required.'); </script>";
    }

}






//         }

//         $sql = "SELECT secret_ FROM admin_tbl WHERE unique_id = '$id'AND email='$email'";
//         $result = $conn->query($sql);
//         $row = mysqli_fetch_array($result);
//         if ($result->num_rows == 1) {
//             $decrypted_answer = md5($row['$secret_answer']);
//             if ($secret_answer == $decrypted_id) {
//                 $Update_sql = "UPDATE admin_tbl SET email = '$email', phone = '$phone'WHERE unique_id = '$id'";
//                 if (mysqli_query($conn, $Update_sql)) {
//                     echo "User Information has been Successfully Updated";
//                     echo "<script>location.replace('logout.php');</script>";
//                 } else {
//                     echo "<script>alert('An Error Occur, Please Try Again') </script>" . $sql . "<br>" . mysqli_error($conn);
//                 }
//             } else {
//                 echo "<script>alert('Invalid Validation Parameter') </script>" . $sql . "<br>" . mysqli_error($conn);
//             }


//         } else {
//             echo "<script>alert('Sorry, Email Address already been Used by another User, please Try another email!');</script>";
//         }

//     } else {
//         echo "<script>alert('Sorry, You cannot Update this Record, Please Contact Admin!');</script>";
//         echo "<script>location.replace('user_list.php');</script>";
//     }
// }
// if (isset($_POST['btnCancel'])) {
//     echo "<script>location.replace('user_list.php');</script>";
// }
//  
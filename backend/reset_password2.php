<?php
include_once "config.php";
// if (isset($_POST['btnPasswordChange'])) {
//     $email = mysqli_real_escape_string($conn, $_POST['email']);
//     $secretAnswer = (md5(filter_var($_POST['secret_answer'], FILTER_SANITIZE_STRING)));
    // Validate email and secret answer

    if (isset($_POST['btnPasswordChange'])) {
            $email = mysqli_real_escape_string($conn, $_POST['email']);
            $secretAnswer = (md5(filter_var($_POST['secret_answer'], FILTER_SANITIZE_STRING)));
            // $secretAnswer = (md5(filter_var($_POST['secret_answer'], FILTER_SANITIZE_STRING)));
            // Validate email and secret answer
        
            // echo $email . 'and'. $secretAnswer;
            if (!empty($email) && !empty($secretAnswer)) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    // Prepare and execute a query to check if the email exists
                    // Perform E-mail address Validation
                    $email_query = mysqli_query($conn, "select * from `admin_tbl` where email='$email' AND secret_answer = '$secretAnswer'");
                    $row = mysqli_fetch_array($email_query);
                    if ($row) {
                        $admin_email = ($row['email']);
                        $admin_answer = ($row['secret_answer']);
                        if (($email == $admin_email)) {
                            echo "<script>alert('Email Address is Valid.'); </script>";
                            //Update customer's Secret Question and Answer
                            $uniqueID = $row['unique_id']; // Get admin ID for password update
                            //store retrieved User ID in a session variable
                            session_start();
                            $_SESSION['customerID'] = $row['unique_id'];
                            // Set session variable to indicate user is coming from reset_password.php
                            $_SESSION['from_reset_password'] = true;
                            echo "<script>alert('Validation Successful'); </script>";
                            echo "<script>window.location.href='../password_reset.php?id=" . $_SESSION['customerID'] . "';</script>";
                        } 
                        // if (($secretAnswer) == $admin_answer){
                        //     echo "<script>alert('Secret Answer is Valid.'); </script>";
                        // }
                        // if (($secretAnswer) != ($admin_answer)){
                        //     echo "<script>alert('Secret Answer is not a Valid One.'); </script>";
                        //     echo md5($secretAnswer);
                        //     echo "        ". $admin_answer;
                        // }
                        // if ($email != $admin_email){
                        //     echo "<script>alert('Email is not a Valid One.'); </script>";
                        // }
                        else {
                            echo "<script>alert('Invalid Credentials. Error Resetting Password.'); window.location.href='../index.php'; </script>";
                        }
                    } 
                    else {
                        echo "<script>alert('Error Validating User Info')" . mysqli_error($conn) . " window.location.href='../index.php'; </script>";
                    }
                }
                else{
                    echo "<script>alert('Invalid E-mail Address. Please Input a valid E-mail')" . mysqli_error($conn) . " window.location.href='../index.php'; </script>";
                }
        
            } else {
                echo "<script>alert('All Input Fields are required')" . mysqli_error($conn) . " window.location.href='../index.php'; </script>";
            }
    

   
    
    // $sql = "SELECT * FROM admin_tbl WHERE email = ?";
    // // $sql = "SELECT * FROM admin_tbl WHERE email = ?";
    // $stmt = $conn->prepare($sql);
    // $stmt->bind_param($email);

    // $stmt->execute();
    // $result = $stmt->get_result();

    // if ($result->num_rows === 1) {
    //     // Valid email and secret answer - display password reset form
    //     $row = $result->fetch_assoc();
    //     $uniqueID = $row['unique_id']; // Get admin ID for password update
    //     //store retrieved User ID in a session variable
    //     session_start();
    //     $_SESSION['customerID'] = $row['unique_id'];
    //     // Set session variable to indicate user is coming from reset_password.php
    //     $_SESSION['from_reset_password'] = true;
    //     echo "<script>window.location.href='../password_reset.php?id=" . $_SESSION['customerID'] . "';</script>";
    // }
    // else{
    //     echo "<script>alert(' Something went wrong, Please try again')".mysqli_error($conn)."; window.location.href='../index.php';</script>";
    // }
}


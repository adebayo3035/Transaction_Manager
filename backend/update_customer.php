<?php
// session_start();
include_once "config.php";
include('restriction_checker.php');

$customer_id = mysqli_real_escape_string($conn, $_POST['customer_id']);
$firstname = mysqli_real_escape_string($conn, $_POST['firstName']);
$lastname = mysqli_real_escape_string($conn, $_POST['lastName']);
$gender = mysqli_real_escape_string($conn, $_POST['gender']);
$email = mysqli_real_escape_string($conn, $_POST['email']);
$password = mysqli_real_escape_string($conn, $_POST['password']);
$mobile_number = mysqli_real_escape_string($conn, $_POST['phoneNumber']);
$address = mysqli_real_escape_string($conn, $_POST['address']);
$group = mysqli_real_escape_string($conn, $_POST['group']);
$unit = mysqli_real_escape_string($conn, $_POST['unit']);
// $current_group = mysqli_real_escape_string($conn, $_POST['current_group']);
// $currentn_unit = mysqli_real_escape_string($conn, $_POST['current_unit']);
// REGEX TO VALIDATE PASSWORD AND SECRET ANSWER
$minLength = 8;
$hasSpecialChar = preg_match('/[!@#$%^&*(),.?":{}|<>_]/', $password);
$hasUpperCase = preg_match('/[A-Z]/', $password);
$hasDigit = preg_match('/\d/', $password);


if (!empty($firstname) && !empty($lastname) && !empty($gender) && !empty($email) && !empty($mobile_number) && !empty($address) && !empty($group) && !empty($unit)) {
    if(((strlen($password)) < $minLength) || (!$hasSpecialChar) || (!$hasUpperCase) || (!$hasDigit)){
        echo "<script>alert(' Please Input a Valid Password.'); window.location.href='../add_customer.php';</script>";
    }
    //check for email validation
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $sql_email = mysqli_query($conn, "SELECT email FROM customers WHERE email = '{$email}' && customer_id != '{$customer_id}' ");
        $sql_phone = mysqli_query($conn, "SELECT mobile_number FROM customers WHERE mobile_number = '{$mobile_number}' && customer_id != '{$customer_id}'");
        // Check if email already exist in DB
        if (mysqli_num_rows($sql_email) > 0) {
            echo "<script>alert(' $email already exists. Please use a different email.'); window.location.href='../edit_customer.php?id=" . $customer_id . "';</script>";
            exit();

        } else if (mysqli_num_rows($sql_phone) > 0) {
            echo "<script>alert(' $mobile_number already exists. Please use a different Phone Number.'); window.location.href='../edit_customer.php?id=" . $customer_id . "';</script>";
            exit();
        } 
        else{
            $encrypt_password = md5($password);
            // updateCustomer($conn, $customer_id, $firstname, $lastname, $gender, $email, $mobile_number, $address, $group, $unit);
            $updateteamQuery = "UPDATE customers SET firstname = '{$firstname}', lastname ='{$lastname}', gender = '{$gender}', email = '{$email}', password = '{$encrypt_password}', mobile_number = '{$mobile_number}', address = '{$address}', group_id = '{$group}' , unit_id = '{$unit}' WHERE customer_id = '{$customer_id}'";
            $updateteamResult = mysqli_query($conn, $updateteamQuery);

            if (!$updateteamResult) {
                // If there's an error updating the team, display an error message and redirect back to the edit page
                echo "<script>alert('Error Updating Team and Group Name: " . mysqli_error($conn) . "'); window.location.href='../edit_customer.php?id=" . $customer_id . "'; </script>";
            } else {
                // If the update is successful, display a success message and redirect to the teams page
                echo "<script>alert('Customer Information has been successfully Updated.'); window.location.href='../customer.php'; </script>";
            }
        }
    }
    else{
        echo "<script>alert('Please Input a Valid email address.'); window.location.href='../customer.php';</script>";
        exit();
    }
}
else{
    echo "<script>alert('Please Input all fields...'); window.location.href='../customer.php';</script>";
    echo mysqli_error($conn);
    exit();
}

function updateCustomer($conn, $customer_id, $firstname, $lastname, $gender, $email, $mobile_number, $address, $group , $unit)
{
    $updateteamQuery = "UPDATE customers SET first_name = '{$firstname}', lastname ='{$lastname}', gender = '{$gender}', email = '{$email}', mobile_number = '{$mobile_number}', address = '{$address}', group_id = '{$group}' , unit_id = '{$unit}' WHERE customer_id = '{$customer_id}'";
    $updateteamResult = mysqli_query($conn, $updateteamQuery);

    if (!$updateteamResult) {
        // If there's an error updating the team, display an error message and redirect back to the edit page
        echo "<script>alert('Error Updating Team and Group Name: " . mysqli_error($conn) . "'); window.location.href='../edit_customer.php?id=" . $customer_id . "'; </script>";
    } else {
        // If the update is successful, display a success message and redirect to the teams page
        echo "<script>alert('Customer Information has been successfully Updated.'); window.location.href='../customer.php'; </script>";
    }
}
<?php
header('Content-Type: application/json');
session_start();
include "config.php";

$data = json_decode(file_get_contents('php://input'), true);

$username = $conn->real_escape_string($data['username']);
$password = $conn->real_escape_string($data['password']);
$decrypt_pass = md5($password);

$sql = "SELECT * FROM customers WHERE email = '$username' OR mobile_number = '$username'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $user_pass = ($password);
    $enc_pass = $row['password'];
    if($user_pass === $enc_pass){
        $_SESSION['customer_id'] = $row['customer_id'];
        $_SESSION['customer_name'] = $row['firstname']." ". $row['lastname'];
        // $_SESSION['wallet_balance'] = $row['wallet_balance'];
        echo json_encode(["success" => true, "message" => "Login successful."]);
    } else {
        echo json_encode(["success" => false, "message" => "Invalid email or password."]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Invalid email or password."]);
}

$conn->close();

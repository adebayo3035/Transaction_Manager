<?php
header('Content-Type: application/json');
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Decode the JSON input
    $data = json_decode(file_get_contents('php://input'), true);

    // Check if JSON decoding was successful
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
        exit;
    }

    $email = $conn->real_escape_string($data['email']);
    $password = $conn->real_escape_string($data['password']);
    $encrypted_pass = md5($password);

    // Fetch customer details
    $stmt = $conn->prepare("SELECT email, password, secret_question FROM customers WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();

    // Bind the result variables
    $result = $stmt->get_result();

    // Fetch the results
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $customer_pass = $row['password'];
        if ($encrypted_pass === $customer_pass) {
            $secret_question = $row['secret_question'];
            echo json_encode([
                "success" => true,
                "secret_question" => $secret_question,
            ]);
        } else {
            echo json_encode(["success" => false, "message" => "Invalid email address or password."]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "Invalid email or password."]);
    }

    $stmt->close();
}


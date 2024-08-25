<?php
header('Content-Type: application/json');
include 'config.php'; // Make sure to include your database configuration

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Decode the JSON input
    $data = json_decode(file_get_contents('php://input'), true);

    // Check if JSON decoding was successful
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
        exit;
    }

    // Retrieve and sanitize input data
    $email = $conn->real_escape_string($data['email']);
    $secret_answer = $conn->real_escape_string($data['secret_answer']);
 // Hash secret answer
    $hashedSecretAnswer = md5($secret_answer);
 
    // Prepare and execute the SQL query
    $stmt = $conn->prepare("SELECT secret_answer FROM customers WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        // Assuming secret_answer is stored in plaintext in the database, otherwise hash/encode as needed
        if ($row['secret_answer'] !== $hashedSecretAnswer) {
            echo json_encode(['success' => false, 'message' => 'Customer Validation Failed.']);
            exit;
        }
        else {
            echo json_encode(['success' => true]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Email not found.']);
    }

    $stmt->close();
}

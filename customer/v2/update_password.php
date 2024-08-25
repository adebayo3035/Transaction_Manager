<?php
header('Content-Type: application/json');
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
        exit;
    }

    $email = $conn->real_escape_string($data['email']);
    $newPassword = $conn->real_escape_string($data['new_password']);
    $encryptedPass = md5($newPassword);

    // Update the password in the database
    $stmt = $conn->prepare("UPDATE customers SET password = ? WHERE email = ?");
    $stmt->bind_param("ss", $encryptedPass, $email);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update password']);
    }

    $stmt->close();
}

<?php
header('Content-Type: application/json');
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Decode JSON input
    $data = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
        exit;
    }

    $email = $conn->real_escape_string($data['email']);
    $new_password = $conn->real_escape_string($data['new_password']);
    $token = $conn->real_escape_string($data['token']);
    
    // Validate token
    $stmt = $conn->prepare("SELECT expires_at FROM driver_password_reset_tokens WHERE email = ? AND token = ?");
    $stmt->bind_param("ss", $email, $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        // Check if the token has expired
        if (strtotime($row['expires_at']) > time()) {
            // Token is valid, proceed with password reset
            $hashedPassword = md5($new_password); // Hash the new password
            $stmtUpdatePassword = $conn->prepare("UPDATE driver SET password = ? WHERE email = ?");
            $stmtUpdatePassword->bind_param("ss", $hashedPassword, $email);

            if ($stmtUpdatePassword->execute()) {
                echo json_encode(['success' => true, 'message' => 'Password has been reset successfully.']);

                // Delete the token after successful password reset
                $stmtDeleteToken = $conn->prepare("DELETE FROM customer_password_reset_tokens WHERE email = ?");
                $stmtDeleteToken->bind_param("s", $email);
                $stmtDeleteToken->execute();
                $stmtDeleteToken->close();
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to reset the password.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Token has expired.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid token.']);
    }

    $stmt->close();
}


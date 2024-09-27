<?php
// Include database connection
include_once "config.php";

// Set content type to JSON
header('Content-Type: application/json');

// Get the POST data
$data = json_decode(file_get_contents("php://input"), true);


// Check if all required fields are provided
if (isset($data['email'], $data['password'], $data['secret_answer'], $data['confirmPassword'])) {
    $email = mysqli_real_escape_string($conn, $data['email']);
    $password = mysqli_real_escape_string($conn, $data['password']);
    $confirmPassword = mysqli_real_escape_string($conn, $data['confirmPassword']);
    $secret_answer = mysqli_real_escape_string($conn, $data['secret_answer']);

    // REGEX TO VALIDATE PASSWORD AND SECRET ANSWER
    $minLength = 8;
    $hasSpecialChar = preg_match('/[!@#$%^&*(),.?":{}|<>_]/', $password);
    $hasUpperCase = preg_match('/[A-Z]/', $password);
    $hasDigit = preg_match('/\d/', $password);

    // Validate password strength
    if (strlen($password) < $minLength || !$hasSpecialChar || !$hasUpperCase || !$hasDigit) {
        echo json_encode(['success' => false, 'message' => 'Please input a valid password.']);
        exit();
    }

    // Step 1: Validate email
    $emailQuery = $conn->prepare("SELECT secret_answer, password FROM admin_tbl WHERE email = ?");
    $emailQuery->bind_param("s", $email);
    $emailQuery->execute();
    $emailQuery->store_result();

    if ($emailQuery->num_rows > 0) {
        // Email exists in the database
        $emailQuery->bind_result($db_secret_answer, $db_password);
        $emailQuery->fetch();

        // Step 2: Validate the secret answer (compare with md5 hash)
        if (md5($secret_answer) === $db_secret_answer) {

            // Step 3: Check if password and confirmPassword match
            if ($password === $confirmPassword) {

                // Step 4: Hash the new password (using password_hash)
                $hashedPassword = md5($password);

                // Step 5: Update the password in the database
                $updateQuery = $conn->prepare("UPDATE admin_tbl SET password = ? WHERE email = ?");
                $updateQuery->bind_param("ss", $hashedPassword, $email);

                if ($updateQuery->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Password reset successfully.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update the password.']);
                }

                $updateQuery->close();
            } else {
                // Password and Confirm Password do not match
                echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
                exit();
            }
        } else {
            // Secret answer is incorrect
            echo json_encode(['success' => false, 'message' => 'Secret answer is incorrect.']);
            exit();
        }
    } else {
        // Email not found
        echo json_encode(['success' => false, 'message' => 'Email does not exist.']);
        exit();
    }

    $emailQuery->close();
} else {
    // Missing required fields
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit();
}

// Close the database connection
$conn->close();


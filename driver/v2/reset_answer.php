<?php
header('Content-Type: application/json'); // Ensure the response is JSON formatted

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    include_once "config.php";
    session_start();
    $id = $_SESSION['driver_id'];

    // Decode JSON data from the frontend
    $data = json_decode(file_get_contents("php://input"), true);

    $email = filter_var($data['email'], FILTER_VALIDATE_EMAIL); // Validate email
    $password = $data['password'];
    $question = filter_var($data['secret_question'], FILTER_SANITIZE_STRING);
    $secret_answer = filter_var($data['secret_answer'], FILTER_SANITIZE_STRING);
    $confirm_answer = filter_var($data['confirm_answer'], FILTER_SANITIZE_STRING);

    // Check if required fields are not empty
    if (!empty($email) && !empty($password) && !empty($question) && !empty($secret_answer) && !empty($confirm_answer)) {
        // Validate email format
        if ($email) {
            // Prepare statement to check if the email exists for the logged-in user
            $email_query = $conn->prepare("SELECT email, password FROM `driver` WHERE id = ?");
            $email_query->bind_param('i', $id);
            $email_query->execute();
            $result = $email_query->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $driver_email = $row['email'];
                $driver_password = $row['password'];
                $verifyPassword = password_verify($password, $driver_password);
                // Verify if the password is correct and secret answers are the same
                if ($email === $driver_email && ($verifyPassword) && $secret_answer === $confirm_answer) {
                    // Encrypt the secret answer
                    $encrypted_answer = md5($secret_answer);

                    // Prepare the statement to update the secret question and answer
                    $updateAdminQuery = $conn->prepare("UPDATE driver SET secret_question = ?, secret_answer = ? WHERE id = ?");
                    $updateAdminQuery->bind_param('ssi', $question, $encrypted_answer, $id);
                    $updateAdminResult = $updateAdminQuery->execute();

                    // Check if the update was successful
                    if ($updateAdminResult) {
                        echo json_encode(['success' => true, 'message' => 'Secret Question and Answer updated successfully.']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Error updating Secret Question and Answer: ' . $conn->error]);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid credentials or secret answers do not match.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Email not found in the system.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'All input fields are required.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}

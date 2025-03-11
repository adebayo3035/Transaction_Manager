<?php
header('Content-Type: application/json'); // Ensure the response is JSON formatted

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    session_start();
    include_once "config.php";

    $id = $_SESSION['driver_id'];
    checkDriverSession($id);
    logActivity("Session validated successfully for Driver ID: $id.");
    
    logActivity("Driver ID: $id attempted to update secret question and answer.");

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
            if ($email_query === false) {
                logActivity("Failed to prepare the SQL statement to fetch driver email and password.");
                echo json_encode(['success' => false, 'message' => 'Database error']);
                exit();
            }

            $email_query->bind_param('i', $id);
            if (!$email_query->execute()) {
                logActivity("Failed to execute the SQL statement to fetch driver email and password.");
                echo json_encode(['success' => false, 'message' => 'Database error']);
                exit();
            }

            $result = $email_query->get_result();
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $driver_email = $row['email'];
                $driver_password = $row['password'];
                $verifyPassword = password_verify($password, $driver_password);

                // Verify if the password is correct and secret answers are the same
                if ($email === $driver_email && $verifyPassword && $secret_answer === $confirm_answer) {
                    // Encrypt the secret answer
                    $encrypted_answer = md5($secret_answer);

                    // Prepare the statement to update the secret question and answer
                    $updateAdminQuery = $conn->prepare("UPDATE driver SET secret_question = ?, secret_answer = ? WHERE id = ?");
                    if ($updateAdminQuery === false) {
                        logActivity("Failed to prepare the SQL statement to update secret question and answer.");
                        echo json_encode(['success' => false, 'message' => 'Database error']);
                        exit();
                    }

                    $updateAdminQuery->bind_param('ssi', $question, $encrypted_answer, $id);
                    $updateAdminResult = $updateAdminQuery->execute();

                    // Check if the update was successful
                    if ($updateAdminResult) {
                        logActivity("Secret question and answer updated successfully for driver ID: $id.");
                        echo json_encode(['success' => true, 'message' => 'Secret Question and Answer updated successfully.']);
                    } else {
                        logActivity("Failed to update secret question and answer for driver ID: $id. Error: " . $conn->error);
                        echo json_encode(['success' => false, 'message' => 'Error updating Secret Question and Answer: ' . $conn->error]);
                    }
                } else {
                    logActivity("Invalid credentials or secret answers do not match for driver ID: $id.");
                    echo json_encode(['success' => false, 'message' => 'Invalid credentials or secret answers do not match.']);
                }
            } else {
                logActivity("Email not found in the system for driver ID: $id.");
                echo json_encode(['success' => false, 'message' => 'Email not found in the system.']);
            }
        } else {
            logActivity("Invalid email format provided by driver ID: $id.");
            echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
        }
    } else {
        logActivity("Missing required fields in the request from driver ID: $id.");
        echo json_encode(['success' => false, 'message' => 'All input fields are required.']);
    }
} else {
    logActivity("Invalid request method received.");
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
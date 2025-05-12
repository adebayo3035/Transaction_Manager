<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    include_once "config.php";
    session_start();

    if (!isset($_SESSION['unique_id'])) {
        logActivity("Unauthorized access attempt. No session ID.");
        echo json_encode(["success" => false, "message" => "User not authenticated."]);
        exit;
    }

    $id = $_SESSION['unique_id'];
    logActivity("User with ID $id initiated update for secret question and answer.");

    $data = json_decode(file_get_contents("php://input"), true);

    $email = filter_var($data['email'], FILTER_VALIDATE_EMAIL);
    $password = $data['password'];
    $question = filter_var($data['secret_question'], FILTER_SANITIZE_STRING);
    $secret_answer = filter_var($data['secret_answer'], FILTER_SANITIZE_STRING);
    $confirm_answer = filter_var($data['confirm_answer'], FILTER_SANITIZE_STRING);

    if (!empty($email) && !empty($password) && !empty($question) && !empty($secret_answer) && !empty($confirm_answer)) {
        if ($email) {
            $email_query = $conn->prepare("SELECT email, password FROM `admin_tbl` WHERE unique_id = ?");
            $email_query->bind_param('i', $id);
            $email_query->execute();
            $result = $email_query->get_result();

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $admin_email = $row['email'];
                $hashed_password = $row['password'];

                if ($email === $admin_email && (md5($password) === $hashed_password) && $secret_answer === $confirm_answer) {
                    $encrypted_answer = md5($secret_answer);

                    $updateAdminQuery = $conn->prepare("UPDATE admin_tbl SET secret_question = ?, secret_answer = ? WHERE unique_id = ?");
                    $updateAdminQuery->bind_param('ssi', $question, $encrypted_answer, $id);
                    $updateAdminResult = $updateAdminQuery->execute();

                    if ($updateAdminResult) {
                        logActivity("Secret question and answer updated successfully for user ID $id.");
                        echo json_encode(['success' => true, 'message' => 'Secret Question and Answer updated successfully.']);
                    } else {
                        logActivity("Failed to update secret question/answer for user ID $id: " . $conn->error);
                        echo json_encode(['success' => false, 'message' => 'Error updating Secret Question and Answer: ' . $conn->error]);
                    }
                } else {
                    logActivity("Credential mismatch or secret answer mismatch for user ID $id.");
                    echo json_encode(['success' => false, 'message' => 'Invalid credentials or secret answers do not match.']);
                }
            } else {
                logActivity("Email not found for user ID $id.");
                echo json_encode(['success' => false, 'message' => 'Email not found in the system.']);
            }
        } else {
            logActivity("Invalid email format provided by user ID $id.");
            echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
        }
    } else {
        logActivity("Missing fields in request from user ID $id.");
        echo json_encode(['success' => false, 'message' => 'All input fields are required.']);
    }
} else {
    logActivity("Invalid request method attempted.");
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}

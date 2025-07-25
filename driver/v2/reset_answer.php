<?php
header('Content-Type: application/json'); // Ensure the response is JSON formatted

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    session_start();
    include_once "config.php";

    $id = $_SESSION['driver_id'] ?? null;

    if (!$id) {
        logActivity("Driver session not found.");
        echo json_encode(['success' => false, 'message' => 'Session not found.']);
        exit();
    }

    logActivity("Driver session found. Validating session for Driver ID: $id.");
    checkDriverSession($id);
    logActivity("Session validated successfully for Driver ID: $id.");

    // Decode JSON data from the frontend
    $data = json_decode(file_get_contents("php://input"), true);
    logActivity("Incoming request payload: " . json_encode([
        'email' => $data['email'] ?? 'null',
        'secret_question' => $data['secret_question'] ?? 'null',
        'confirm_answer' => isset($data['confirm_answer']) ? '[REDACTED]' : 'null'
    ]));

    $email = filter_var($data['email'], FILTER_VALIDATE_EMAIL);
    $password = $data['password'] ?? '';
    $question = filter_var($data['secret_question'], FILTER_SANITIZE_STRING);
    $secret_answer = filter_var($data['secret_answer'], FILTER_SANITIZE_STRING);
    $confirm_answer = filter_var($data['confirm_answer'], FILTER_SANITIZE_STRING);

    if (!empty($email) && !empty($password) && !empty($question) && !empty($secret_answer) && !empty($confirm_answer)) {
        logActivity("All required fields are present. Proceeding with validation.");

        if ($email) {
            logActivity("Email format is valid: $email");

            $email_query = $conn->prepare("SELECT email, password FROM `driver` WHERE id = ?");
            if ($email_query === false) {
                logActivity("SQL preparation failed when checking email/password. Error: " . $conn->error);
                echo json_encode(['success' => false, 'message' => 'Database error']);
                exit();
            }

            $email_query->bind_param('i', $id);
            if (!$email_query->execute()) {
                logActivity("Execution failed for email/password check. Error: " . $email_query->error);
                echo json_encode(['success' => false, 'message' => 'Database error']);
                exit();
            }

            $result = $email_query->get_result();
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $driver_email = $row['email'];
                $driver_password = $row['password'];

                logActivity("Driver email retrieved from DB: $driver_email");

                $verifyPassword = password_verify($password, $driver_password);
                logActivity("Password verification result: " . ($verifyPassword ? "success" : "failed"));

                if ($email === $driver_email && $verifyPassword && $secret_answer === $confirm_answer) {
                    logActivity("Email, password, and secret answer match. Proceeding to update.");

                    $encrypted_answer = password_hash($secret_answer, PASSWORD_DEFAULT);

                    $updateAdminQuery = $conn->prepare("UPDATE driver SET secret_question = ?, secret_answer = ? WHERE id = ?");
                    if ($updateAdminQuery === false) {
                        logActivity("Failed to prepare update query. Error: " . $conn->error);
                        echo json_encode(['success' => false, 'message' => 'Database error']);
                        exit();
                    }

                    $updateAdminQuery->bind_param('ssi', $question, $encrypted_answer, $id);
                    $updateAdminResult = $updateAdminQuery->execute();

                    if ($updateAdminResult) {
                        logActivity("Secret question and answer updated successfully for driver ID: $id.");
                        echo json_encode(['success' => true, 'message' => 'Secret Question and Answer updated successfully.']);
                    } else {
                        logActivity("Update failed for driver ID: $id. Error: " . $updateAdminQuery->error);
                        echo json_encode(['success' => false, 'message' => 'Error updating Secret Question and Answer.']);
                    }

                } else {
                    logActivity("Validation failed. Match results - Email: " . ($email === $driver_email ? "yes" : "no") . ", Password: " . ($verifyPassword ? "yes" : "no") . ", Answer match: " . ($secret_answer === $confirm_answer ? "yes" : "no"));
                    echo json_encode(['success' => false, 'message' => 'Invalid credentials or secret answers do not match.']);
                }

            } else {
                logActivity("No record found for driver ID: $id.");
                echo json_encode(['success' => false, 'message' => 'Email not found in the system.']);
            }
        } else {
            logActivity("Invalid email format received: " . $data['email']);
            echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
        }
    } else {
        logActivity("One or more fields are missing in the request.");
        echo json_encode(['success' => false, 'message' => 'All input fields are required.']);
    }
} else {
    logActivity("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}

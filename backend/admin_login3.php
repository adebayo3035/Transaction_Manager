<?php
function destroySession($uniqueId, $conn)
{
    // Fetch the session_id from admin_active_sessions table
    $sessionId = null;
    $stmt = $conn->prepare("SELECT session_id FROM admin_active_sessions WHERE unique_id = ?");
    $stmt->bind_param("i", $uniqueId);
    $stmt->execute();
    $stmt->bind_result($sessionId);
    if ($stmt->fetch()) {
        // Destroy the session
        session_id($sessionId);
        session_start();
        session_destroy();

        // Remove the session record from the table
        $stmt->close();
        $stmt = $conn->prepare("DELETE FROM admin_active_sessions WHERE unique_id = ?");
        $stmt->bind_param("i", $uniqueId);
        $stmt->execute();
    }
    $stmt->close();
}

header('Content-Type: application/json');
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $encrypted_password = md5($password);

    // Fetch admin details
    $stmt = $conn->prepare("SELECT * FROM admin_tbl WHERE email = ? OR phone = ?");
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $admin_password = $row['password'];
            $restriction_id = $row['restriction_id'];
            $block_id = $row['block_id'];

            // Check if there's a block_id on the staff account
            if ($block_id == 0) {
                if ($encrypted_password === $admin_password) {
                    $admin_id = $row['unique_id'];

                    // Call destroySession function
                    destroySession($admin_id, $conn);

                    // Start a new session
                    session_start();
                    session_regenerate_id(true);
                    $_SESSION['unique_id'] = $row['unique_id'];
                    $_SESSION['firstname'] = $row['firstname'];
                    $_SESSION['lastname'] = $row['lastname'];
                    $_SESSION['role'] = $row['role'];
                    $_SESSION['restriction_id'] = $row['restriction_id'];
                    $_SESSION['secret_answer'] = md5($row['secret_answer']);

                    // Insert new session into admin_active_sessions table
                    $newSessionId = session_id();
                    $loginTime = date('Y-m-d H:i:s');
                    $stmt = $conn->prepare("INSERT INTO admin_active_sessions (unique_id, session_id, login_time, status) VALUES (?, ?, ?, 'Active')");
                    $stmt->bind_param("iss", $admin_id, $newSessionId, $loginTime);
                    $stmt->execute();
                    echo json_encode(["success" => true, "message" => "success"]);
                } else {
                    echo json_encode(["success" => false, "message" => "Email or Password is Incorrect!"]);
                }
            } else {
                echo json_encode(["success" => false, "message" => "Account is blocked. Kindly Contact Super Admin."]);
            }
        }
    } else {
        echo json_encode(["success" => false, "message" => "$username - This Email or Phone Number does not Exist!"]);
    }
    $stmt->close();
    $conn->close();
}


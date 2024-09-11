<?php
function destroySession($driverId, $conn)
{
    // Fetch the session_id from customer_active_sessions table
    $sessionId = null;
    $stmt = $conn->prepare("SELECT session_id FROM driver_active_sessions WHERE driver_id = ?");
    $stmt->bind_param("i", $driverId);
    $stmt->execute();
    $stmt->bind_result($sessionId);
    if ($stmt->fetch()) {
        // Destroy the session
        session_id($sessionId);
        session_start();
        session_destroy();

        // Remove the session record from the table
        $stmt->close();
        $stmt = $conn->prepare("DELETE FROM driver_active_sessions WHERE driver_id = ?");
        $stmt->bind_param("i", $driverId);
        $stmt->execute();
    }
    $stmt->close();
}

header('Content-Type: application/json');
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $username = $conn->real_escape_string($data['username']);
    $password = $conn->real_escape_string($data['password']);
    // $encrypted_pass = md5($password);
    // Fetch customer details
    $stmt = $conn->prepare("SELECT * FROM driver WHERE email = ? OR phone_number = ?");
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    // $stmt->store_result();

    // Bind the result variables
    $result = $stmt->get_result();

    // Fetch the results
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            if($row['restriction'] !== 0){
                echo json_encode(["success" => false, "message" => "Your account has been restricted. Contact your Admin."]);
                exit();
                
                
            }
            $customer_pass = $row['password'];
            $verifyPassword = password_verify($password, $customer_pass);
            if ($verifyPassword) {
                $driver_id = $row['id'];
                // Call destroySession function
                destroySession($driver_id, $conn);

                // Start a new session
                session_start();
                session_regenerate_id(true);
                $_SESSION['driver_id'] = $driver_id;
                $_SESSION['driver_name'] = $row['firstname']." ". $row['lastname'];

                // Insert new session into customer_active_sessions table
                $newSessionId = session_id();
                $loginTime = date('Y-m-d H:i:s');
                $stmt = $conn->prepare("INSERT INTO driver_active_sessions (driver_id, session_id, login_time, status) VALUES (?, ?, ?, 'Active')");
                $stmt->bind_param("iss", $driver_id, $newSessionId, $loginTime);
                $stmt->execute();
                echo json_encode(["success" => true, "message" => "Login successful."]);
            } else {
                echo json_encode(["success" => false, "message" => "Invalid email or password."]);
                exit();
            }
        }
    } else {
        echo json_encode(["success" => false, "message" => "Invalid email or password."]);
        $stmt->close();
    }
}
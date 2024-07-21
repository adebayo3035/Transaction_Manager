<?php
function destroySession($customerId, $conn)
{
    // Fetch the session_id from customer_active_sessions table
    $sessionId = null;
    $stmt = $conn->prepare("SELECT session_id FROM customer_active_sessions WHERE customer_id = ?");
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $stmt->bind_result($sessionId);
    if ($stmt->fetch()) {
        // Destroy the session
        session_id($sessionId);
        session_start();
        session_destroy();

        // Remove the session record from the table
        $stmt->close();
        $stmt = $conn->prepare("DELETE FROM customer_active_sessions WHERE customer_id = ?");
        $stmt->bind_param("i", $customerId);
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
    $encrypted_pass = md5($password);
    // Fetch customer details
    $stmt = $conn->prepare("SELECT * FROM customers WHERE email = ? OR mobile_number = ?");
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    // $stmt->store_result();

    // Bind the result variables
    $result = $stmt->get_result();

    // Fetch the results
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $customer_pass = $row['password'];
            if ($encrypted_pass === $customer_pass) {
                $customer_id = $row['customer_id'];
                // $_SESSION['customer_name'] = $row['firstname']." ". $row['lastname'];
                // $_SESSION['wallet_balance'] = $row['wallet_balance'];
                // Call destroySession function
                destroySession($customer_id, $conn);

                // Start a new session
                session_start();
                session_regenerate_id(true);
                $_SESSION['customer_id'] = $customer_id;
                $_SESSION['customer_name'] = $row['firstname']." ". $row['lastname'];

                // Insert new session into customer_active_sessions table
                $newSessionId = session_id();
                $loginTime = date('Y-m-d H:i:s');
                $stmt = $conn->prepare("INSERT INTO customer_active_sessions (customer_id, session_id, login_time, status) VALUES (?, ?, ?, 'Active')");
                $stmt->bind_param("iss", $customer_id, $newSessionId, $loginTime);
                $stmt->execute();
                echo json_encode(["success" => true, "message" => "Login successful."]);
            } else {
                echo json_encode(["success" => false, "message" => "Invalid email or password."]);
            }
        }
    } else {
        echo json_encode(["success" => false, "message" => "Invalid email or password."]);
        $stmt->close();
    }
}
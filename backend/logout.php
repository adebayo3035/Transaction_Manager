<?php
session_start();

// Set the content type to application/json
header('Content-Type: application/json');

if (isset($_SESSION['unique_id'])) {
    include_once "config.php";

    // Check if the request method is POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Get the raw POST data
        $data = json_decode(file_get_contents('php://input'), true);

        // Use POST to get logout_id
        if (isset($data['logout_id'])) {
            $logout_id = mysqli_real_escape_string($conn, $data['logout_id']);

            // Step 1: Retrieve the session_id using $logout_id
            $stmt = $conn->prepare("SELECT session_id FROM admin_active_sessions WHERE unique_id = ?");
            $stmt->bind_param("s", $logout_id);
            $stmt->execute();
            $stmt->bind_result($session_id);
            $stmt->fetch();
            $stmt->close();

            if ($session_id) {
                // Step 2: Update the status to 'Inactive'
                $stmt = $conn->prepare("UPDATE admin_active_sessions SET status = 'Inactive' WHERE session_id = ?");
                $stmt->bind_param("s", $session_id);
                $stmt->execute();
                $stmt->close();
                
                // Destroy the session
                session_unset();
                session_destroy();

                // Return a success response
                echo json_encode(['success' => true, "message" => "You have been successfully logged out"]);
                exit();
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to retrieve session ID for the provided logout ID.']);
                exit();
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Logout ID missing.']);
            exit();
        }
    } else {
        // Handle invalid request method
        echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
        exit();
    }
} else {
    // User is not logged in
    echo json_encode(['success' => false, 'error' => 'User not logged in.']);
    exit();
}

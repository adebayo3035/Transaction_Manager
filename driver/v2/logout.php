<?php
session_start();

if (isset($_SESSION['driver_id'])) {
    include_once "config.php";
    $logout_id = $_SESSION['driver_id'];
    checkDriverSession($logout_id);
    logActivity("Session validated successfully for Driver ID: $logout_id.");

    // Sanitize the logout_id received from the URL
    $logout_id = $_SESSION['driver_id'];

    // Log logout attempt
    logActivity("Logout attempt for driver_id: " . $logout_id);

    // Check if the logout_id matches the current session's driver_id
    if ($logout_id) {
        // Step 1: Retrieve the session_id using $logout_id
        $stmt = $conn->prepare("SELECT session_id FROM driver_active_sessions WHERE driver_id = ?");
        if ($stmt === false) {
            logActivity("Failed to prepare the SQL statement to retrieve session_id.");
            echo "Database error.";
            exit();
        }

        $stmt->bind_param("i", $logout_id);
        if (!$stmt->execute()) {
            logActivity("Failed to execute the SQL statement to retrieve session_id.");
            echo "Database error.";
            exit();
        }

        $stmt->bind_result($session_id);
        $stmt->fetch();
        $stmt->close();

        // Update the status column to Inactive
        if ($session_id) {
            logActivity("Retrieved session_id: $session_id for driver_id: $logout_id.");

            // Step 2: Update the status to 'Inactive'
            $stmt = $conn->prepare("UPDATE driver_active_sessions SET status = 'Inactive' WHERE session_id = ?");
            if ($stmt === false) {
                logActivity("Failed to prepare the SQL statement to update session status.");
                echo "Database error.";
                exit();
            }

            $stmt->bind_param("s", $session_id);
            if (!$stmt->execute()) {
                logActivity("Failed to execute the SQL statement to update session status.");
                echo "Database error.";
                exit();
            }
            $stmt->close();

            logActivity("Updated session status to 'Inactive' for session_id: $session_id.");

            // Destroy the session for this specific user
            session_unset();
            session_destroy();

            logActivity("Session destroyed for driver_id: $logout_id.");

            // Redirect to login page
            header("Location: ../v1/index.php");
            exit();
        } else {
            logActivity("Failed to retrieve session ID for driver_id: $logout_id.");
            echo "Failed to retrieve session ID for the provided logout ID.";
            exit();
        }
    } 
} else {
    // If no session or no logout_id provided, redirect to login page
    logActivity("Unauthorized logout attempt: No session or logout_id provided.");
    header("Location: ../v1/index.php");
    exit();
}
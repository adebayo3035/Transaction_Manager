<?php
session_start();
if (isset($_SESSION['driver_id'])){
    include_once "config.php";

    // Sanitize the logout_id received from the URL
    $logout_id = $_SESSION['driver_id'];

    // Check if the logout_id matches the current session's driver_id
    if ($logout_id) {
        // Step 1: Retrieve the session_id using $logout_id
        $stmt = $conn->prepare("SELECT session_id FROM driver_active_sessions WHERE driver_id = ?");
        $stmt->bind_param("i", $logout_id);
        $stmt->execute();
        $stmt->bind_result($session_id);
        $stmt->fetch();
        $stmt->close();

        // Update the status column to Inactive
        if ($session_id) {
            // Step 2: Update the status to 'Inactive'
            $stmt = $conn->prepare("UPDATE driver_active_sessions SET status = 'Inactive' WHERE session_id = ?");
            $stmt->bind_param("s", $session_id);
            $stmt->execute();
            $stmt->close();

            // Destroy the session for this specific user
            session_unset();
            session_destroy();

            // Redirect to login page
            header("Location: ../v1/index.php");
            exit();
        } else {
            echo "Failed to retrieve session ID for the provided logout ID.";
            exit();
        }
    } 
    // else {
    //     // If the logout_id does not match, redirect to homepage
    //     header("Location: ../v1/dashboard.php");
    //     exit();
    // }
} else {
    // If no session or no logout_id provided, redirect to login page
    header("Location: ../v1/index.php");
    exit();
}


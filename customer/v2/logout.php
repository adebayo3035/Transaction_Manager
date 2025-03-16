<?php
include_once "config.php";
session_start();

// Log the start of the logout process
if (isset($_SESSION['customer_id'])) {
    $logout_id = $_SESSION['customer_id'];
    logActivity("Logout process started for Customer ID: " . $logout_id);
    checkSession($logout_id);

    // Log the session ID retrieval attempt
    logActivity("Attempting to retrieve session_id for Customer ID: " . $logout_id);

    // Step 1: Retrieve the session_id using $logout_id
    $stmt = $conn->prepare("SELECT session_id FROM customer_active_sessions WHERE customer_id = ?");
    $stmt->bind_param("s", $logout_id);
    $stmt->execute();
    $stmt->bind_result($session_id);
    $stmt->fetch();
    $stmt->close();

    // Log the result of session ID retrieval
    if ($session_id) {
        logActivity("Session ID retrieved successfully for Customer ID: " . $logout_id . " with session_id: " . $session_id);
        
        // Step 2: Update the status to 'Inactive' and destroy session
        $stmt = $conn->prepare("UPDATE customer_active_sessions SET status = 'Inactive' WHERE session_id = ?");
        $stmt->bind_param("s", $session_id);
        $stmt->execute();
        $stmt->close();

        // Log the session status update
        logActivity("Session status updated to 'Inactive' for session_id: " . $session_id);

        // Destroy the session
        session_unset();
        session_destroy();

        // Log session destruction
        logActivity("Session destroyed for Customer ID: " . $logout_id);

        // Redirect to login page
        header("Location: ../v1/index.php");
        exit();
    } else {
        // Log the failure to retrieve session ID
        logActivity("Failed to retrieve session ID for Customer ID: " . $logout_id);
        echo "Failed to retrieve session ID for the provided logout ID.";
    }
} else {
    // Log when session does not exist
    logActivity("No active session found for Customer ID: " . $_SESSION['customer_id']);
    
    // If session does not exist, redirect to login page
    header("Location: ../v1/index.php");
    exit();
}

// Log the end of the logout process
logActivity("Logout process completed for Customer ID: " . $_SESSION['customer_id']);

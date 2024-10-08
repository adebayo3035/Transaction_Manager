<?php
session_start();

if (isset($_SESSION['customer_id'])) {
    include_once "config.php";
    $logout_id = $_SESSION['customer_id'];

    // Step 1: Retrieve the session_id using $logout_id
    $stmt = $conn->prepare("SELECT session_id FROM customer_active_sessions WHERE customer_id = ?");
    $stmt->bind_param("s", $logout_id);
    $stmt->execute();
    $stmt->bind_result($session_id);
    $stmt->fetch();
    $stmt->close();

    // Step 2: Update the status to 'Inactive' and destroy session
    if ($session_id) {
        $stmt = $conn->prepare("UPDATE customer_active_sessions SET status = 'Inactive' WHERE session_id = ?");
        $stmt->bind_param("s", $session_id);
        $stmt->execute();
        $stmt->close();

        // Destroy the session
        session_unset();
        session_destroy();

        // Redirect to login page
        header("Location: ../v1/index.php");
        exit();
    } else {
        echo "Failed to retrieve session ID for the provided logout ID.";
    }
} else {
    // If session does not exist, redirect to login page
    header("Location: ../v1/index.php");
    exit();
}


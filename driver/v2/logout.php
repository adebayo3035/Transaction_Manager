<?php
session_start();
if (isset($_SESSION['driver_id'])) {
    include_once "config.php";
    // $logout_id = mysqli_real_escape_string($conn, $_GET['logout_id']);
    $logout_id = $_SESSION['driver_id'];
    if (isset($logout_id)) {
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
            session_start();
            session_unset();
            session_destroy();
            header("location: ../v1/index.php");
        }
        else{
            echo "Failed to retrieve session ID for the provided logout ID.";
        }
    } else {
        header("location: ../v1/homepage.php");
    }
} else {
    header("location: ../v1/index.php");
}

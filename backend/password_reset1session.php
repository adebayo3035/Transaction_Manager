<?php
session_start();
include_once "config.php";

// Function to handle errors
function handle_error($error_message) {
    // Output error message and exit
    echo $error_message;
    exit;
}

// Validate POST data
if (empty($_POST['username']) || empty($_POST['password'])) {
    handle_error('Please input required fields');
}

// Sanitize and escape POST data
$email = mysqli_real_escape_string($conn, $_POST['username']);
$password = mysqli_real_escape_string($conn, $_POST['password']);

// Define session status constants
$active_session = 1;
$inactive_session = 0;

// Retrieve user information from database
$getUserInfoQuery = "SELECT * FROM admin_tbl WHERE email = '{$email}' AND password = md5('{$password}')";
$getUserInfoResult = mysqli_query($conn, $getUserInfoQuery);

if (!$getUserInfoResult) {
    handle_error('Error executing query: ' . mysqli_error($conn));
}

if (mysqli_num_rows($getUserInfoResult) > 0) {
    $row = mysqli_fetch_assoc($getUserInfoResult);
    $user_id = $row['unique_id'];

    // Check if there's an ongoing session for this user
    $checkSessionQuery = "SELECT * FROM sessions WHERE user_id = '{$user_id}' AND session_status = '{$active_session}'";
    $checkSessionResult = mysqli_query($conn, $checkSessionQuery);

    if (!$checkSessionResult) {
        handle_error('Error executing query: ' . mysqli_error($conn));
    }

    if (mysqli_num_rows($checkSessionResult) > 0) {
        // Update existing session
        $updateSessionQuery = "UPDATE sessions SET session_status = '{$inactive_session}' WHERE user_id = '{$user_id}'";
        $updateSessionResult = mysqli_query($conn, $updateSessionQuery);

        if (!$updateSessionResult) {
            handle_error('Error updating session: ' . mysqli_error($conn));
        }

        // Destroy session
        session_destroy();
        session_unset();
        echo "<script>window.location.href='../index.php';</script>";
    } else {
        // Start new session
        $_SESSION['unique_id'] = $row['unique_id'];
        $_SESSION['firstname'] = $row['firstname'];
        $_SESSION['lastname'] = $row['lastname'];
        $_SESSION['role'] = $row['role'];
        $_SESSION['secret_answer'] = md5($row['secret_answer']);

        // Update session status or insert new session record
        $currentDateTime = date("Y-m-d H:i:s");
        $checkSessionQuery2 = "SELECT * FROM sessions WHERE user_id = '{$user_id}'";
        $checkSessionResult2 = mysqli_query($conn, $checkSessionQuery2);

        if (!$checkSessionResult2) {
            handle_error('Error executing query: ' . mysqli_error($conn));
        }

        if (mysqli_num_rows($checkSessionResult2) > 0) {
            // Update session status
            $updateSessionQuery2 = "UPDATE sessions SET session_status = '{$active_session}' WHERE user_id = '{$user_id}'";
            $updateSessionResult2 = mysqli_query($conn, $updateSessionQuery2);

            if (!$updateSessionResult2) {
                handle_error('Error updating session: ' . mysqli_error($conn));
            }
        } else {
            // Insert new session record
            $insertSessionQuery = "INSERT INTO sessions (session_status, user_id, last_activity) VALUES ('{$active_session}', '{$user_id}', '{$currentDateTime}')";
            $insertSessionResult = mysqli_query($conn, $insertSessionQuery);

            if (!$insertSessionResult) {
                handle_error('Error inserting session: ' . mysqli_error($conn));
            }
        }

        echo "success";
    }
} else {
    echo 'This user does not exist';
}

mysqli_close($conn);
?>

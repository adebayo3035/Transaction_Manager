<?php
include "config.php"; // Include database connection file
session_start();
if (!isset($_SESSION['unique_id'])) {
    header("Location: index.php"); // Redirect to login page if not logged in
    exit();
}

if (isset($_SESSION['unique_id'])) {
    $userId = $_SESSION['unique_id'];
    $sql = mysqli_query($conn, "SELECT * FROM admin_tbl WHERE unique_id = $userId");
    if (mysqli_num_rows($sql) > 0) {
        $row = mysqli_fetch_assoc($sql);
        echo json_encode($row); // Return user info as JSON
    } else {
        echo json_encode(null); // Return null if no user found
    }
} else {
    echo json_encode(null); // Return null if no user id set
}


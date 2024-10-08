<?php
session_start();
header('Content-Type: application/json');

if (isset($_SESSION['driver_id'])) {
    echo json_encode(['driver_id' => $_SESSION['driver_id']]);
} else {
    // Return null if session has expired or is not set
    echo json_encode(['driver_id' => null]);
}


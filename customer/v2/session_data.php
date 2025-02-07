<?php

session_start();
header('Content-Type: application/json');

if (isset($_SESSION['customer_id'])) {
    echo json_encode(['customer_id' => $_SESSION['customer_id'], 'email' => $_SESSION['email']]);
} else {
    // Return null if session has expired or is not set
    echo json_encode(['customer_id' => null]);
}

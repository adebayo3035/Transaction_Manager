<?php

header('Content-Type: application/json');

// Include database connection file
include('config.php');
session_start();
if (!isset($_SESSION['unique_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}

// Get the logged-in user's role from the session
$loggedInUserRole = $_SESSION['role'];

// Get the request data
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['promo_id'])) {
    $promo_id = $data['promo_id'];

    // Fetch promo details
    $query = "SELECT * from promo WHERE promo_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $promo_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Include logged in user's role in the response
        echo json_encode([
            'success' => true, 
            'promo_details' => $row,
            'logged_in_user_role' => $loggedInUserRole // Include the user's role
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No record found for this promo.']);
    }

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
}

$conn->close();

<?php
// Include database connection
include('config.php');
session_start();

if (!isset($_SESSION['unique_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}

// Get JSON data from the request body
$data = json_decode(file_get_contents("php://input"), true);

if (isset($data['promo_id'])) {
    $promoId = $data['promo_id'];
    $delete_id = 1;
    
    // Prepare the SQL update delete_id column statement
    $query = "UPDATE promo SET 
                delete_id = ?, 
                date_last_modified = NOW() 
              WHERE promo_id = ?";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit();
    }

    // Bind parameters to the SQL statement
    $stmt->bind_param(
        "ii",
        $delete_id,
        $promoId
    );

    // Execute the query and check for success
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Promo offer have been successfully Deleted.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error Deleting promo offer: ' . $stmt->error]);
    }

    // Close statement and connection
    $stmt->close();
} else {
    // Return error response if the ID is not provided
    echo json_encode(["success" => false, "message" => "Promo ID is required."]);
    exit();
}

// Close the database connection
$conn->close();

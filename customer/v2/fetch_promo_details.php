<?php
include 'config.php';
session_start();

logActivity("Starting Session validated for User");
$customerId = $_SESSION["customer_id"] ?? null;
checkSession($customerId);

logActivity("Session validated for customer ID: $customerId");


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    logActivity("Received a POST request.");
    
    $input = json_decode(file_get_contents('php://input'), true);
    logActivity("Decoded input: " . json_encode($input));
    
    if (!isset($input['promo_id'])) {
        logActivity("Promo ID is missing in the request.");
        echo json_encode(["success" => false, "message" => "Promo ID is missing."]);
        exit();
    }

    $promoId = $input['promo_id'];
    logActivity("Processing promo ID: $promoId");
    
    $query = "SELECT * FROM promo WHERE promo_id = ?";
    logActivity("Prepared SQL Query: $query");
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        logActivity("Failed to prepare statement: " . $conn->error);
        echo json_encode(["success" => false, "message" => "Database error."]);
        exit();
    }

    $stmt->bind_param("i", $promoId);
    logActivity("Bound parameter: promo_id = $promoId");
    
    if (!$stmt->execute()) {
        logActivity("Query execution failed: " . $stmt->error);
        echo json_encode(["success" => false, "message" => "Failed to retrieve promo details."]);
        exit();
    }

    logActivity("Query executed successfully.");
    $result = $stmt->get_result();
    $promoDetails = [];
    
    while ($row = $result->fetch_assoc()) {
        $promoDetails[] = $row;
    }

    logActivity("Fetched promo details: " . json_encode($promoDetails));
    $stmt->close();
    
    echo json_encode(["success" => true, "promo_details" => $promoDetails]);
    logActivity("Response sent successfully.");
} else {
    logActivity("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
}

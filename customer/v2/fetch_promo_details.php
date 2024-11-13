<?php
include 'config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['promo_id'])) {
        echo json_encode(["success" => false, "message" => "Promo ID is missing."]);
        exit();
    }

    $promoId = $input['promo_id'];
    

    // $stmt = $conn->prepare("SELECT * FROM order_details WHERE order_id = ?");
    $query = "SELECT * FROM promo WHERE promo_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $promoId);
    $stmt->execute();
    $result = $stmt->get_result();

    $promoDetails = [];
    while ($row = $result->fetch_assoc()) {
        $promoDetails[] = $row;
    }
    $stmt->close();

    echo json_encode(["success" => true, "promo_details" => $promoDetails]);
} else {
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
}

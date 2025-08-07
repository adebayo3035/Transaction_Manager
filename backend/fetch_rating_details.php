<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

try {
    // Check authentication
    if (!isset($_SESSION['unique_id'])) {
        throw new Exception("Authentication required");
    }

    // Get the rating ID from POST data
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['rating_id'])) {
        throw new Exception("Rating ID not provided");
    }

    $ratingId = (int)$input['rating_id'];
    $userId = $_SESSION['unique_id'];
    $userRole = $_SESSION['role'] ?? 'Unknown';

    // Base query with JOIN to get related order and user information
    $query = "SELECT 
                r.*,
                o.order_id,
                c.firstname as customer_firstname,
                c.lastname as customer_lastname,
                c.photo as customer_photo,
                c.mobile_number as customer_phone,
                d.firstname as driver_firstname,
                d.lastname as driver_lastname,
                d.photo as driver_photo,
                d.vehicle_type as driver_vehicle
              FROM order_ratings r
              JOIN orders o ON r.order_id = o.order_id
              JOIN customers c ON r.customer_id = c.customer_id
              LEFT JOIN driver d ON r.driver_id = d.id
              WHERE r.rating_id = ?";

    // Add role-based filtering for Admin
    if ($userRole == 'Admin') {
        $query .= " AND o.assigned_to = ?";
    } elseif ($userRole != 'Super Admin') {
        throw new Exception("Unauthorized access");
    }

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }

    // Bind parameters based on role
    if ($userRole == 'Admin') {
        $stmt->bind_param("ii", $ratingId, $userId);
    } else {
        $stmt->bind_param("i", $ratingId);
    }

    if (!$stmt->execute()) {
        throw new Exception("Failed to execute query: " . $stmt->error);
    }

    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        throw new Exception("Rating not found or access denied");
    }

    $ratingDetails = $result->fetch_assoc();

    // Format the response
    $response = [
        'success' => true,
        'rating_details' => [
            [
                'rating_id' => $ratingDetails['rating_id'],
                'order_id' => $ratingDetails['order_id'],
                'customer_id' => $ratingDetails['customer_id'],
                'customer_photo' => $ratingDetails['customer_photo'],
                'customer_phone' => $ratingDetails['customer_phone'],
                'customer_name' => $ratingDetails['customer_firstname'] . ' ' . $ratingDetails['customer_lastname'],
                'driver_id' => $ratingDetails['driver_id'],
                'driver_photo' => $ratingDetails['driver_photo'],
                'driver_vehicle' => $ratingDetails['driver_vehicle'],
                'driver_name' => $ratingDetails['driver_firstname'] ? 
                    $ratingDetails['driver_firstname'] . ' ' . $ratingDetails['driver_lastname'] : 'N/A',
                'food_rating' => $ratingDetails['food_rating'],
                'packaging_rating' => $ratingDetails['packaging_rating'],
                'driver_rating' => $ratingDetails['driver_rating'],
                'delivery_time_rating' => $ratingDetails['delivery_time_rating'],
                'driver_comment' => $ratingDetails['driver_comment'] ?? null,
                'order_comment' => $ratingDetails['order_comment'] ?? null,
                'rated_at' => $ratingDetails['rated_at']
            ]
        ]
    ];

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($conn)) $conn->close();
}
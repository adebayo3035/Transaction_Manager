<?php
header('Content-Type: application/json');
require_once 'config.php';

// Start session and validate
session_start();
logActivity("Rating submission process started");

// Check if customer is logged in
if (!isset($_SESSION['customer_id'])) {
    $errorMsg = "Unauthenticated access attempt";
    logActivity($errorMsg);
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit();
}

$sessionCustomerId = $_SESSION['customer_id'];
logActivity("Session validated for customer_id: $sessionCustomerId");

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $errorMsg = "Invalid request method: " . $_SERVER['REQUEST_METHOD'];
    logActivity($errorMsg);
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get and validate JSON input
$input = file_get_contents('php://input');
logActivity("Received input data: " . substr($input, 0, 200)); // Log first 200 chars of input

$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    $errorMsg = "Invalid JSON data: " . json_last_error_msg();
    logActivity($errorMsg);
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON data']);
    exit();
}

logActivity("JSON data parsed successfully");

// Validate required fields
$required = ['order_id', 'driver_id', 'driver_rating'];
foreach ($required as $field) {
    if (!isset($data[$field]) || $data[$field] === '') {
        $errorMsg = "Missing required field: $field";
        logActivity($errorMsg);
        http_response_code(400);
        echo json_encode(['error' => ucfirst(str_replace('_', ' ', $field)) . ' is required']);
        exit();
    }
}

logActivity("All required fields present in request");

// Verify session customer exists
if (!$sessionCustomerId) {
    $errorMsg = "Session customer_id not found";
    logActivity($errorMsg);
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Validate rating values (1-5 or null)
$ratingFields = [
    'driver_rating', 
    'food_rating', 
    'packaging_rating', 
    'delivery_time_rating'
];

foreach ($ratingFields as $field) {
    if (isset($data[$field]) && $data[$field] !== null) {
        if (!is_numeric($data[$field]) || $data[$field] < 1 || $data[$field] > 5) {
            $errorMsg = "Invalid rating value for $field: " . $data[$field];
            logActivity($errorMsg);
            http_response_code(400);
            echo json_encode(['error' => ucfirst(str_replace('_', ' ', $field)) . ' must be between 1 and 5 or null']);
            exit();
        }
    }
}

logActivity("All rating values validated");

// Sanitize inputs
$order_id = $data['order_id'];
$customer_id = $sessionCustomerId;
$driver_id = (int)$data['driver_id'];
$driver_rating = (int)$data['driver_rating'];
$driver_comment = $data['driver_comment'] ?? null;
$food_rating = isset($data['food_rating']) ? (int)$data['food_rating'] : 0;
$packaging_rating = isset($data['packaging_rating']) ? (int)$data['packaging_rating'] : 0;
$delivery_time_rating = isset($data['delivery_time_rating']) ? (int)$data['delivery_time_rating'] : 0;
$order_comment = $data['order_comment'] ?? null;

logActivity("Processing rating for order_id: $order_id, customer_id: $customer_id, driver_id: $driver_id");

// Begin transaction
$conn->begin_transaction();
logActivity("Database transaction started");

try {
    // Verify order exists and belongs to customer
    $checkOrder = $conn->prepare("
        SELECT order_id 
        FROM orders 
        WHERE order_id = ? AND customer_id = ?
        FOR UPDATE
    ");
    
    if (!$checkOrder) {
        throw new Exception("Prepare failed for order verification: " . $conn->error);
    }
    
    $checkOrder->bind_param("si", $order_id, $customer_id);
    if (!$checkOrder->execute()) {
        throw new Exception("Execute failed for order verification: " . $checkOrder->error);
    }
    
    if ($checkOrder->get_result()->num_rows === 0) {
        $errorMsg = "Order not found or doesn't belong to customer: order_id=$order_id, customer_id=$customer_id";
        logActivity($errorMsg);
        throw new Exception('Order not found or does not belong to you');
    }
    
    logActivity("Order validation successful for order_id: $order_id");
    
    // Check for existing rating
    $checkRating = $conn->prepare("
        SELECT rating_id 
        FROM order_ratings 
        WHERE order_id = ?
    ");
    
    if (!$checkRating) {
        throw new Exception("Prepare failed for rating check: " . $conn->error);
    }
    
    $checkRating->bind_param("s", $order_id);
    if (!$checkRating->execute()) {
        throw new Exception("Execute failed for rating check: " . $checkRating->error);
    }
    
    if ($checkRating->get_result()->num_rows > 0) {
        $errorMsg = "Duplicate rating attempt for order_id: $order_id";
        logActivity($errorMsg);
        throw new Exception('This order has already been rated');
    }
    
    logActivity("No existing rating found for order_id: $order_id");
    
    // Insert new rating
    $insertRating = $conn->prepare("
        INSERT INTO order_ratings (
            order_id, driver_id, customer_id, driver_rating, driver_comment,
            food_rating, packaging_rating, delivery_time_rating, order_comment
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    if (!$insertRating) {
        throw new Exception("Prepare failed for rating insert: " . $conn->error);
    }
    
    $insertRating->bind_param(
        "siiisiiis",
        $order_id,
        $driver_id,
        $customer_id,
        $driver_rating,
        $driver_comment,
        $food_rating,
        $packaging_rating,
        $delivery_time_rating,
        $order_comment
    );
    
    if (!$insertRating->execute()) {
        throw new Exception("Execute failed for rating insert: " . $insertRating->error);
    }
    
    $rating_id = $conn->insert_id;
    $conn->commit();
    
    $successMsg = "Rating submitted successfully. rating_id: $rating_id, order_id: $order_id, customer_id: $customer_id";
    logActivity($successMsg);
    
    // Success response
    echo json_encode([
        'success' => true,
        'message' => 'Thank you for your feedback!',
        'rating_id' => $rating_id
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    $errorMsg = "Error processing rating: " . $e->getMessage() . " for order_id: $order_id";
    logActivity($errorMsg);
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} finally {
    if (isset($checkOrder)) {
        $checkOrder->close();
        logActivity("Order verification statement closed");
    }
    if (isset($checkRating)) {
        $checkRating->close();
        logActivity("Rating check statement closed");
    }
    if (isset($insertRating)) {
        $insertRating->close();
        logActivity("Rating insert statement closed");
    }
    logActivity("Rating submission process completed");
}
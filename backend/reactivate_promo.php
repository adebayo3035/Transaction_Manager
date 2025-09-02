<?php
include('config.php');
session_start();

// Set content type first
header('Content-Type: application/json');

// 1. Authentication and Authorization Check
if (!isset($_SESSION['unique_id'])) {
    logActivity("Unauthorized access attempt: User not logged in.");
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}

// 2. Request Validation
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logActivity("Invalid request method for promo Reactivation.");
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit();
}

// 3. Input Validation
$contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
if (strpos($contentType, "application/json") === false) {
    logActivity("Invalid content type for promo update: " . $contentType);
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Content-Type must be application/json"]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    logActivity("Invalid JSON data received for promo update.");
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid JSON data"]);
    exit();
}

// 4. Data Sanitization
$promoId = filter_var($data['promo_id'] ?? null, FILTER_VALIDATE_INT);
if ($promoId === false || $promoId <= 0) {
    logActivity("Invalid promo ID format.");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid promo ID format.']);
    exit();
}

// 5. Business Logic Validations
try {
    // Check if promo exists and is currently deactivated
    $checkQuery = "SELECT delete_id, promo_name FROM promo WHERE promo_id = ?";
    $stmt = $conn->prepare($checkQuery);
    if (!$stmt) {
        logActivity("Database error preparing check for promo ID $promoId: " . $conn->error);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error.']);
        exit();
    }

    $stmt->bind_param("i", $promoId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        logActivity("Promo ID $promoId not found.");
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Promo not found.']);
        $stmt->close();
        exit();
    }

    $row = $result->fetch_assoc();
    $stmt->close();

    // Check if promo is already active (not deactivated)
    if ($row['delete_id'] == 0) {
        logActivity("Promo '{$row['promo_name']}' (ID: $promoId) is already active.");
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Promo is already active.']);
        exit();
    }

    // 6. Database Update
    $conn->begin_transaction();
    $delete_id = 0;
    $status = 1;
    $currentDateTime = date('Y-m-d H:i:s');
    $endDateTime = date('Y-m-d H:i:s', strtotime('+7 days'));

    $query = "UPDATE promo SET 
    delete_id = ?, 
    status = ?,
    start_date = ?,
    end_date = ?,
    date_last_modified = NOW() 
    WHERE promo_id = ?";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        $conn->rollback();
        logActivity(message: "Database error preparing update for promo ID $promoId: " . $conn->error);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error.']);
        exit();
    }

    $stmt->bind_param("iissi", $delete_id, $status, $currentDateTime, $endDateTime, $promoId);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $conn->commit();
        logActivity("Promo '{$row['promo_name']}' (ID: $promoId) has been successfully Reactivated. Start date: $currentDateTime, End date: $endDateTime. Affected rows: " . $stmt->affected_rows);
        echo json_encode([
            'success' => true,
            'message' => 'Promo offer Reactivated successfully.',
            'new_dates' => [
                'start_date' => $currentDateTime,
                'end_date' => $endDateTime
            ]
        ]);
    } else {
        $conn->rollback();
        logActivity("Execution failed for promo ID $promoId: " . $stmt->error . ". Affected rows: " . $stmt->affected_rows);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error Reactivating promo offer. No changes made.']);
    }

    $stmt->close();

} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    logActivity("Exception occurred while updating promo ID $promoId: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred while processing your request.']);
}

if (isset($conn)) {
    $conn->close();
}
logActivity("Connection closed after processing promo reactivation for ID $promoId.");

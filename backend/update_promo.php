<?php
include('config.php');
session_start();

// 1. Authentication and Authorization Check
if (!isset($_SESSION['unique_id'])) {
    logActivity("Unauthorized access attempt: User not logged in.");
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}

// 2. Request Validation
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logActivity("Invalid request method for promo update.");
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit();
}

// 3. Input Validation
$contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
if ($contentType !== "application/json") {
    logActivity("Invalid content type for promo update.");
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(["success" => false, "message" => "Content-Type must be application/json"]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    logActivity("Invalid JSON data received for promo update.");
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(["success" => false, "message" => "Invalid JSON data"]);
    exit();
}

// 4. Required Fields Check
$requiredFields = [
    'promo_id', 'promo_code', 'promo_name', 'description', 'date_created',
    'start_date', 'end_date', 'discount_type', 'discount_percentage',
    'eligibility_criteria', 'min_order_value', 'max_discount', 'status'
];

foreach ($requiredFields as $field) {
    if (!isset($data[$field])) {
        logActivity("Missing required field: $field");
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit();
    }
}

// 5. Data Sanitization
$promoId = filter_var($data['promo_id'], FILTER_VALIDATE_INT);
if ($promoId === false || $promoId <= 0) {
    logActivity("Invalid promo ID format.");
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Invalid promo ID format.']);
    exit();
}

$promoCode = trim($data['promo_code']);
$promoName = trim($data['promo_name']);
$description = trim($data['description']);
$dateCreated = trim($data['date_created']);
$startDate = trim($data['start_date']);
$endDate = trim($data['end_date']);
$discountType = trim($data['discount_type']);
$discountPercentage = filter_var($data['discount_percentage'], FILTER_VALIDATE_FLOAT);
$eligibilityCriteria = trim($data['eligibility_criteria']);
$minOrderValue = filter_var($data['min_order_value'], FILTER_VALIDATE_FLOAT);
$maxDiscount = filter_var($data['max_discount'], FILTER_VALIDATE_FLOAT);
$status = filter_var($data['status'], FILTER_VALIDATE_INT);

// 6. Business Logic Validations
try {
    // Date Validations
    $dateCreatedObj = new DateTime($dateCreated);
    $startDateObj = new DateTime($startDate);
    $endDateObj = new DateTime($endDate);
    $currentDate = new DateTime();

    if ($startDateObj < $dateCreatedObj) {
        logActivity("Start date before date created for promo ID $promoId");
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['success' => false, 'message' => 'Start date cannot be before creation date.']);
        exit();
    }

    if ($startDateObj >= $endDateObj) {
        logActivity("Start date not before end date for promo ID $promoId");
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['success' => false, 'message' => 'Start date must be before end date.']);
        exit();
    }

    if ($endDateObj < $currentDate) {
        logActivity("End date in the past for promo ID $promoId");
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['success' => false, 'message' => 'End date cannot be in the past.']);
        exit();
    }

    // Status Validation
    if (!in_array($status, [0, 1], true)) {
        logActivity("Invalid status value '$status' for promo ID $promoId");
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['success' => false, 'message' => 'Invalid status value. Must be 0 or 1.']);
        exit();
    }

    // Eligibility Criteria Validation
    $validCriteria = ['All Customers', 'New Customers', 'Others'];
    if (!in_array($eligibilityCriteria, $validCriteria, true)) {
        logActivity("Invalid eligibility criteria '$eligibilityCriteria' for promo ID $promoId");
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['success' => false, 'message' => 'Invalid eligibility criteria.']);
        exit();
    }

    // Numeric Value Validations
    if ($discountPercentage === false || $discountPercentage <= 0 || $discountPercentage > 100) {
        logActivity("Invalid discount percentage for promo ID $promoId");
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['success' => false, 'message' => 'Discount percentage must be between 0 and 100.']);
        exit();
    }

    if ($minOrderValue === false || $minOrderValue < 0) {
        logActivity("Invalid minimum order value for promo ID $promoId");
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['success' => false, 'message' => 'Minimum order value must be a positive number.']);
        exit();
    }

    if ($maxDiscount === false || $maxDiscount < 0) {
        logActivity("Invalid maximum discount for promo ID $promoId");
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['success' => false, 'message' => 'Maximum discount must be a positive number.']);
        exit();
    }

    // Promo Code Validation
    if (!preg_match('/^[A-Z0-9_-]{3,20}$/i', $promoCode)) {
        logActivity("Invalid promo code format for promo ID $promoId");
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['success' => false, 'message' => 'Promo code must be 3-20 alphanumeric characters (may include - or _).']);
        exit();
    }

    // Promo Name Validation
    if (strlen($promoName) < 3 || strlen($promoName) > 100) {
        logActivity("Invalid promo name length for promo ID $promoId");
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['success' => false, 'message' => 'Promo name must be between 3 and 100 characters.']);
        exit();
    }

    // Check for duplicate promo name
    $checkQuery = "SELECT promo_name FROM promo WHERE promo_name = ? AND promo_id != ? AND delete_id = 0";
    $stmt = $conn->prepare($checkQuery);
    if (!$stmt) {
        logActivity("Database error preparing duplicate check for promo ID $promoId: " . $conn->error);
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['success' => false, 'message' => 'Database error.']);
        exit();
    }

    $stmt->bind_param("si", $promoName, $promoId);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        logActivity("Promo name '$promoName' already exists for a different promo ID.");
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['success' => false, 'message' => 'Promo name already exists. Please try another name.']);
        $stmt->close();
        exit();
    }
    $stmt->close();

    // 7. Database Update
    $conn->begin_transaction();

    $query = "UPDATE promo SET 
        promo_code = ?, 
        promo_name = ?, 
        promo_description = ?, 
        start_date = ?, 
        end_date = ?, 
        discount_type = ?, 
        discount_value = ?, 
        eligibility_criteria = ?, 
        min_order_value = ?, 
        max_discount = ?, 
        status = ?, 
        date_last_modified = NOW() 
        WHERE promo_id = ?";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        logActivity("Database error preparing update for promo ID $promoId: " . $conn->error);
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['success' => false, 'message' => 'Database error.']);
        exit();
    }

    $stmt->bind_param(
        "ssssssdsdiii", 
        $promoCode, $promoName, $description, $startDate, $endDate, $discountType, 
        $discountPercentage, $eligibilityCriteria, $minOrderValue, $maxDiscount, 
        $status, $promoId
    );

    if ($stmt->execute()) {
        $conn->commit();
        logActivity("Promo ID $promoId updated successfully.");
        echo json_encode(['success' => true, 'message' => 'Promo offer updated successfully.']);
    } else {
        $conn->rollback();
        logActivity("Execution failed for promo ID $promoId: " . $stmt->error);
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['success' => false, 'message' => 'Error updating promo offer.']);
    }

    $stmt->close();

} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    logActivity("Exception occurred while updating promo ID $promoId: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'message' => 'An error occurred while processing your request.']);
}

if (isset($conn)) {
    $conn->close();
}
logActivity("Connection closed after processing promo update for ID $promoId.");
<?php
include('config.php');
session_start();

// 1. Authentication and Authorization Check
if (!isset($_SESSION['unique_id'])) {
    logActivity("Unauthorized access attempt to update_promo.php: User not logged in. Session: " . json_encode($_SESSION));
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}

// Log the start of promo update process
logActivity("Promo update process started by user ID: {$_SESSION['unique_id']}. Request method: {$_SERVER['REQUEST_METHOD']}");

// 2. Request Validation
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logActivity("Invalid request method for promo update. Expected: POST, Got: {$_SERVER['REQUEST_METHOD']}. User ID: {$_SESSION['unique_id']}");
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit();
}

// 3. Input Validation
$contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
if ($contentType !== "application/json") {
    logActivity("Invalid content type for promo update. Expected: application/json, Got: {$contentType}. User ID: {$_SESSION['unique_id']}");
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(["success" => false, "message" => "Content-Type must be application/json"]);
    exit();
}

$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    $jsonError = json_last_error_msg();
    logActivity("Invalid JSON data received for promo update. JSON error: {$jsonError}. Raw input: " . substr($rawInput, 0, 500) . ". User ID: {$_SESSION['unique_id']}");
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(["success" => false, "message" => "Invalid JSON data"]);
    exit();
}

// Log received data (sanitized for security)
$loggableData = $data;
if (isset($loggableData['description'])) {
    $loggableData['description'] = substr($loggableData['description'], 0, 100) . (strlen($loggableData['description']) > 100 ? '...' : '');
}
logActivity("Received promo update data: " . json_encode($loggableData) . ". User ID: {$_SESSION['unique_id']}");

// 4. Required Fields Check
$requiredFields = [
    'promo_id',
    'promo_code',
    'promo_name',
    'description',
    'date_created',
    'start_date',
    'end_date',
    'discount_type',
    'discount_value',
    'eligibility_criteria',
    'min_order_value',
    'max_discount',
    'status'
];

$missingFields = [];
foreach ($requiredFields as $field) {
    if (!isset($data[$field])) {
        $missingFields[] = $field;
    }
}

if (!empty($missingFields)) {
    $missingFieldsStr = implode(', ', $missingFields);
    logActivity("Missing required fields for promo update: {$missingFieldsStr}. User ID: {$_SESSION['unique_id']}, Promo ID: " . ($data['promo_id'] ?? 'unknown'));
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => "Missing required fields: {$missingFieldsStr}"]);
    exit();
}

// 5. Data Sanitization
$promoId = filter_var($data['promo_id'], FILTER_VALIDATE_INT);
if ($promoId === false || $promoId <= 0) {
    logActivity("Invalid promo ID format. Received: {$data['promo_id']}. User ID: {$_SESSION['unique_id']}");
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
$discountValue = filter_var($data['discount_value'], FILTER_VALIDATE_FLOAT);
$eligibilityCriteria = trim($data['eligibility_criteria']);
$minOrderValue = filter_var($data['min_order_value'], FILTER_VALIDATE_FLOAT);
$maxDiscount = filter_var($data['max_discount'], FILTER_VALIDATE_FLOAT);
$status = filter_var($data['status'], FILTER_VALIDATE_INT);

// Log sanitized values
logActivity("Sanitized values - Promo ID: {$promoId}, Code: {$promoCode}, Name: {$promoName}, Discount Type: {$discountType}, Discount Value: {$discountValue}, Status: {$status}. User ID: {$_SESSION['unique_id']}");

// 6. Business Logic Validations
try {
    // Date Validations
    $dateCreatedObj = new DateTime($dateCreated);
    $startDateObj = new DateTime($startDate);
    $endDateObj = new DateTime($endDate);
    $currentDate = new DateTime();

    if ($startDateObj < $dateCreatedObj) {
        logActivity("Start date validation failed: Start date ({$startDate}) before creation date ({$dateCreated}) for promo ID {$promoId}. User ID: {$_SESSION['unique_id']}");
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['success' => false, 'message' => 'Start date cannot be before creation date.']);
        exit();
    }

    if ($startDateObj >= $endDateObj) {
        logActivity("Date validation failed: Start date ({$startDate}) not before end date ({$endDate}) for promo ID {$promoId}. User ID: {$_SESSION['unique_id']}");
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['success' => false, 'message' => 'Start date must be before end date.']);
        exit();
    }

    if ($endDateObj < $currentDate) {
        $currentDateStr = $currentDate->format('Y-m-d H:i:s');
        logActivity("Date validation failed: End date ({$endDate}) in the past (current: {$currentDateStr}) for promo ID {$promoId}. User ID: {$_SESSION['unique_id']}");
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['success' => false, 'message' => 'End date cannot be in the past.']);
        exit();
    }

    // Status Validation
    if (!in_array($status, [0, 1], true)) {
        logActivity("Invalid status value '{$status}' for promo ID {$promoId}. User ID: {$_SESSION['unique_id']}");
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['success' => false, 'message' => 'Invalid status value. Must be 0 or 1.']);
        exit();
    }

    // Eligibility Criteria Validation
    $validCriteria = ['All Customers', 'New Customers', 'Others'];
    if (!in_array($eligibilityCriteria, $validCriteria, true)) {
        logActivity("Invalid eligibility criteria '{$eligibilityCriteria}' for promo ID {$promoId}. Valid options: " . implode(', ', $validCriteria) . ". User ID: {$_SESSION['unique_id']}");
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['success' => false, 'message' => 'Invalid eligibility criteria.']);
        exit();
    }

    if ($discountType === 'percentage') {
        // Percentage validation
        if ($discountValue === false || $discountValue <= 0 || $discountValue > 100) {
            logActivity("Invalid discount percentage '{$data['discount_value']}' for promo ID {$promoId}. Must be between 0-100. User ID: {$_SESSION['unique_id']}");
            header('HTTP/1.1 400 Bad Request');
            echo json_encode([
                'success' => false,
                'message' => 'Discount percentage must be between 0 and 100.'
            ]);
            exit();
        }
    } elseif ($discountType === 'flat') {
        // Flat value validation
        if ($discountValue === false || $discountValue <= 0) {
            logActivity("Invalid flat discount value '{$data['discount_value']}' for promo ID {$promoId}. Must be greater than 0. User ID: {$_SESSION['unique_id']}");
            header('HTTP/1.1 400 Bad Request');
            echo json_encode([
                'success' => false,
                'message' => 'Flat discount must be greater than 0.'
            ]);
            exit();
        }

        // Ensure min_order_value is greater than flat discount
        if ($minOrderValue <= $discountValue) {
            logActivity("Validation failed: Flat discount ({$discountValue}) is greater than or equal to min_order_value ({$minOrderValue}) for promo ID {$promoId}. User ID: {$_SESSION['unique_id']}");
            header('HTTP/1.1 400 Bad Request');
            echo json_encode([
                'success' => false,
                'message' => 'Minimum order value must be greater than the flat discount amount.'
            ]);
            exit();
        }
        // Ensure min_order_value is greater than flat discount
        if ($maxDiscount < $discountValue) {
            logActivity("Validation failed: Flat discount ({$discountValue}) is greater than Maximum Discount Value ({$minOrderValue}) for promo ID {$promoId}. User ID: {$_SESSION['unique_id']}");
            header('HTTP/1.1 400 Bad Request');
            echo json_encode([
                'success' => false,
                'message' => 'Maximum Discount must be the same as flat discount amount.'
            ]);
            exit();
        }
    }

    if ($minOrderValue === false || $minOrderValue < 0) {
        logActivity("Invalid minimum order value '{$data['min_order_value']}' for promo ID {$promoId}. User ID: {$_SESSION['unique_id']}");
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['success' => false, 'message' => 'Minimum order value must be a positive number.']);
        exit();
    }

    if ($maxDiscount === false || $maxDiscount < 0) {
        logActivity("Invalid maximum discount '{$data['max_discount']}' for promo ID {$promoId}. User ID: {$_SESSION['unique_id']}");
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['success' => false, 'message' => 'Maximum discount must be a positive number.']);
        exit();
    }

    // Promo Code Validation
    if (!preg_match('/^[A-Z0-9_-]{3,20}$/i', $promoCode)) {
        logActivity("Invalid promo code format '{$promoCode}' for promo ID {$promoId}. User ID: {$_SESSION['unique_id']}");
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['success' => false, 'message' => 'Promo code must be 3-20 alphanumeric characters (may include - or _).']);
        exit();
    }

    // Promo Name Validation
    if (strlen($promoName) < 3 || strlen($promoName) > 100) {
        $nameLength = strlen($promoName);
        logActivity("Invalid promo name length for promo ID {$promoId}. Length: {$nameLength}, Name: '{$promoName}'. User ID: {$_SESSION['unique_id']}");
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['success' => false, 'message' => 'Promo name must be between 3 and 100 characters.']);
        exit();
    }

    // Combined check for duplicate name and deactivation status
    // 1. Check for duplicate active promo name
    $duplicateQuery = "
    SELECT promo_id, promo_code, promo_name 
    FROM promo 
    WHERE promo_name = ? AND promo_id != ? AND delete_id = 0
";
    $stmt = $conn->prepare($duplicateQuery);
    $stmt->bind_param("si", $promoName, $promoId);
    $stmt->execute();
    $duplicateResult = $stmt->get_result();
    $duplicateRow = $duplicateResult->fetch_assoc();

    if ($duplicateRow) {
        logActivity("Duplicate promo detected: [ID: {$duplicateRow['promo_id']}, Code: {$duplicateRow['promo_code']}, Name: {$duplicateRow['promo_name']}] conflicts with attempted update to promo ID {$promoId}. User ID: {$_SESSION['unique_id']}");
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['success' => false, 'message' => 'Promo name already exists. Please try another name.']);
        $stmt->close();
        exit();
    }
    $stmt->close();

    // 2. Check if the promo being updated is deactivated
    $deactivatedQuery = "SELECT delete_id FROM promo WHERE promo_id = ?";
    $stmt = $conn->prepare($deactivatedQuery);
    $stmt->bind_param("i", $promoId);
    $stmt->execute();
    $deactivatedResult = $stmt->get_result();
    $deactivatedRow = $deactivatedResult->fetch_assoc();

    if ($deactivatedRow && $deactivatedRow['delete_id'] != 0) {
        logActivity("Attempt to update deactivated promo '{$promoName}' (ID: {$promoId}). User ID: {$_SESSION['unique_id']}");
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['success' => false, 'message' => 'Promo is currently Deactivated.']);
        $stmt->close();
        exit();
    }
    $stmt->close();

    // 7. Database Update
    $conn->begin_transaction();
    logActivity("Starting database update for promo ID {$promoId}. User ID: {$_SESSION['unique_id']}");

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
        $dbError = $conn->error;
        logActivity("Database error preparing update for promo ID {$promoId}: {$dbError}. User ID: {$_SESSION['unique_id']}");
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['success' => false, 'message' => 'Database error.']);
        exit();
    }

    $stmt->bind_param(
        "ssssssdsdiii",
        $promoCode,
        $promoName,
        $description,
        $startDate,
        $endDate,
        $discountType,
        $discountValue,
        $eligibilityCriteria,
        $minOrderValue,
        $maxDiscount,
        $status,
        $promoId
    );

    if ($stmt->execute()) {
        $affectedRows = $stmt->affected_rows;
        $conn->commit();
        logActivity("Promo ID {$promoId} updated successfully. Affected rows: {$affectedRows}. User ID: {$_SESSION['unique_id']}");
        echo json_encode(['success' => true, 'message' => 'Promo offer updated successfully.']);
    } else {
        $stmtError = $stmt->error;
        $conn->rollback();
        logActivity("Execution failed for promo ID {$promoId}: {$stmtError}. User ID: {$_SESSION['unique_id']}");
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['success' => false, 'message' => 'Error updating promo offer.']);
    }

    $stmt->close();

} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    $exceptionMessage = $e->getMessage();
    logActivity("Exception occurred while updating promo ID {$promoId}: {$exceptionMessage}. User ID: {$_SESSION['unique_id']}");
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'message' => 'An error occurred while processing your request.']);
}

if (isset($conn)) {
    $conn->close();
    logActivity("Database connection closed after processing promo update for ID {$promoId}. User ID: {$_SESSION['unique_id']}");
} else {
    logActivity("No database connection to close after processing promo update for ID {$promoId}. User ID: {$_SESSION['unique_id']}");
}

logActivity("Promo update process completed for ID {$promoId}. User ID: {$_SESSION['unique_id']}");
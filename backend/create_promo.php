<?php
header('Content-Type: application/json');
include('config.php');
session_start();

// Initialize logging
logActivity("Promo creation process started");

// Check authentication
if (!isset($_SESSION['unique_id'])) {
    $errorMsg = "Unauthorized access attempt";
    logActivity($errorMsg);
    echo json_encode(["status" => false, "message" => "Not logged in."]);
    exit();
}

try {
    if ($_SERVER['REQUEST_METHOD'] != 'POST') {
        $errorMsg = "Invalid request method: " . $_SERVER['REQUEST_METHOD'];
        logActivity($errorMsg);
        echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
        exit();
    }

    // Validate and sanitize input data
    $requiredFields = [
        'promo_code', 'promo_name', 'start_date', 'end_date', 
        'discount_type', 'discount_value', 'min_order_value', 
        'max_discount', 'eligibility_criteria'
    ];

    $missingFields = [];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            $missingFields[] = $field;
        }
    }

    if (!empty($missingFields)) {
        $errorMsg = "Missing required fields: " . implode(', ', $missingFields);
        logActivity($errorMsg);
        echo json_encode(['status' => 'error', 'message' => 'All required fields must be filled.']);
        exit();
    }

    // Assign and sanitize input values
    $promoData = [
        'promo_code' => trim($_POST['promo_code']),
        'promo_name' => trim($_POST['promo_name']),
        'promo_description' => trim($_POST['promo_description'] ?? ''),
        'start_date' => $_POST['start_date'],
        'end_date' => $_POST['end_date'],
        'discount_type' => $_POST['discount_type'],
        'discount_value' => (float) $_POST['discount_value'],
        'eligibility_criteria' => $_POST['eligibility_criteria'],
        'min_order_value' => (float) $_POST['min_order_value'],
        'max_discount' => (float) $_POST['max_discount'],
        'status' => isset($_POST['status']) ? (int) $_POST['status'] : 1
    ];

    logActivity("Received promo data: " . json_encode($promoData));

    // Status validation
    if (!in_array($promoData['status'], [0, 1])) {
        $errorMsg = "Invalid status value: " . $promoData['status'];
        logActivity($errorMsg);
        echo json_encode(['status' => false, 'message' => 'Invalid status value. Must be 0 or 1.']);
        exit();
    }

    // Eligibility criteria validation
    $validCriteria = ['All Customers', 'New Customers', 'Others'];
    if (!in_array($promoData['eligibility_criteria'], $validCriteria)) {
        $errorMsg = "Invalid eligibility criteria: " . $promoData['eligibility_criteria'];
        logActivity($errorMsg);
        echo json_encode(['status' => false, 'message' => 'Invalid eligibility criteria.']);
        exit();
    }

    // Date validation
    try {
        $startDateObj = new DateTime($promoData['start_date']);
        $endDateObj = new DateTime($promoData['end_date']);
        $now = new DateTime();

        if ($startDateObj < $now) {
            $errorMsg = "Start date in past: " . $promoData['start_date'];
            logActivity($errorMsg);
            echo json_encode(['status' => 'error', 'message' => 'Start date cannot be in the past.']);
            exit();
        }

        if ($startDateObj >= $endDateObj) {
            $errorMsg = "Invalid date range: Start " . $promoData['start_date'] . " >= End " . $promoData['end_date'];
            logActivity($errorMsg);
            echo json_encode(['status' => 'error', 'message' => 'Start date must be before end date.']);
            exit();
        }
    } catch (Exception $e) {
        $errorMsg = "Invalid date format: " . $e->getMessage();
        logActivity($errorMsg);
        echo json_encode(['status' => 'error', 'message' => 'Invalid date format.']);
        exit();
    }

    // Check for duplicate promo name
    $checkQuery = "SELECT promo_id FROM promo WHERE promo_name = ?";
    $stmt = $conn->prepare($checkQuery);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("s", $promoData['promo_name']);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $errorMsg = "Duplicate promo name: " . $promoData['promo_name'];
        logActivity($errorMsg);
        echo json_encode(['status' => false, 'message' => 'Promo name already exists. Please try another name.']);
        exit();
    }
    $stmt->close();

    // Start transaction
    $conn->begin_transaction();
    logActivity("Transaction started for promo creation");

    try {
        $insertQuery = "INSERT INTO promo (
            promo_code, promo_name, promo_description, start_date, end_date, 
            discount_type, discount_value, eligibility_criteria, min_order_value, 
            max_discount, status, date_created, date_last_modified
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

        $stmt = $conn->prepare($insertQuery);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param(
            "ssssssdsddi",
            $promoData['promo_code'],
            $promoData['promo_name'],
            $promoData['promo_description'],
            $promoData['start_date'],
            $promoData['end_date'],
            $promoData['discount_type'],
            $promoData['discount_value'],
            $promoData['eligibility_criteria'],
            $promoData['min_order_value'],
            $promoData['max_discount'],
            $promoData['status']
        );

        if ($stmt->execute()) {
            $newPromoId = $conn->insert_id;
            $conn->commit();
            $successMsg = "Promo created successfully. ID: " . $newPromoId;
            logActivity($successMsg);
            echo json_encode(['status' => 'success', 'message' => 'Promo created successfully', 'promo_id' => $newPromoId]);
        } else {
            throw new Exception("Execute failed: " . $stmt->error);
        }
    } catch (Exception $e) {
        $conn->rollback();
        $errorMsg = "Promo creation failed: " . $e->getMessage();
        logActivity($errorMsg);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    } finally {
        if (isset($stmt) && $stmt instanceof mysqli_stmt) {
            $stmt->close();
        }
    }
} catch (Exception $e) {
    $errorMsg = "System error: " . $e->getMessage();
    logActivity($errorMsg);
    echo json_encode(['status' => 'error', 'message' => 'An unexpected error occurred.']);
} finally {
    $conn->close();
    logActivity("Promo creation process completed");
}
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
    $promoCode = $data['promo_code'];
    $promoName = $data['promo_name'];
    $description = $data['description'];
    $startDate = $data['start_date'];
    $endDate = $data['end_date'];
    $discountType = $data['discount_type'];
    $discountPercentage = $data['discount_percentage'];
    $eligibilityCriteria = $data['eligibility_criteria'];
    $minOrderValue = $data['min_order_value'];
    $maxDiscount = $data['max_discount'];
    $status = $data['status'];

    // Validate required fields
    if (empty($promoCode) || empty($promoName) || empty($startDate) || empty($endDate) || empty($discountType) || empty($discountPercentage) || empty($minOrderValue) || empty($maxDiscount) || empty($eligibilityCriteria)) {
        echo json_encode(['success' => false, 'message' => 'All required fields must be filled.']);
        exit();
    }

    // Status validation: Only 0 or 1 allowed
    if (!in_array($status, [0, 1])) {
        echo json_encode(['success' => false, 'message' => 'Invalid status value. Must be 0 or 1.']);
        exit();
    }

    // Eligibility criteria validation
    $validCriteria = ['All Customers', 'New Customers', 'Others'];
    if (!in_array($eligibilityCriteria, $validCriteria)) {
        echo json_encode(['success' => false, 'message' => 'Invalid eligibility criteria.']);
        exit();
    }

    // Date validation
    $startDateObj = new DateTime($startDate);
    $endDateObj = new DateTime($endDate);
    $now = new DateTime();

    if ($startDateObj < $now) {
        echo json_encode(['success' => false, 'message' => 'Start date cannot be in the past.']);
        exit();
    } elseif ($startDateObj >= $endDateObj) {
        echo json_encode(['success' => false, 'message' => 'Start date must be before end date.']);
        exit();
    }

    // Check for duplicate promo name, excluding the current Promo Offer
    $checkQuery = "SELECT promo_name FROM promo WHERE promo_name = ? AND promo_id != ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("si", $promoName, $promoId);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Promo name already exists. Please try another name.']);
        exit();
    }
    $stmt->close();

    // Prepare the SQL update statement
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
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit();
    }

    // Bind parameters to the SQL statement
    $stmt->bind_param(
        "ssssssdiddii",
        $promoCode,
        $promoName,
        $description,
        $startDate,
        $endDate,
        $discountType,
        $discountPercentage,
        $eligibilityCriteria,
        $minOrderValue,
        $maxDiscount,
        $status,
        $promoId
    );

    // Execute the query and check for success
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Promo offer updated successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating promo offer: ' . $stmt->error]);
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

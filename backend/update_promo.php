<?php
include('config.php');
session_start();

if (!isset($_SESSION['unique_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);

if (isset($data['promo_id'])) {
    $promoId = $data['promo_id'];
    $promoCode = $data['promo_code'];
    $promoName = $data['promo_name'];
    $description = $data['description'];
    $dateCreated = $data['date_created'];
    $startDate = $data['start_date'];
    $endDate = $data['end_date'];
    $discountType = $data['discount_type'];
    $discountPercentage = $data['discount_percentage'];
    $eligibilityCriteria = $data['eligibility_criteria'];
    $minOrderValue = $data['min_order_value'];
    $maxDiscount = $data['max_discount'];
    $status = $data['status'];

    if (empty($promoCode) || empty($promoName) || empty($startDate) || empty($endDate) || empty($discountType) || empty($discountPercentage) || empty($minOrderValue) || empty($maxDiscount) || empty($eligibilityCriteria)) {
        echo json_encode(['success' => false, 'message' => 'All required fields must be filled.']);
        exit();
    }

    try {
        $startDateObj = new DateTime($startDate);
        $endDateObj = new DateTime($endDate);
        $dateCreatedObj = new DateTime($dateCreated);

        if ($startDateObj < $dateCreatedObj) {
            echo json_encode(['status' => 'error', 'message' => 'Start date cannot be less than Date Promo was Created.']);
            exit();
        } elseif ($startDateObj >= $endDateObj) {
            echo json_encode(['status' => 'error', 'message' => 'Start date must be before end date.']);
            exit();
        }

        if (!in_array($status, [0, 1])) {
            echo json_encode(['success' => false, 'message' => 'Invalid status value. Must be 0 or 1.']);
            exit();
        }

        $validCriteria = ['All Customers', 'New Customers', 'Others'];
        if (!in_array($eligibilityCriteria, $validCriteria)) {
            echo json_encode(['success' => false, 'message' => 'Invalid eligibility criteria.']);
            exit();
        }

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
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
            exit();
        }

        $stmt->bind_param(
            "ssssssdsdiii", 
            $promoCode, $promoName, $description, $startDate, $endDate, $discountType, $discountPercentage,
            $eligibilityCriteria, $minOrderValue, $maxDiscount, $status, $promoId
        );

        if ($stmt->execute()) {
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Promo offer updated successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating promo offer: ' . $stmt->error]);
        }

        $stmt->close();
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Promo ID is required."]);
    exit();
}

$conn->close();

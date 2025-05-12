<?php
include('config.php');
session_start();

if (!isset($_SESSION['unique_id'])) {
    logActivity("Unauthorized access attempt: User not logged in.");
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['promo_id'])) {
    logActivity("Promo update failed: Missing promo_id in request.");
    echo json_encode(["success" => false, "message" => "Promo ID is required."]);
    exit();
}

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

logActivity("Promo update request received for ID: $promoId by user: {$_SESSION['unique_id']}");

if (
    empty($promoCode) || empty($promoName) || empty($startDate) || empty($endDate) ||
    empty($discountType) || empty($discountPercentage) || empty($minOrderValue) ||
    empty($maxDiscount) || empty($eligibilityCriteria)
) {
    logActivity("Validation failed: Missing required fields for promo ID $promoId");
    echo json_encode(['success' => false, 'message' => 'All required fields must be filled.']);
    exit();
}

try {
    $startDateObj = new DateTime($startDate);
    $endDateObj = new DateTime($endDate);
    $dateCreatedObj = new DateTime($dateCreated);

    if ($startDateObj < $dateCreatedObj) {
        logActivity("Validation failed: Start date before date created for promo ID $promoId");
        echo json_encode(['status' => 'error', 'message' => 'Start date cannot be less than Date Promo was Created.']);
        exit();
    } elseif ($startDateObj >= $endDateObj) {
        logActivity("Validation failed: Start date not before end date for promo ID $promoId");
        echo json_encode(['status' => 'error', 'message' => 'Start date must be before end date.']);
        exit();
    }

    if (!in_array($status, [0, 1])) {
        logActivity("Invalid status value '$status' for promo ID $promoId");
        echo json_encode(['success' => false, 'message' => 'Invalid status value. Must be 0 or 1.']);
        exit();
    }

    $validCriteria = ['All Customers', 'New Customers', 'Others'];
    if (!in_array($eligibilityCriteria, $validCriteria)) {
        logActivity("Invalid eligibility criteria '$eligibilityCriteria' for promo ID $promoId");
        echo json_encode(['success' => false, 'message' => 'Invalid eligibility criteria.']);
        exit();
    }

    $checkQuery = "SELECT promo_name FROM promo WHERE promo_name = ? AND promo_id != ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("si", $promoName, $promoId);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        logActivity("Promo name '$promoName' already exists for a different promo ID.");
        echo json_encode(['success' => false, 'message' => 'Promo name already exists. Please try another name.']);
        $stmt->close();
        exit();
    }
    $stmt->close();

    logActivity("Validation passed. Updating promo ID $promoId.");

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
        logActivity("Promo ID $promoId updated successfully.");
        echo json_encode(['success' => true, 'message' => 'Promo offer updated successfully.']);
    } else {
        logActivity("Execution failed for promo ID $promoId: " . $stmt->error);
        echo json_encode(['success' => false, 'message' => 'Error updating promo offer: ' . $stmt->error]);
    }

    $stmt->close();

} catch (Exception $e) {
    $conn->rollback();
    logActivity("Exception occurred while updating promo ID $promoId: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
logActivity("Connection closed after processing promo update for ID $promoId.");

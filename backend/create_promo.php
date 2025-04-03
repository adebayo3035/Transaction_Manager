<?php
header('Content-Type: application/json');
include('config.php');
session_start();

if (!isset($_SESSION['unique_id'])) {
    echo json_encode(["status" => false, "message" => "Not logged in."]);
    exit();
}

try {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $promoCode = $_POST['promo_code'];
        $promoName = $_POST['promo_name'];
        $promoDescription = $_POST['promo_description'];
        $startDate = $_POST['start_date'];
        $endDate = $_POST['end_date'];
        $discountType = $_POST['discount_type'];
        $discountValue = $_POST['discount_value'];
        $eligibilityCriteria = $_POST['eligibility_criteria'];
        $minOrderValue = $_POST['min_order_value'];
        $maxDiscount = $_POST['max_discount'];
        $status = isset($_POST['status']) ? (bool) $_POST['status'] : true;

        if (empty($promoCode) || empty($promoName) || empty($startDate) || empty($endDate) || empty($discountType) || empty($discountValue) || empty($minOrderValue) || empty($maxDiscount) || empty($eligibilityCriteria)) {
            echo json_encode(['status' => 'error', 'message' => 'All required fields must be filled.']);
            exit;
        }
        // Status validation: Only 0 or 1 allowed
        if (!in_array($status, [0, 1])) {
            echo json_encode(['status' => false, 'message' => 'Invalid status value. Must be 0 or 1.']);
            exit();
        }

        // Eligibility criteria validation
        $validCriteria = ['All Customers', 'New Customers', 'Others'];
        if (!in_array($eligibilityCriteria, $validCriteria)) {
            echo json_encode(['status' => false, 'message' => 'Invalid eligibility criteria.']);
            exit();
        }

        // Date comparison
        $startDateObj = new DateTime($startDate);
        $endDateObj = new DateTime($endDate);
        $now = new DateTime();

        if ($startDateObj < $now) {
            echo json_encode(['status' => 'error', 'message' => 'Start date cannot be in the past.']);
            exit;
        } elseif ($startDateObj >= $endDateObj) {
            echo json_encode(['status' => 'error', 'message' => 'Start date must be before end date.']);
            exit;
        }

        try {
            // Check for duplicate promo name, excluding the current Promo Offer
            $checkQuery = "SELECT promo_name FROM promo WHERE promo_name = ?";
            $stmt = $conn->prepare($checkQuery);
            $stmt->bind_param("s", $promoName);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                echo json_encode(['status' => false, 'message' => 'Promo name already exists. Please try another name.']);
                exit();
            }
            $stmt->close();
            $conn->begin_transaction();
            $stmt = $conn->prepare("INSERT INTO promo (
                promo_code, 
                promo_name, 
                promo_description, 
                start_date, 
                end_date, 
                discount_type, 
                discount_value, 
                eligibility_criteria, 
                min_order_value, 
                max_discount, 
                status, 
                date_created, 
                date_last_modified
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");

            $stmt->bind_param(
                "ssssssdsddi", // Corrected binding types
                $promoCode,
                $promoName,
                $promoDescription,
                $startDate,
                $endDate,
                $discountType,
                $discountValue,
                $eligibilityCriteria,
                $minOrderValue,
                $maxDiscount,
                $status
            );

            if ($stmt->execute()) {
                $conn->commit();
                echo json_encode(['status' => 'success', 'message' => 'Promo created successfully']);
            } else {
                throw new Exception('An Error Occurred: ' . $stmt->error);
            }

            $stmt->close();
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Exception occurred: ' . $e->getMessage()]);
}
$conn->close();

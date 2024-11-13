<?php
session_start();
include 'config.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Fetch customer ID from session
$customerId = $_SESSION['customer_id'] ?? null;
if (!$customerId) {
    echo json_encode(['success' => false, 'message' => 'Customer ID not found.']);
    exit;
}

// Decode the JSON input from request body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Check if JSON decoding was successful
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

// Extract promo code from the received JSON
$promo_code = $data['promo_code'] ?? null;
$total_order = $data['total_order'] ?? null;


// Fetch promo details from the database
$stmt = $conn->prepare("SELECT * FROM promo WHERE promo_code = ? AND status = 1 AND NOW() BETWEEN start_date AND end_date");
$stmt->bind_param("s", $promo_code);
$stmt->execute();
$result = $stmt->get_result();

if ($promo = $result->fetch_assoc()) {
    // Extract eligibility criteria from the promo
    $eligibility_criteria = $promo['eligibility_criteria'];
    $min_order_value = $promo['min_order_value'];
    $discount_value = $promo['discount_value'];
    $max_discount = $promo['max_discount'];

    if ($min_order_value > $total_order) {
        echo json_encode(['eligible' => false, 'message' => 'Your Total Order does not qualify for this promo', 'promo' => $promo]);
        exit();
    }

    // Check if the customer has already used this promo code today
    $usageStmt = $conn->prepare("SELECT COUNT(*) FROM promo_usage WHERE promo_code = ? AND customer_id = ? AND DATE(date_used) = CURDATE()");
    $usageStmt->bind_param("ss", $promo_code, $customerId);
    $usageStmt->execute();
    $usageResult = $usageStmt->get_result();
    $usedCount = $usageResult->fetch_row()[0];

    if ($usedCount > 0) {
        echo json_encode(['eligible' => false, 'message' => 'You have already used this promo code today.', 'promo' => $promo]);
        $usageStmt->close();
        $stmt->close();
        $conn->close();
        exit;
    }

    // Check eligibility criteria
    if ($eligibility_criteria === "New Customers") {
        // Fetch the customer's record to check registration date
        $customerStmt = $conn->prepare("SELECT date_created FROM customers WHERE customer_id = ?");
        $customerStmt->bind_param("s", $customerId);
        $customerStmt->execute();
        $customerResult = $customerStmt->get_result();

        if ($customerData = $customerResult->fetch_assoc()) {
            $registrationDate = new DateTime($customerData['date_created']);
            $currentDate = new DateTime();

            // Calculate the difference in days between registration and now
            $daysSinceRegistration = $currentDate->diff($registrationDate)->days;

            if ($daysSinceRegistration <= 5) {
                // Customer is eligible as they registered within the last 5 days
                $discount = ($discount_value / 100) * $total_order;
                if ($discount > $max_discount) { $discount = $max_discount; } echo json_encode(['eligible' => true, 'promo' => $promo, 'discount' => $discount,'discount_percent' => $discount_value, 'promo_code' => $promo_code]);
            } else {
                // Customer is not eligible
                echo json_encode(['eligible' => false, 'message' => 'Promo is only for new customers registered within the last 5 days.', 'promo' => $promo]);
            }
        } else {
            echo json_encode(['eligible' => false, 'message' => 'Customer record not found.', 'promo' => $promo]);
        }

        $customerStmt->close();
    } elseif ($eligibility_criteria === "All Customers") {
        // Customer is eligible as the criteria is for all customers
        $discount = ($discount_value / 100) * $total_order;
        if ($discount > $max_discount) { $discount = $max_discount; } echo json_encode(['eligible' => true, 'promo' => $promo, 'discount' => $discount, 'discount_percent' => $discount_value, 'promo_code' => $promo_code]);
    } else {
        // Handle any other eligibility criteria if needed
        echo json_encode(['eligible' => false, 'message' => 'Customer does not meet eligibility criteria.', 'promo' => $promo]);
    }
    $usageStmt->close();
} else {
    // Promo code is not valid or expired
    echo json_encode(['eligible' => false, 'message' => 'Promo not valid or expired', 'promo' => null]);
}

$stmt->close();
$conn->close();


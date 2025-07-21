<?php
session_start();
include 'config.php';
// include 'activity_logger.php'; // Include the logger file
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Log the start of the script
logActivity("Promo code validation script started.");

// Fetch customer ID from session
$customerId = $_SESSION["customer_id"];
checkSession($customerId);

// Log the customer ID for debugging
logActivity("Customer ID found in session: " . $customerId);

// Decode the JSON input from request body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Check if JSON decoding was successful
if (json_last_error() !== JSON_ERROR_NONE) {
    $error_message = "Invalid JSON input.";
    error_log($error_message);
    logActivity($error_message);
    echo json_encode(['success' => false, 'message' => $error_message]);
    exit;
}

// Extract promo code and total order from the received JSON
$promo_code = $data['promo_code'] ?? null;
$total_order = $data['total_order'] ?? null;

// Log the promo code and total order for debugging
logActivity("Promo code received: " . $promo_code);
logActivity("Total order received: " . $total_order);

// Fetch promo details from the database
$stmt = $conn->prepare("SELECT * FROM promo WHERE promo_code = ? AND status = 1 AND NOW() BETWEEN start_date AND end_date");
if (!$stmt) {
    $error_message = "Failed to prepare promo query: " . $conn->error;
    error_log($error_message);
    logActivity($error_message);
    echo json_encode(['success' => false, 'message' => $error_message]);
    exit;
}
$stmt->bind_param("s", $promo_code);
$stmt->execute();
$result = $stmt->get_result();

if ($promo = $result->fetch_assoc()) {
    // Log the promo details for debugging
    logActivity("Promo details fetched: " . json_encode($promo));

    // Extract eligibility criteria from the promo
    $eligibility_criteria = $promo['eligibility_criteria'];
    $min_order_value = $promo['min_order_value'];
    $discount_value = $promo['discount_value'];
    $max_discount = $promo['max_discount'];

    // Check if the total order meets the minimum order value
    if ($min_order_value > $total_order) {
        $error_message = "Your Total Order does not qualify for this promo.";
        error_log($error_message);
        logActivity($error_message);
        echo json_encode(['eligible' => false, 'message' => $error_message, 'promo' => $promo]);
        exit();
    }

    // Check if the customer has already used this promo code today
    $usageStmt = $conn->prepare("SELECT COUNT(*) FROM promo_usage WHERE promo_code = ? AND customer_id = ? AND DATE(date_used) = CURDATE()");
    if (!$usageStmt) {
        $error_message = "Failed to prepare promo usage query: " . $conn->error;
        error_log($error_message);
        logActivity($error_message);
        echo json_encode(['success' => false, 'message' => $error_message]);
        exit;
    }
    $usageStmt->bind_param("ss", $promo_code, $customerId);
    $usageStmt->execute();
    $usageResult = $usageStmt->get_result();
    $usedCount = $usageResult->fetch_row()[0];

    if ($usedCount > 0) {
        $error_message = "You have already used this promo code today.";
        error_log($error_message);
        logActivity($error_message);
        echo json_encode(['eligible' => false, 'message' => $error_message, 'promo' => $promo]);
        $usageStmt->close();
        $stmt->close();
        $conn->close();
        exit;
    }

    // Check eligibility criteria
    if ($eligibility_criteria === "New Customers") {
        // Fetch the customer's record to check registration date
        $customerStmt = $conn->prepare("SELECT date_created FROM customers WHERE customer_id = ?");
        if (!$customerStmt) {
            $error_message = "Failed to prepare customer query: " . $conn->error;
            error_log($error_message);
            logActivity($error_message);
            echo json_encode(['success' => false, 'message' => $error_message]);
            exit;
        }
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
                if ($discount > $max_discount) {
                    $discount = $max_discount;
                }
                logActivity("Customer is eligible for the promo. Discount applied: " . $discount);
                echo json_encode(['eligible' => true, 'promo' => $promo, 'discount' => $discount, 'discount_percent' => $discount_value, 'promo_code' => $promo_code]);
            } else {
                // Customer is not eligible
                $error_message = "Promo is only for new customers registered within the last 5 days.";
                error_log($error_message);
                logActivity($error_message);
                echo json_encode(['eligible' => false, 'message' => $error_message, 'promo' => $promo]);
            }
        } else {
            $error_message = "Customer record not found.";
            error_log($error_message);
            logActivity($error_message);
            echo json_encode(['eligible' => false, 'message' => $error_message, 'promo' => $promo]);
        }

        $customerStmt->close();
    } elseif ($eligibility_criteria === "All Customers") {
        // Customer is eligible as the criteria is for all customers
        $discount = ($discount_value / 100) * $total_order;
        if ($discount > $max_discount) {
            $discount = $max_discount;
        }
        logActivity("Customer is eligible for the promo. Discount applied: " . $discount);
        echo json_encode(['eligible' => true, 'promo' => $promo, 'discount' => $discount, 'discount_percent' => $discount_value, 'promo_code' => $promo_code, 'total_order' => $total_order]);
    } else {
        // Handle any other eligibility criteria if needed
        $error_message = "Customer does not meet eligibility criteria.";
        error_log($error_message);
        logActivity($error_message);
        echo json_encode(['eligible' => false, 'message' => $error_message, 'promo' => $promo]);
    }
    $usageStmt->close();
} else {
    // Promo code is not valid or expired
    $error_message = "Promo not valid or expired.";
    error_log($error_message);
    logActivity($error_message);
    echo json_encode(['eligible' => false, 'message' => $error_message, 'promo' => null]);
}

$stmt->close();
$conn->close();

// Log the end of the script
logActivity("Promo code validation script completed.");
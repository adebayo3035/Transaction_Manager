<?php
session_start();
include 'config.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Start logging
logActivity("=== PROMO VALIDATION REQUEST STARTED ===");

// Validate session exists
if (!isset($_SESSION["customer_id"])) {
    $errorMsg = "Unauthorized access attempt - no customer_id in session";
    logActivity($errorMsg);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$customerId = $_SESSION["customer_id"];
logActivity("Validating promo for customer ID: $customerId");

// Validate input
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $errorMsg = "Invalid JSON input: " . json_last_error_msg();
    logActivity($errorMsg);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

if (!isset($input['promo_code']) || !isset($input['total_order'])) {
    $errorMsg = "Missing required fields in request";
    logActivity($errorMsg);
    echo json_encode(['success' => false, 'message' => 'Promo code and total order required']);
    exit;
}

$promoCode = trim($input['promo_code']);
$totalOrder = (float)$input['total_order'];
logActivity("Validating promo code: $promoCode for order amount: $totalOrder");

// Fetch active promo
$stmt = $conn->prepare("SELECT * FROM promo WHERE promo_code = ? AND delete_id = 0 AND status = 1 AND NOW() BETWEEN start_date AND end_date");
if (!$stmt) {
    $errorMsg = "Prepare failed: " . $conn->error;
    logActivity($errorMsg);
    echo json_encode(['success' => false, 'message' => 'System error']);
    exit;
}

if (!$stmt->bind_param("s", $promoCode) || !$stmt->execute()) {
    $errorMsg = "Execute failed: " . $stmt->error;
    logActivity($errorMsg);
    echo json_encode(['success' => false, 'message' => 'System error']);
    exit;
}

$promo = $stmt->get_result()->fetch_assoc();
if (!$promo) {
    $errorMsg = "Promo code not found or inactive: $promoCode";
    logActivity($errorMsg);
    echo json_encode(['eligible' => false, 'message' => 'Invalid or expired promo code']);
    exit;
}

logActivity("Found valid promo: " . json_encode([
    'id' => $promo['promo_id'],
    'code' => $promo['promo_code'],
    'min_order' => $promo['min_order_value']
]));

// Validate minimum order
if ($totalOrder < $promo['min_order_value']) {
    $errorMsg = "Order amount $totalOrder below minimum required " . $promo['min_order_value'];
    logActivity($errorMsg);
    echo json_encode([
        'eligible' => false, 
        'message' => 'Order must be at least N' . $promo['min_order_value'] . ' to use this promo'
    ]);
    exit;
}

// Check daily usage limit (without recording)
$usageCheck = $conn->prepare("SELECT COUNT(*) FROM promo_usage 
                            WHERE promo_code = ? AND customer_id = ? AND DATE(date_used) = CURDATE()");
if (!$usageCheck) {
    $errorMsg = "Usage check prepare failed: " . $conn->error;
    logActivity($errorMsg);
    echo json_encode(['success' => false, 'message' => 'System error']);
    exit;
}

if (!$usageCheck->bind_param("ss", $promoCode, $customerId) || !$usageCheck->execute()) {
    $errorMsg = "Usage check execute failed: " . $usageCheck->error;
    logActivity($errorMsg);
    echo json_encode(['success' => false, 'message' => 'System error']);
    exit;
}

$usageCount = $usageCheck->get_result()->fetch_row()[0];
logActivity("Current usage count for today: $usageCount");

if ($usageCount > 0) {
    $errorMsg = "Usage limit reached for customer $customerId .";
    logActivity($errorMsg);
    echo json_encode(['eligible' => false, 'message' => 'Promo code usage limit reached']);
    exit;
}

// Check eligibility criteria
$eligible = false;
$criteria = $promo['eligibility_criteria'];
logActivity("Checking eligibility criteria: $criteria");

if ($criteria === "New Customers") {
    $customerCheck = $conn->prepare("SELECT DATEDIFF(NOW(), date_created) <= 5 AS is_new 
                                   FROM customers WHERE customer_id = ?");
    if (!$customerCheck) {
        $errorMsg = "Customer check prepare failed: " . $conn->error;
        logActivity($errorMsg);
        echo json_encode(['success' => false, 'message' => 'System error']);
        exit;
    }
    
    if (!$customerCheck->bind_param("s", $customerId) || !$customerCheck->execute()) {
        $errorMsg = "Customer check execute failed: " . $customerCheck->error;
        logActivity($errorMsg);
        echo json_encode(['success' => false, 'message' => 'System error']);
        exit;
    }
    
    $result = $customerCheck->get_result()->fetch_assoc();
    $eligible = $result['is_new'] ?? false;
    
    logActivity("New customer check result: " . ($eligible ? "Eligible" : "Not eligible"));
} else {
    $eligible = true; // For "All Customers" or other criteria
    logActivity("No special eligibility criteria required");
}

if (!$eligible) {
    $errorMsg = "Customer $customerId not eligible for promo $promoCode";
    logActivity($errorMsg);
    echo json_encode(['eligible' => false, 'message' => 'You are not eligible for this promo']);
    exit;
}

// Calculate potential discount
$discountValue = $promo['discount_value'];
$maxDiscount = $promo['max_discount'];
$discountAmount = min(($discountValue / 100) * $totalOrder, $maxDiscount);

logActivity("Calculated discount: $discountAmount (value: $discountValue%, max: $maxDiscount)");

// Return successful validation response
$response = [
    'eligible' => true,
    'promo_code' => $promoCode,
    'discount' => $discountAmount,
    'discount_percent' => $discountValue,
    'max_discount' => $maxDiscount,
    'min_order' => $promo['min_order_value'],
    'total_order' => $totalOrder,
    'promo_id' => $promo['promo_id']
];

logActivity("Validation successful: " . json_encode($response));
echo json_encode($response);

// Clean up
$stmt->close();
if (isset($usageCheck)) $usageCheck->close();
if (isset($customerCheck)) $customerCheck->close();
$conn->close();

logActivity("=== PROMO VALIDATION COMPLETED ===");
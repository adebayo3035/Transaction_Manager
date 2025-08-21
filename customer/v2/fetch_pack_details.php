<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

// Get order_id from request
// $orderId = isset($_GET['order_id']) ? intval($_GET['order_id']) : null;
$input = json_decode(file_get_contents('php://input'), true);
$orderId = $credit_id = $input['order_id'] ?? null;

if (!$orderId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Order ID is required']);
    exit;
}

try {
    // Validate session
    $customerId = $_SESSION["customer_id"] ?? null;
    $admin_id = $_SESSION["unique_id"] ?? null;

    logActivity("SESSION_VALIDATION: Starting session validation");

    // Fail only if BOTH are missing
    if (!$customerId && !$admin_id) {
        throw new Exception("No Customer ID or Admin ID found in session");
    }

    // checkSession($customerId);
    logActivity("SESSION_VALIDATION: Successfully validated session for User");

    // Decode JSON input with validation
    logActivity("INPUT_PROCESSING: Reading JSON input");
    $input = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $errorMsg = "Invalid JSON input: " . json_last_error_msg();
        logActivity("INPUT_ERROR: $errorMsg");
        throw new Exception($errorMsg);
    }
    logActivity("INPUT_PROCESSING: Successfully decoded JSON input");

    // Fetch packs for this order
    $packStmt = $conn->prepare("
        SELECT pack_id, total_cost, created_at 
        FROM packs 
        WHERE order_id = ?
    ");
    $packStmt->bind_param("i", $orderId);
    $packStmt->execute();
    $packResult = $packStmt->get_result();

    $packs = [];
    while ($pack = $packResult->fetch_assoc()) {
        $packId = $pack['pack_id'];

        // Fetch items for each pack
        $itemStmt = $conn->prepare("
            SELECT 
                pi.food_id, 
                pi.food_name, 
                pi.quantity, 
                pi.price_per_unit, 
                pi.total_price
               
            FROM pack_items pi
            WHERE pi.pack_id = ? AND pi.order_id = ?
        ");
        $itemStmt->bind_param("si", $packId, $orderId);
        $itemStmt->execute();
        $itemResult = $itemStmt->get_result();

        $items = [];
        while ($item = $itemResult->fetch_assoc()) {
            $items[] = [
                'food_id' => $item['food_id'],
                'food_name' => $item['food_name'],
                'quantity' => $item['quantity'],
                'price_per_unit' => $item['price_per_unit'],
                'total_price' => $item['total_price']
                // 'image_url' => $item['image_url'] // Optional food image
            ];
        }

        $packs[$packId] = [
            'pack_info' => $pack,
            'items' => $items
        ];
    }

    if (empty($packs)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'No packs found for this order']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'order_id' => $orderId,
            'packs' => $packs
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
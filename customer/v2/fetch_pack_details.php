<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

// Initialize logging at the very beginning
logActivity("ENDPOINT_ACCESS: Order packs endpoint accessed");

// Get order_id from request
$input = json_decode(file_get_contents('php://input'), true);
$orderId = $input['order_id'] ?? null;

logActivity("REQUEST_DATA: Received order_id: " . ($orderId ? $orderId : 'NULL'));

if (!$orderId) {
    logActivity("VALIDATION_ERROR: Order ID is required but not provided");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Order ID is required']);
    exit;
}

try {
    // Validate session - check all possible user types
    $customerId = $_SESSION["customer_id"] ?? null;
    $admin_id = $_SESSION["unique_id"] ?? null;
    $driverId = $_SESSION['driver_id'] ?? null;
    
    logActivity("SESSION_VALIDATION: Checking session for user types - Customer: " . 
                ($customerId ? $customerId : 'NULL') . 
                ", Admin: " . ($admin_id ? $admin_id : 'NULL') . 
                ", Driver: " . ($driverId ? $driverId : 'NULL'));

    // Fail only if ALL user types are missing
    if (!$customerId && !$admin_id && !$driverId) {
        $errorMsg = "No Customer ID, Admin ID, or Driver ID found in session";
        logActivity("SESSION_ERROR: " . $errorMsg);
        throw new Exception($errorMsg);
    }

    // Determine which user type is accessing and log accordingly
    if ($customerId) {
        logActivity("USER_TYPE: Customer accessing endpoint - ID: " . $customerId);
    } elseif ($admin_id) {
        logActivity("USER_TYPE: Admin accessing endpoint - ID: " . $admin_id);
    } elseif ($driverId) {
        logActivity("USER_TYPE: Driver accessing endpoint - ID: " . $driverId);
    }

    logActivity("SESSION_VALIDATION: Successfully validated session for user");

    // Fetch packs for this order
    logActivity("DB_QUERY: Preparing to fetch packs for order_id: " . $orderId);
    $packStmt = $conn->prepare("
        SELECT pack_id, total_cost, created_at 
        FROM packs 
        WHERE order_id = ?
    ");
    $packStmt->bind_param("i", $orderId);
    $packStmt->execute();
    $packResult = $packStmt->get_result();

    $packs = [];
    $packCount = 0;
    
    logActivity("DB_QUERY: Fetching pack results for order_id: " . $orderId);
    while ($pack = $packResult->fetch_assoc()) {
        $packId = $pack['pack_id'];
        $packCount++;
        logActivity("PACK_PROCESSING: Processing pack_id: " . $packId . " (" . $packCount . " of total packs)");

        // Fetch items for each pack
        logActivity("DB_QUERY: Preparing to fetch items for pack_id: " . $packId);
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
        $itemCount = 0;
        
        logActivity("DB_QUERY: Fetching items for pack_id: " . $packId);
        while ($item = $itemResult->fetch_assoc()) {
            $itemCount++;
            $items[] = [
                'food_id' => $item['food_id'],
                'food_name' => $item['food_name'],
                'quantity' => $item['quantity'],
                'price_per_unit' => $item['price_per_unit'],
                'total_price' => $item['total_price']
            ];
        }
        
        logActivity("ITEM_COUNT: Pack " . $packId . " contains " . $itemCount . " items");

        $packs[$packId] = [
            'pack_info' => $pack,
            'items' => $items
        ];
    }

    if (empty($packs)) {
        logActivity("DATA_NOT_FOUND: No packs found for order_id: " . $orderId);
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'No packs found for this order']);
        exit;
    }

    logActivity("SUCCESS: Successfully retrieved " . $packCount . " packs with items for order_id: " . $orderId);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'order_id' => $orderId,
            'packs' => $packs
        ]
    ]);

} catch (Exception $e) {
    logActivity("EXCEPTION: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

// Log the completion of the request
logActivity("REQUEST_COMPLETE: Endpoint processing completed for order_id: " . $orderId);
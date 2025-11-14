<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

// Initialize logging
logActivity("Order details fetch process started");

try {
    // Check authentication
    if (!isset($_SESSION['unique_id'])) {
        $errorMsg = "Unauthenticated access attempt to order details";
        logActivity($errorMsg);
        echo json_encode(["success" => false, "message" => "Not logged in."]);
        exit();
    }

    $userId = $_SESSION['unique_id'];
    $userRole = $_SESSION['role'] ?? null;
    logActivity("Request initiated by user ID: $userId (Role: $userRole)");

    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] != 'POST') {
        $errorMsg = "Invalid request method: " . $_SERVER['REQUEST_METHOD'];
        logActivity($errorMsg);
        echo json_encode(["success" => false, "message" => "Only POST requests are allowed"]);
        exit();
    }

    // Get and validate input
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON input: " . json_last_error_msg());
    }

    if (!isset($input['order_id'])) {
        $errorMsg = "Missing order_id in request data";
        logActivity($errorMsg);
        echo json_encode(['success' => false, 'message' => 'Order ID is required']);
        exit();
    }

    $orderId = filter_var($input['order_id'], FILTER_VALIDATE_INT);
    if ($orderId === false || $orderId < 1) {
        $errorMsg = "Invalid order_id format: " . ($input['order_id'] ?? 'null');
        logActivity($errorMsg);
        echo json_encode(['success' => false, 'message' => 'Invalid Order ID format']);
        exit();
    }

    logActivity("Processing order details for ID: " . $orderId);

    // Prepare comprehensive order details query with optimized structure
    $query = "
    SELECT 
        o.order_id,
        o.customer_id,
        o.service_fee,
        o.delivery_fee,
        o.total_order,
        o.pack_count,
        o.total_amount,
        o.delivery_pin,
        o.delivery_status,
        o.order_date,
        o.updated_at,
        o.cancellation_reason,
        o.reported,
        c.firstname AS customer_firstname,
        c.lastname AS customer_lastname,
        c.mobile_number AS customer_phone,
        c.address AS customer_address,
        d.firstname AS driver_firstname, 
        d.lastname AS driver_lastname,
        d.id AS driver_id,
        a1.firstname AS assigned_admin_firstname, 
        a1.lastname AS assigned_admin_lastname,
        a2.firstname AS approver_firstname,
        a2.lastname AS approver_lastname,
        COALESCE(r.retained_amount, 0.00) AS retained_amount,
        COALESCE(r.refunded_amount, 0.00) AS refunded_amount,
        pu.discount_value,
        pu.promo_code,
        pu.percentage_discount,
        od.food_id, 
        od.quantity, 
        od.price_per_unit, 
        od.total_price, 
        od.status as item_status,
        f.food_name
    FROM orders o
    LEFT JOIN order_details od ON o.order_id = od.order_id
    LEFT JOIN food f ON od.food_id = f.food_id
    LEFT JOIN customers c ON o.customer_id = c.customer_id
    LEFT JOIN driver d ON o.driver_id = d.id
    LEFT JOIN admin_tbl a1 ON o.assigned_to = a1.unique_id
    LEFT JOIN admin_tbl a2 ON o.approved_by = a2.unique_id
    LEFT JOIN promo_usage pu ON o.order_id = pu.order_id
    LEFT JOIN revenue r ON o.order_id = r.order_id
    WHERE o.order_id = ?
    ";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed for order details: " . $conn->error);
    }

    $stmt->bind_param("i", $orderId);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed for order details: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $items = [];
    $orderInfo = null;

    while ($row = $result->fetch_assoc()) {
        // Extract order-level information once
        if ($orderInfo === null) {
            $orderInfo = [
                'order_id' => $row['order_id'],
                'customer_id' => $row['customer_id'],
                'service_fee' => number_format($row['service_fee'], 2),
                'delivery_fee' => number_format($row['delivery_fee'], 2),
                'total_order' => number_format($row['total_order'], 2),
                'pack_count' => $row['pack_count'],
                'total_amount' => number_format($row['total_amount'], 2),
                'delivery_pin' => $row['delivery_pin'],
                'delivery_status' => $row['delivery_status'],
                'order_date' => $row['order_date'],
                'updated_at' => $row['updated_at'],
                'cancellation_reason' => $row['cancellation_reason'],
                'reported' => $row['reported'],
                'customer' => [
                    'firstname' => $row['customer_firstname'],
                    'lastname' => $row['customer_lastname'],
                    'phone' => $row['customer_phone'],
                    'address' => $row['customer_address']
                ],
                'driver' => $row['driver_id'] ? [
                    'id' => $row['driver_id'],
                    'firstname' => $row['driver_firstname'],
                    'lastname' => $row['driver_lastname']
                ] : null,
                'assigned_admin' => $row['assigned_admin_firstname'] ? [
                    'firstname' => $row['assigned_admin_firstname'],
                    'lastname' => $row['assigned_admin_lastname']
                ] : null,
                'approver' => $row['approver_firstname'] ? [
                    'firstname' => $row['approver_firstname'],
                    'lastname' => $row['approver_lastname']
                ] : null,
                'revenue' => [
                    'retained_amount' => number_format($row['retained_amount'], 2),
                    'refunded_amount' => number_format($row['refunded_amount'], 2)
                ],
                'promotion' => $row['promo_code'] ? [
                    'promo_code' => $row['promo_code'],
                    'discount_value' => number_format($row['discount_value'], 2),
                    'percentage_discount' => $row['percentage_discount']
                ] : null
            ];
        }
        
        // Collect item details (only if food_id exists)
        if ($row['food_id']) {
            $items[] = [
                'food_id' => $row['food_id'],
                'food_name' => $row['food_name'],
                'quantity' => (int)$row['quantity'],
                'price_per_unit' => number_format($row['price_per_unit'], 2),
                'total_price' => number_format($row['total_price'], 2),
                'item_status' => $row['item_status']
            ];
        }
    }

    $stmt->close();

    if ($orderInfo === null) {
        logActivity("No order found with ID: " . $orderId);
        echo json_encode([
            'success' => false, 
            'message' => 'Order not found',
            'order_id' => $orderId
        ]);
        exit();
    }

    logActivity("Successfully retrieved order details for ID: " . $orderId);

    // Prepare optimized response
    $response = [
        'success' => true,
        'order' => $orderInfo,
        'items' => $items,
        'count' => count($items),
        'requested_by' => $userId,
        'user_role' => $userRole,
        'timestamp' => date('c')
    ];

    echo json_encode($response);
    logActivity("Order details fetch completed successfully");

} catch (Exception $e) {
    $errorMsg = "Error fetching order details: " . $e->getMessage();
    logActivity($errorMsg);
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to fetch order details',
        'error' => $e->getMessage(),
        'order_id' => $orderId ?? null
    ]);
} finally {
    // Clean up resources
    if (isset($conn)) {
        $conn->close();
    }
    logActivity("Order details fetch process completed");
}
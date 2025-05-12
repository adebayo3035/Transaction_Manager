<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

// Initialize variables for cleanup
$stmt = null;


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

    try {
        // Start transaction for consistent data view
        $conn->begin_transaction();
        logActivity("Database transaction started");

        // Prepare comprehensive order details query
        $query = "
        SELECT 
            od.food_id, 
            od.quantity, 
            od.price_per_unit, 
            od.total_price, 
            f.food_name, 
            od.order_date, 
            od.status,
            o.order_id,
            o.customer_id,
            o.service_fee,
            o.delivery_fee,
            o.total_order,
            o.total_amount,
            o.delivery_pin,
            o.delivery_status,
            o.updated_at,
            o.cancellation_reason,
            d.firstname AS driver_firstname, 
            d.lastname AS driver_lastname,
            c.firstname AS customer_firstname,
            c.lastname AS customer_lastname,
            c.mobile_number AS customer_phone_number,
            a1.firstname AS assigned_admin_firstname, 
            a1.lastname AS assigned_admin_lastname,
            a2.firstname AS approver_firstname,
            a2.lastname AS approver_lastname,
            COALESCE(r.retained_amount, 0.00) AS retained_amount,
            COALESCE(r.refunded_amount, 0.00) AS refunded_amount,
            pu.discount_value,
            pu.promo_code,
            pu.percentage_discount
        FROM order_details od
        JOIN food f ON od.food_id = f.food_id
        JOIN orders o ON o.order_id = od.order_id
        LEFT JOIN driver d ON o.driver_id = d.id
        LEFT JOIN customers c ON o.customer_id = c.customer_id
        LEFT JOIN admin_tbl a1 ON o.assigned_to = a1.unique_id
        LEFT JOIN admin_tbl a2 ON o.approved_by = a2.unique_id
        LEFT JOIN promo_usage pu ON o.order_id = pu.order_id
        LEFT JOIN revenue r ON o.order_id = r.order_id
        WHERE od.order_id = ?
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
        $orderDetails = [];

        while ($row = $result->fetch_assoc()) {
            // Format numeric values
            $row['price_per_unit'] = number_format($row['price_per_unit'], 2);
            $row['total_price'] = number_format($row['total_price'], 2);
            $row['service_fee'] = number_format($row['service_fee'], 2);
            $row['delivery_fee'] = number_format($row['delivery_fee'], 2);
            $row['total_order'] = number_format($row['total_order'], 2);
            $row['total_amount'] = number_format($row['total_amount'], 2);
            $row['retained_amount'] = number_format($row['retained_amount'], 2);
            $row['refunded_amount'] = number_format($row['refunded_amount'], 2);
            $row['discount_value'] = number_format($row['discount_value'], 2);
            
            $orderDetails[] = $row;
        }

        if (empty($orderDetails)) {
            logActivity("No order found with ID: " . $orderId);
            echo json_encode([
                'success' => false, 
                'message' => 'Order not found',
                'order_id' => $orderId
            ]);
            exit();
        }

        $conn->commit();
        logActivity("Successfully retrieved order details for ID: " . $orderId);

        // Prepare response
        $response = [
            'success' => true,
            'order_details' => $orderDetails,
            'count' => count($orderDetails),
            'requested_by' => $userId,
            'user_role' => $userRole,
            'timestamp' => date('c')
        ];

        echo json_encode($response);
        logActivity("Order details fetch completed successfully");

    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
        }
        throw $e;
    }
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
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
    logActivity("Order details fetch process completed");
}
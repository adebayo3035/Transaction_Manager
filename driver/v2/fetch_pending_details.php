<?php
session_start();
include 'config.php';
$driverId = $_SESSION['driver_id'];
checkDriverSession($driverId);
logActivity("Session validated successfully for Driver ID: $driverId.");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    logActivity("Received a POST request.");
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['order_id'])) {
        logActivity("Order ID is missing in the request.");
        echo json_encode(["success" => false, "message" => "Order ID is missing."]);
        exit();
    }
    
    $orderId = $input['order_id'];
    logActivity("Processing Pending Order Details for order ID: $orderId for driver ID: $driverId.");

    $query = "
SELECT 
    od.food_id, 
    od.quantity, 
    f.food_name, 
    od.order_date, 
    od.status as item_status,
    o.updated_at,
    o.delivery_fee,
    o.delivery_status,
    o.order_id,
    o.pack_count,
    d.firstname AS driver_firstname, 
    d.lastname AS driver_lastname,
    c.firstname AS customer_firstname,
    c.lastname AS customer_lastname,
    c.mobile_number AS customer_phone,
    c.address AS customer_address
FROM orders o
JOIN order_details od ON o.order_id = od.order_id
JOIN food f ON od.food_id = f.food_id
LEFT JOIN driver d ON o.driver_id = d.id
LEFT JOIN customers c ON o.customer_id = c.customer_id
WHERE o.order_id = ? 
AND o.driver_id = ?";

    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        logActivity("Failed to prepare the SQL statement.");
        echo json_encode(["success" => false, "message" => "Database error."]);
        exit();
    }

    $stmt->bind_param("ii", $orderId, $driverId);
    if (!$stmt->execute()) {
        logActivity("Failed to execute the SQL statement.");
        echo json_encode(["success" => false, "message" => "Database error."]);
        exit();
    }

    $result = $stmt->get_result();
    $orderItems = [];
    $orderInfo = null;
    
    while ($row = $result->fetch_assoc()) {
        // Extract order-level information (same for all items) on first iteration
        if ($orderInfo === null) {
            $orderInfo = [
                'order_id' => $row['order_id'],
                'pack_count' => $row['pack_count'],
                'order_date' => $row['order_date'],
                'updated_at' => $row['updated_at'],
                'delivery_fee' => ((float)$row['delivery_fee']),
                'delivery_status' => $row['delivery_status'],
                'customer' => [
                    'firstname' => $row['customer_firstname'],
                    'lastname' => $row['customer_lastname'],
                    'phone' => $row['customer_phone'],
                    'address' => $row['customer_address'] ?? null
                ],
                'driver' => [
                    'firstname' => $row['driver_firstname'],
                    'lastname' => $row['driver_lastname']
                ]
            ];
        }
        
        // Extract item-specific information
        $orderItems[] = [
            'food_id' => $row['food_id'],
            'food_name' => $row['food_name'],
            'quantity' => (int)$row['quantity'],
            'item_status' => $row['item_status']
        ];
    }
    
    $stmt->close();

    if ($orderInfo === null) {
        logActivity("No order details found for order ID: $orderId");
        echo json_encode([
            "success" => false, 
            "message" => "Order not found or you don't have permission to view it."
        ]);
        exit();
    }

    logActivity("Retrieved order details for order ID: $orderId with " . count($orderItems) . " items");
    
    $response = [
        "success" => true,
        "order" => $orderInfo,
        "items" => $orderItems
    ];
    
    echo json_encode($response);
    
} else {
    logActivity("Invalid request method received.");
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
}
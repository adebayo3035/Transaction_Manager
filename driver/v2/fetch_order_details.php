<?php
include 'config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    logActivity("Invalid request method received.");
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['order_id'])) {
    logActivity("Order ID is missing in the request.");
    echo json_encode(["success" => false, "message" => "Order ID is missing in the request Body."]);
    exit();
}

$orderId = $input['order_id'];
$driverId = $_SESSION['driver_id'] ?? null;

if (!$driverId) {
    logActivity("Driver session not found.");
    echo json_encode(["success" => false, "message" => "Authentication required."]);
    exit();
}

checkDriverSession($driverId);
logActivity("Session validated successfully for Driver ID: $driverId.");

logActivity("About to retrieve Order Details for order ID: $orderId for driver ID: $driverId.");

$query = "
SELECT 
    o.order_id,
    o.order_date,
    o.updated_at,
    o.pack_count,
    o.delivery_fee,
    o.delivery_status,
    c.firstname AS customer_firstname,
    c.lastname AS customer_lastname,
    c.mobile_number AS customer_phone,
    c.address AS customer_address,
    d.firstname AS driver_firstname, 
    d.lastname AS driver_lastname,
    od.food_id, 
    od.quantity, 
    f.food_name, 
    od.status as item_status
FROM orders o
JOIN order_details od ON o.order_id = od.order_id
JOIN food f ON od.food_id = f.food_id
LEFT JOIN customers c ON o.customer_id = c.customer_id
LEFT JOIN driver d ON o.driver_id = d.id
WHERE o.order_id = ? 
AND o.driver_id = ?";

$stmt = $conn->prepare($query);
if ($stmt === false) {
    $error = $conn->error;
    logActivity("Failed to prepare SQL statement for Order ID: $orderId. Error: $error");
    echo json_encode(["success" => false, "message" => "Database preparation error."]);
    exit();
}

if (!$stmt->bind_param("ii", $orderId, $driverId)) {
    logActivity("Failed to bind parameters for Order ID: $orderId");
    echo json_encode(["success" => false, "message" => "Database binding error."]);
    exit();
}

if (!$stmt->execute()) {
    $error = $stmt->error;
    logActivity("Failed to execute SQL statement for Order ID: $orderId. Error: $error");
    echo json_encode(["success" => false, "message" => "Database execution error."]);
    exit();
}

$result = $stmt->get_result();
$items = [];
$orderInfo = null;

while ($row = $result->fetch_assoc()) {
    // Extract order-level information once
    if ($orderInfo === null) {
        $orderInfo = [
            'order_id' => $row['order_id'],
            'order_date' => $row['order_date'],
            'updated_at' => $row['updated_at'],
            'pack_count' => (int)$row['pack_count'],
            'delivery_fee' => (float)$row['delivery_fee'],
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
    
    // Collect item details
    $items[] = [
        'food_id' => $row['food_id'],
        'food_name' => $row['food_name'],
        'quantity' => (int)$row['quantity'],
        'item_status' => $row['item_status']
    ];
}

$stmt->close();

if ($orderInfo === null) {
    logActivity("No order found for Order ID: $orderId with Driver ID: $driverId");
    echo json_encode([
        "success" => false, 
        "message" => "Order not found or you don't have permission to view it."
    ]);
    exit();
}

logActivity("Successfully retrieved order details for order ID: $orderId with " . count($items) . " items");

echo json_encode([
    "success" => true, 
    "order" => $orderInfo,
    "items" => $items
]);
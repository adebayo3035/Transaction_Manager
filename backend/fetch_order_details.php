<?php
include 'config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['order_id'])) {
        echo json_encode(["success" => false, "message" => "Order ID is missing."]);
        exit();
    }

    $orderId = $input['order_id'];
    // $customerId = $_SESSION['customer_id'];

    // $stmt = $conn->prepare("SELECT * FROM order_details WHERE order_id = ?");
    $query = "
    SELECT 
    order_details.food_id, 
    order_details.quantity, 
    order_details.price_per_unit, 
    order_details.total_price, 
    food.food_name, 
    order_details.order_date, 
    order_details.status,
    orders.order_id,
    orders.customer_id,
    orders.service_fee,
    orders.delivery_fee,
    orders.total_order,
    orders.total_amount,
    orders.delivery_pin,
    orders.delivery_status,
    orders.updated_at,
    driver.firstname AS driver_firstname, 
    driver.lastname AS driver_lastname,
    customers.firstname AS customer_firstname,
    customers.lastname AS customer_lastname,
    customers.mobile_number AS customer_phone_number,
    admin_tbl.firstname AS assigned_admin_firstname, 
    admin_tbl.lastname AS assigned_admin_lastname,
    admin_approver.firstname AS approver_firstname,
    admin_approver.lastname AS approver_lastname
FROM order_details
JOIN food ON order_details.food_id = food.food_id
JOIN orders ON orders.order_id = order_details.order_id
LEFT JOIN driver ON orders.driver_id = driver.id
LEFT JOIN customers ON orders.customer_id = customers.customer_id
LEFT JOIN admin_tbl ON orders.assigned_to = admin_tbl.unique_id
LEFT JOIN admin_tbl AS admin_approver ON orders.approved_by = admin_approver.unique_id
WHERE order_details.order_id = ?

    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $result = $stmt->get_result();

    $orderDetails = [];
    while ($row = $result->fetch_assoc()) {
        $orderDetails[] = $row;
    }
    $stmt->close();

    echo json_encode(["success" => true, "order_details" => $orderDetails]);
} else {
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
}

<?php
include('config.php');

$data = json_decode(file_get_contents("php://input"), true);

$orderId = $data['order_id'];
$driverId = $data['driver_id'];

if ($orderId == "" || null) {
    echo json_encode(['success' => false, 'message' => 'Invalid Order ID']);
    exit();
}

// check the current delivery status of the order before re assigning the order
$stmt = $conn->prepare("SELECT delivery_status FROM orders WHERE order_id = ? ");
$stmt->bind_param("i", $orderId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $delivery_status = $row['delivery_status']; // Get Delivery Status of specified Order
    if ($delivery_status !== 'Assigned') {
        echo json_encode(['success' => false, 'message' => 'This Order cannot be reassigned to another Driver']);
        exit();
    }
} else {
    echo json_encode(['success' => false, 'message' => 'This Order does not exist. Try again Later']);
    exit();
}

// check the current  status of the driver before re assigning the order
$stmt = $conn->prepare("SELECT status FROM driver WHERE id = ? ");
$stmt->bind_param("i", $driverId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $driver_status = $row['status']; // Get Delivery Status of specified Order
    if ($driver_status !== 'Available') {
        echo json_encode(['success' => false, 'message' => 'This Driver is currently not Available']);
        exit();
    }
} else {
    echo json_encode(['success' => false, 'message' => 'This Driver does not exist. Try again Later']);
    exit();
}

// Update the order with the new driver
$query = "UPDATE orders SET driver_id = ? WHERE order_id = ?";
$updateDriverStatusQuery = "UPDATE driver SET status = 'Not Available' WHERE id = ?";

$stmt1 = $conn->prepare($query);
$stmt1->bind_param('ii', $driverId, $orderId);

$stmt2 = $conn->prepare($updateDriverStatusQuery);
$stmt2->bind_param('i', $driverId);

if ($stmt1->execute() && $stmt2->execute()) {
    echo json_encode(['success' => true, 'message' => 'Order has been successfully reassigned to another User']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to reassign order']);
}
$stmt->close();
$stmt1->close();
$stmt2->close();
$conn->close();

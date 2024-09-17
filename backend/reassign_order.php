<?php
include('config.php');

$data = json_decode(file_get_contents("php://input"), true);

$orderId = $data['order_id'];
$driverId = $data['driver_id'];

if($orderId == "" || null){
    echo json_encode(['success' => false, 'message' => 'Invalid Order ID']);
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

$stmt1->close();
$stmt2->close();
$conn->close();

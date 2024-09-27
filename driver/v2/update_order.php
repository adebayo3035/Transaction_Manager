<?php
// Include database connection
include('config.php');
session_start();

// Get the JSON data from the request body
$data = json_decode(file_get_contents("php://input"), true);

// Validate required inputs
if (!isset($data['id'], $data['currentStatus'], $data['orderStatus'])) {
    echo json_encode(["success" => false, "message" => "Invalid order - Order details missing."]);
    exit();
}

$orderId = $data['id'];
$current_status = $data['currentStatus'];
$orderStatus = $data['orderStatus'];
$driver_id = $_SESSION['driver_id'];

// Invalid status transitions check
$invalidTransitions = [
    ["from" => "Assigned", "to" => "Delivered"],
    ["from" => "Assigned", "to" => "Cancelled"],
    ["from" => "In Transit", "to" => "In Transit"]
];

foreach ($invalidTransitions as $transition) {
    if ($current_status === $transition['from'] && $orderStatus === $transition['to']) {
        echo json_encode(["success" => false, "message" => "Invalid Order Status - From {$transition['from']} to {$transition['to']}"]);
        exit();
    }
}

// Validate the delivery pin if status is Delivered or Cancelled
if (($orderStatus === "Delivered" || $orderStatus === "Cancelled") && isset($data['deliveryPin'])) {
    $deliveryPin = $data['deliveryPin'];
    $sql = "SELECT delivery_pin FROM orders WHERE order_id = ? AND driver_id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ii", $orderId, $driver_id);
        $stmt->execute();
        $stmt->bind_result($storedPin);
        $stmt->fetch();
        $stmt->close();

        if ($storedPin !== $deliveryPin) {
            echo json_encode(["success" => false, "message" => "Invalid delivery pin. Please try again."]);
            exit();
        }
    } else {
        echo json_encode(["success" => false, "message" => "Prepare failed: " . $conn->error]);
        exit();
    }
}

// Prepare to update the order status in the orders table
$cancellationReason = null;
$updateSql = "";

// Handle different order statuses
if ($orderStatus === "Cancelled") {
    if (!isset($data['cancelReason']) || empty($data['cancelReason'])) {
        echo json_encode(["success" => false, "message" => "Cancellation reason is required for a Cancelled order."]);
        exit();
    }
    $cancellationReason = $data['cancelReason'];
    $updateSql = "UPDATE orders SET cancellation_reason = ?, delivery_status = 'Cancelled' WHERE order_id = ? AND driver_id = ?";
} elseif ($orderStatus === "Delivered") {
    $updateSql = "UPDATE orders SET delivery_status = 'Delivered' WHERE order_id = ? AND driver_id = ?";
} elseif ($orderStatus === "In Transit") {
    $updateSql = "UPDATE orders SET delivery_status = 'In Transit' WHERE order_id = ? AND driver_id = ?";
}

// Execute the update for the order status
if ($stmt = $conn->prepare($updateSql)) {
    if ($cancellationReason) {
        $stmt->bind_param("sii", $cancellationReason, $orderId, $driver_id);
    } else {
        $stmt->bind_param("ii", $orderId, $driver_id);
    }

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(["success" => true, "message" => "Order status has been successfully updated."]);
    } else {
        echo json_encode(["success" => false, "message" => "No rows were updated. Please check the order ID and driver ID."]);
    }
    $stmt->close();
} else {
    echo json_encode(["success" => false, "message" => "Failed to update order status: " . $stmt->error]);
    exit();
}

// Update driver status to 'Available' if Delivered or Cancelled
if ($orderStatus === "Delivered" || $orderStatus === "Cancelled") {
    $sql = "UPDATE driver SET status = 'Available' WHERE id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $driver_id);
        $stmt->execute();
        $stmt->close();
    } else {
        echo json_encode(["success" => false, "message" => "Prepare failed: " . $conn->error]);
        exit();
    }
}

// Close the database connection
$conn->close();

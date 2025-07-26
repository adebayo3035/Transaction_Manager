<?php
include('config.php');

// Fetch and decode JSON input
$data = json_decode(file_get_contents("php://input"), true);

// Validate input
$orderId = isset($data['order_id']) ? intval($data['order_id']) : 0;
$driverId = isset($data['driver_id']) ? intval($data['driver_id']) : 0;

if ($orderId <= 0) {
    logActivity("Invalid Order ID provided: $orderId");
    echo json_encode(['success' => false, 'message' => 'Invalid Order ID']);
    exit();
}

if ($driverId <= 0) {
    logActivity("Invalid Driver ID provided: $driverId");
    echo json_encode(['success' => false, 'message' => 'Invalid Driver ID']);
    exit();
}

// Step 1: Fetch current order status and assigned driver
$stmt = $conn->prepare("SELECT delivery_status, driver_id FROM orders WHERE order_id = ?");
$stmt->bind_param("i", $orderId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    logActivity("Order ID $orderId does not exist.");
    echo json_encode(['success' => false, 'message' => 'This Order does not exist. Try again later.']);
    $stmt->close();
    exit();
}

$row = $result->fetch_assoc();
$delivery_status = $row['delivery_status'];
$currentDriverId = $row['driver_id'];
logActivity("Order ID $orderId found with delivery status: $delivery_status and current driver ID: $currentDriverId");

if ($delivery_status !== 'Assigned') {
    logActivity("Order ID $orderId cannot be reassigned. Current status: $delivery_status");
    echo json_encode(['success' => false, 'message' => 'This Order cannot be reassigned to another Driver']);
    $stmt->close();
    exit();
}
$stmt->close();

// Step 2: Check if the new driver is available
$stmt = $conn->prepare("SELECT status FROM driver WHERE id = ?");
$stmt->bind_param("i", $driverId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    logActivity("Driver ID $driverId does not exist.");
    echo json_encode(['success' => false, 'message' => 'This Driver does not exist. Try again later.']);
    $stmt->close();
    exit();
}

$row = $result->fetch_assoc();
$driver_status = $row['status'];
logActivity("Driver ID $driverId found with status: $driver_status");

if ($driver_status !== 'Available') {
    logActivity("Driver ID $driverId is not available for assignment.");
    echo json_encode(['success' => false, 'message' => 'This Driver is currently not Available']);
    $stmt->close();
    exit();
}
$stmt->close();

// Step 3: Perform the reassignment and update driver statuses
$conn->begin_transaction();

try {
    // Reassign order to new driver
    $stmt1 = $conn->prepare("UPDATE orders SET driver_id = ? WHERE order_id = ?");
    $stmt1->bind_param('ii', $driverId, $orderId);
    $stmt1->execute();
    logActivity("Updated Order ID $orderId with new Driver ID $driverId.");

    // Set new driver's status to 'Not Available'
    $stmt2 = $conn->prepare("UPDATE driver SET status = 'Not Available' WHERE id = ?");
    $stmt2->bind_param('i', $driverId);
    $stmt2->execute();
    logActivity("Updated Driver ID $driverId status to 'Not Available'.");

    // Set previous driver's status back to 'Available'
    if ($currentDriverId !== $driverId) {
        $stmt3 = $conn->prepare("UPDATE driver SET status = 'Available' WHERE id = ?");
        $stmt3->bind_param('i', $currentDriverId);
        $stmt3->execute();
        logActivity("Set previous Driver ID $currentDriverId status back to 'Available'.");
        $stmt3->close();
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Order has been successfully reassigned to another Driver']);
} catch (Exception $e) {
    $conn->rollback();
    logActivity("Failed to reassign Order ID $orderId. Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to reassign order']);
} finally {
    if (isset($stmt1)) $stmt1->close();
    if (isset($stmt2)) $stmt2->close();
    $conn->close();
}

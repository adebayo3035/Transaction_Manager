<?php
header('Content-Type: application/json');
include 'config.php';
// include '../../secrets.php';
// require '../../vendor/autoload.php';
include '../../backend/sendOTPGmail.php';
session_start();

$driverId = $_SESSION['driver_id'];
checkDriverSession($driverId);
logActivity("Session validated successfully for Driver ID: $driverId.");

try {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data) {
        throw new Exception("Invalid request payload.");
    }

    $order_id = $data['order_id'] ?? null;
    $customer_id = $data['customer_id'] ?? null;
    $driver_id = $data['driver_id'] ?? null;
    $action = trim($data['action'] ?? '');
    $reported_by = $_SESSION['driver_id'] ?? 'System';
    $created_at = date('Y-m-d H:i:s');

    // 🔍 Validate inputs
    if (!$order_id || !$customer_id || !$driver_id || !$action) {
        throw new Exception("All fields are required.");
    }

    if (str_word_count($action) > 50) {
        throw new Exception("Action must not exceed 50 words.");
    }

    $conn->begin_transaction();
    logActivity("Report order request received for Order ID: $order_id by Driver: $reported_by");

    // ✅ Verify order existence
    $check = $conn->prepare("
        SELECT order_id, delivery_status, assigned_to, approved_by 
        FROM orders 
        WHERE order_id = ? AND customer_id = ? AND driver_id = ?
    ");
    $check->bind_param("iii", $order_id, $customer_id, $driver_id);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("No matching order found for this driver and customer.");
    }

    $order = $result->fetch_assoc();

    if ($order['delivery_status'] !== 'In Transit') {
        throw new Exception("Only orders that are 'In Transit' can be reported.");
    }

    // ✅ Insert report record
    $insert = $conn->prepare("
        INSERT INTO order_reports (order_id, customer_id, driver_id, action_taken, reported_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $insert->bind_param("iiisss", $order_id, $customer_id, $driver_id, $action, $reported_by, $created_at);
    $insert->execute();

    // ✅ Update order status
    $update = $conn->prepare("UPDATE orders SET reported = 1 WHERE order_id = ?");
    $update->bind_param("i", $order_id);
    $update->execute();

    logActivity("Order ID $order_id marked as reported and inserted into order_reports.");

    // ✅ Fetch admin emails
    $emails = [];

    if (!empty($order['assigned_to'])) {
        $fetch = $conn->prepare("SELECT email, firstname, lastname FROM admin_tbl WHERE unique_id = ?");
        $fetch->bind_param("i", $order['assigned_to']);
        $fetch->execute();
        $res = $fetch->get_result();
        if ($row = $res->fetch_assoc()) {
            $emails[$order['assigned_to']] = $row;
        }
        $fetch->close();
    }

    if (!empty($order['approved_by']) && $order['approved_by'] != $order['assigned_to']) {
        $fetch = $conn->prepare("SELECT email, firstname, lastname FROM admin_tbl WHERE unique_id = ?");
        $fetch->bind_param("i", $order['approved_by']);
        $fetch->execute();
        $res = $fetch->get_result();
        if ($row = $res->fetch_assoc()) {
            $emails[$order['approved_by']] = $row;
        }
        $fetch->close();
    }

    // ✅ Prepare email details
    $subject = "🚨 Order Report Alert - Order #{$order_id}";
    $body = "
        <h3>Order Report Notification</h3>
        <p>An order has been reported and requires your attention.</p>
        <ul>
            <li><strong>Order ID:</strong> {$order_id}</li>
            <li><strong>Driver ID:</strong> {$driver_id}</li>
            <li><strong>Customer ID:</strong> {$customer_id}</li>
            <li><strong>Action Taken:</strong> {$action}</li>
            <li><strong>Reported By:</strong> {$reported_by}</li>
            <li><strong>Reported On:</strong> {$created_at}</li>
        </ul>
        <p>Please log in to the Transaction Manager dashboard for more details.</p>
    ";

    // ✅ Send emails
    $emailSent = true;
    foreach ($emails as $admin) {
        if (!sendEmailWithGmailSMTP($admin['email'], $body, $subject)) {
            $emailSent = false;
        } else {
            logActivity("Email sent to {$admin['email']} for Order ID $order_id");
        }
    }

    // ✅ Commit transaction
    $conn->commit();
    logActivity("Report Order transaction committed successfully for Order ID $order_id.");

    echo json_encode([
        "success" => true,
        "message" => "Order reported successfully" . ($emailSent ? " and notifications sent." : ", but email sending failed."),
    ]);

} catch (Exception $e) {
    $conn->rollback();
    logActivity("Report Order failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage(),
    ]);
}

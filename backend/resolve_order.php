<?php
header('Content-Type: application/json');
include 'config.php';
include '../secrets.php';
include 'sendOTPGmail.php';
session_start();

// Initialize variables
$stmt = null;
$update = null;

try {
    // 🔒 Authentication Check
    if (!isset($_SESSION['unique_id'])) {
        logActivity("⚠️ Unauthenticated access attempt to resolve_order endpoint.");
        echo json_encode(["success" => false, "message" => "Not logged in."]);
        exit();
    }

    $config = include __DIR__ . '/../secrets.php';
    $userId = $_SESSION['unique_id'];
    $userRole = $_SESSION['role'] ?? 'Unknown';
    logActivity("✅ Order resolution initiated by User ID: $userId (Role: $userRole)");

    // 📨 Parse JSON Request
    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data) {
        throw new Exception("Invalid request payload. Expecting JSON.");
    }

    // 📋 Extract and validate input
    $order_id = $data['order_id'] ?? null;
    $reported_value = trim($data['resolution'] ?? '');
    $resolution_note = trim($data['resolutionNote'] ?? '');
    $resolved_by = $userId ?? 'System';

    logActivity("🧾 Payload received: Order ID = $order_id, Resolution = '$reported_value', Note = '$resolution_note'");

    if (!$order_id || !$reported_value) {
        throw new Exception("Order ID and resolution value are required.");
    }

    // Validate resolution value
    $allowed_resolutions = ['Cancelled', 'Resolved'];
    if (!in_array($reported_value, $allowed_resolutions)) {
        throw new Exception("Invalid resolution value '$reported_value'. Only 'Cancelled' or 'Resolved' are supported.");
    }

    // 🚦 Begin DB transaction
    $conn->begin_transaction();
    logActivity("🔄 Transaction started for resolving Order ID: $order_id");

    // ✅ Verify the order exists and is reported
    $stmt = $conn->prepare("SELECT customer_id, driver_id, order_id, reported, delivery_status, is_resolved FROM orders WHERE order_id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("No order found with the provided ID ($order_id).");
    }

    $order = $result->fetch_assoc();
    $stmt->close(); // Close the statement

    logActivity("📦 Order fetched successfully for ID: $order_id (Status: {$order['delivery_status']}, Reported: {$order['reported']})");

    if ((int) $order['reported'] !== 1) {
        throw new Exception("Order ID $order_id has not been reported and cannot be resolved.");
    }
    if (((int) $order['is_resolved'] == 1) && ((int) $order['reported'] !== 0)) {
        throw new Exception("Order ID $order_id has been Resolved Already.");
    }

    // 📝 Update resolution fields in order_reports
    $update = $conn->prepare("
        UPDATE order_reports 
        SET resolution_action = ?, 
            resolution_note = ?, 
            resolved_by = ?, 
            resolved_at = NOW() 
        WHERE order_id = ?
    ");
    $update->bind_param("sssi", $reported_value, $resolution_note, $resolved_by, $order_id);

    if (!$update->execute()) {
        throw new Exception("Failed to update order_reports: " . $update->error);
    }

    $update->close();
    logActivity("✅ Order ID: $order_id successfully resolved by User: $resolved_by");
    logActivity("🧾 Resolution details — Action: $reported_value | Note: $resolution_note | Timestamp: " . date('Y-m-d H:i:s'));

    // 🧠 Determine resolution action
    if (strcasecmp($reported_value, 'Cancelled') === 0) {
        // Call external endpoint to update order status
        $endpoint = $config['update_order_endpoint'] ?? '';
        if (empty($endpoint)) {
            throw new Exception("Update order endpoint not configured.");
        }

        $payload = json_encode(["order_id" => $order_id, "status" => "Cancelled"]);
        logActivity("📡 Sending cancellation update to endpoint: $endpoint | Payload: $payload");

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-User-ID: ' . $_SESSION['unique_id'],
                'X-User-Role: ' . ($_SESSION['role'] ?? 'Unknown')
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        logActivity("🌐 update_order_status Response: HTTP $httpCode | Body: $response" . ($curlError ? " | Error: $curlError" : ""));

        if ($httpCode !== 200) {
            throw new Exception("Failed to update order status via update_order_status endpoint. HTTP Code: $httpCode");
        }

        logActivity("✅ Order #$order_id successfully marked as 'Cancelled' by Admin ID: $resolved_by");

    } elseif (strcasecmp($reported_value, 'Resolved') === 0) {
        // Update reported and is_resolved flags
        $update = $conn->prepare("UPDATE orders SET reported = 0, is_resolved = 1 WHERE order_id = ?");
        $update->bind_param("i", $order_id);

        if (!$update->execute()) {
            throw new Exception("Failed to update order status: " . $update->error);
        }

        $update->close();
        logActivity("✅ Order #$order_id successfully marked as 'Resolved' by Admin ID: $resolved_by");
    }

    // ✅ Fetch customer and driver emails
    $emails = [];

    // Fetch customer email
    if (!empty($order['customer_id'])) {
        $customerFetch = $conn->prepare("SELECT email, firstname, lastname FROM customers WHERE customer_id = ?");
        $customerFetch->bind_param("i", $order['customer_id']);
        $customerFetch->execute();
        $customerRes = $customerFetch->get_result();
        if ($customerRow = $customerRes->fetch_assoc()) {
            $emails['customer'] = $customerRow;
            logActivity("✅ Customer email found: {$customerRow['email']} for Order ID $order_id");
        } else {
            logActivity("⚠️ Customer not found with ID: {$order['customer_id']}");
        }
        $customerFetch->close();
    } else {
        logActivity("⚠️ No customer ID found for Order ID $order_id");
    }

    // Fetch driver email
    if (!empty($order['driver_id'])) {
        $driverFetch = $conn->prepare("SELECT email, firstname, lastname FROM driver WHERE id = ?");
        $driverFetch->bind_param("i", $order['driver_id']);
        $driverFetch->execute();
        $driverRes = $driverFetch->get_result();
        if ($driverRow = $driverRes->fetch_assoc()) {
            $emails['driver'] = $driverRow;
            logActivity("✅ Driver email found: {$driverRow['email']} for Order ID $order_id");
        } else {
            logActivity("⚠️ Driver not found with ID: {$order['driver_id']}");
        }
        $driverFetch->close();
    } else {
        logActivity("ℹ️ No driver ID found for Order ID $order_id");
    }

    // ✅ Prepare email details based on resolution type
    $subject = "";
    $body = "";

    if ($reported_value === 'Cancelled') {
        $subject = "📦 Order #{$order_id} Cancellation Notification";
        $body = "
    <h3>Order Cancellation Notification</h3>
    <p>Your order has been cancelled and resolved by our support team.</p>
    <div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;'>
        <ul style='list-style: none; padding: 0;'>
            <li><strong>Order ID:</strong> {$order_id}</li>
            <li><strong>Status:</strong> Cancelled</li>
            <li><strong>Resolution Date:</strong> " . date('Y-m-d H:i:s') . "</li>
            <li><strong>Resolution Note:</strong> " . ($resolution_note ?: 'No additional notes provided') . "</li>
        </ul>
    </div>
    <p>If you have any questions, please contact our support team.</p>
    <p>Thank you for choosing our service.</p>
";
    } elseif ($reported_value === 'Resolved') {
        $subject = "✅ Order #{$order_id} Resolution Notification";
        $body = "
    <h3>Order Resolution Notification</h3>
    <p>The reported issue with your order has been successfully resolved.</p>
    <div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;'>
        <ul style='list-style: none; padding: 0;'>
            <li><strong>Order ID:</strong> {$order_id}</li>
            <li><strong>Status:</strong> Resolved</li>
            <li><strong>Resolution Date:</strong> " . date('Y-m-d H:i:s') . "</li>
            <li><strong>Resolution Action:</strong> Issue Resolved</li>
            <li><strong>Resolution Note:</strong> " . ($resolution_note ?: 'No additional notes provided') . "</li>
        </ul>
    </div>
    <p>Your order will continue with normal delivery process.</p>
    <p>Thank you for your patience and understanding.</p>
";
    }

    // ✅ Send emails to customer and driver
    $emailSent = true;
    $sentTo = [];

    // Send to customer (if exists)
    if (isset($emails['customer'])) {
        $customer = $emails['customer'];
        $customerBody = str_replace("<h3>Order", "<h3>Dear " . ($customer['firstname'] ?? 'Customer') . ",<br><br>Order", $body);

        if (sendEmailWithGmailSMTP($customer['email'], $customerBody, $subject)) {
            logActivity("✅ Resolution email sent to customer: {$customer['email']} for Order ID $order_id");
            $sentTo[] = "Customer ({$customer['email']})";
        } else {
            $emailSent = false;
            logActivity("❌ Failed to send resolution email to customer: {$customer['email']}");
        }
    } else {
        logActivity("⚠️ Customer email not available for Order ID $order_id");
    }

    // Send to driver (if exists)
    if (isset($emails['driver'])) {
        $driver = $emails['driver'];
        $driverBody = str_replace("<h3>Order", "<h3>Dear " . ($driver['firstname'] ?? 'Driver') . ",<br><br>Order", $body);

        if (sendEmailWithGmailSMTP($driver['email'], $driverBody, $subject)) {
            logActivity("✅ Resolution email sent to driver: {$driver['email']} for Order ID $order_id");
            $sentTo[] = "Driver ({$driver['email']})";
        } else {
            $emailSent = false;
            logActivity("❌ Failed to send resolution email to driver: {$driver['email']}");
        }
    } else {
        logActivity("ℹ️ Driver email not available for Order ID $order_id");
    }

    // Log email summary
    if (!empty($sentTo)) {
        logActivity("📧 Resolution emails sent for Order ID $order_id to: " . implode(', ', $sentTo));
    } else {
        logActivity("⚠️ No resolution emails were sent for Order ID $order_id");
    }

    // ✅ Commit Transaction
    $conn->commit();
    logActivity("💾 Transaction committed successfully for Order ID: $order_id (Resolution: $reported_value)");

    echo json_encode([
        "success" => true,
        "message" => "Order resolution completed successfully.",
    ]);

} catch (Exception $e) {
    // ❌ Rollback & Log
    if ($conn && !$conn->connect_error) {
        $conn->rollback();
        logActivity("⚠️ Transaction rolled back for Order resolution due to error: " . $e->getMessage());
    }

    // Close any open statements
    if ($stmt)
        $stmt->close();
    if ($update)
        $update->close();

    logActivity("❌ Order resolution failed: " . $e->getMessage());

    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage(),
    ]);
}
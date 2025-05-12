<?php
header('Content-Type: application/json');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection file
include('config.php');
include('restriction_checker.php');
$approvalID = $_SESSION['unique_id'];

// Retrieve JSON input
$data = json_decode(file_get_contents("php://input"), true);

// Handle both single and bulk updates
if (isset($data['orders']) && is_array($data['orders'])) {
    $orders = $data['orders']; // Bulk update
} elseif (isset($data['order_id'], $data['status'])) {
    $orders = [[ 'order_id' => $data['order_id'], 'status' => $data['status'] ]]; // Convert single order to array
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
    exit;
}

// Validate order statuses
$valid_statuses = ['Approved', 'Declined'];

if (isset($data['orders']) && is_array($data['orders'])) {
    // Bulk order validation
    foreach ($data['orders'] as $order) {
        if (!isset($order['order_id'], $order['status']) || !in_array($order['status'], $valid_statuses)) {
            echo json_encode(['success' => false, 'message' => 'Invalid order data or status value.']);
            exit;
        }
    }
} else if (isset($data['order_id'], $data['status'])) {
    // Single order validation
    if (!in_array($data['status'], $valid_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status value.']);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
    exit;
}

// Function to generate transaction reference
function generateTransactionReference() {
    return strtoupper(uniqid('TRX', true) . mt_rand(1000, 9999));
}

// Check available drivers and food stock only for Approved orders
$approvedOrders = array_filter($orders, function($order) {
    return $order['status'] === 'Approved';
});

if (!empty($approvedOrders)) {
    // Check available drivers
    $driverCountQuery = "SELECT COUNT(*) as available_drivers FROM driver WHERE status = 'Available' AND restriction = 0";
    $driverCountResult = $conn->query($driverCountQuery);
    $availableDrivers = $driverCountResult->fetch_assoc()['available_drivers'];

    // Check required drivers (same as number of approved orders)
    $requiredDrivers = count($approvedOrders);

    // Check total available food stock
    $foodStockQuery = "SELECT food_id, available_quantity FROM food";
    $foodStockResult = $conn->query($foodStockQuery);
    $availableFoodStock = [];
    while ($row = $foodStockResult->fetch_assoc()) {
        $availableFoodStock[$row['food_id']] = $row['available_quantity'];
    }

    // Calculate total food required for approved orders
    $requiredFoodStock = [];
    foreach ($approvedOrders as $order) {
        $stmt = $conn->prepare("SELECT food_id, quantity FROM order_details WHERE order_id = ?");
        $stmt->bind_param("i", $order['order_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            if (!isset($requiredFoodStock[$row['food_id']])) {
                $requiredFoodStock[$row['food_id']] = 0;
            }
            $requiredFoodStock[$row['food_id']] += $row['quantity'];
        }
        $stmt->close();
    }

    // Validate resources
    $foodShortage = false;
    foreach ($requiredFoodStock as $foodId => $neededQuantity) {
        if (!isset($availableFoodStock[$foodId]) || $availableFoodStock[$foodId] < $neededQuantity) {
            $foodShortage = true;
            break;
        }
    }

    if ($availableDrivers < $requiredDrivers) {
        echo json_encode(['success' => false, 'message' => 'Insufficient available drivers to process all approved orders.']);
        exit;
    }

    if ($foodShortage) {
        echo json_encode(['success' => false, 'message' => 'Insufficient food stock to process all approved orders.']);
        exit;
    }
}
// If checks pass, proceed with transaction
// Begin database transaction
$conn->begin_transaction();

try {
    foreach ($orders as $order) {
        $order_id = $order['order_id'];
        $status = $order['status'];

        if ($status === 'Approved') {
            // Find an available driver
            $findDriverQuery = "SELECT id FROM driver WHERE status = 'Available' AND restriction = 0 ORDER BY RAND() LIMIT 1";
            $result = $conn->query($findDriverQuery);

            if ($result->num_rows > 0) {
                $driver = $result->fetch_assoc();
                $driver_id = $driver['id'];

                // Assign driver to order
                $assignDriverQuery = "UPDATE orders SET driver_id = ?, delivery_status = 'Assigned', status = ?, updated_at = NOW(), approved_by = ? WHERE order_id = ?";
                $stmt = $conn->prepare($assignDriverQuery);
                $stmt->bind_param('isii', $driver_id, $status, $approvalID, $order_id);
                $stmt->execute();
                $stmt->close();

                // Update driver status
                $updateDriverStatusQuery = "UPDATE driver SET status = 'Not Available' WHERE id = ?";
                $stmt = $conn->prepare($updateDriverStatusQuery);
                $stmt->bind_param('i', $driver_id);
                $stmt->execute();
                $stmt->close();

                // Check if the order is a credit order
                $checkCreditQuery = "SELECT is_credit, total_amount, customer_id FROM orders WHERE order_id = ?";
                $stmt = $conn->prepare($checkCreditQuery);
                $stmt->bind_param("i", $order_id);
                $stmt->execute();
                $stmt->bind_result($isCredit, $totalAmount, $customerId);
                $stmt->fetch();
                $stmt->close();

                if ($isCredit) {
                    $updateCreditOrderStatusQuery = "UPDATE credit_orders SET status = 'Approved' WHERE order_id = ?";
                    $stmt = $conn->prepare($updateCreditOrderStatusQuery);
                    $stmt->bind_param('i', $order_id);
                    $stmt->execute();
                    $stmt->close();
                }

            } else {
                throw new Exception("No available drivers for order ID: $order_id");
            }
        } elseif ($status === 'Declined') {
            // Refund process
            $stmt = $conn->prepare("SELECT total_amount, customer_id, is_credit FROM orders WHERE order_id = ?");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $stmt->bind_result($totalAmount, $customerId, $isCredit);
            $stmt->fetch();
            $stmt->close();

            $transactionReference = generateTransactionReference();

            if (!$isCredit) {
                // Refund to wallet
                $stmt = $conn->prepare("UPDATE wallets SET balance = balance + ? WHERE customer_id = ?");
                $stmt->bind_param("di", $totalAmount, $customerId);
                $stmt->execute();
                $stmt->close();

                // Insert refund transaction
                $description = "Declined Order Refund - Order ID: $order_id";
                $paymentMethod = "Transaction Refund";
                $stmt = $conn->prepare("INSERT INTO customer_transactions (transaction_ref, customer_id, amount, date_created, transaction_type, payment_method, description) VALUES (?, ?, ?, NOW(), 'credit', ?, ?)");
                $stmt->bind_param("sidss", $transactionReference, $customerId, $totalAmount, $paymentMethod, $description);
                $stmt->execute();
                $stmt->close();
            } else {
                $description = "Declined Food Order on Credit for Order ID: " . $order_id;
                $paymentMethod = "Not Applicable";
                $stmt = $conn->prepare("INSERT INTO customer_transactions (transaction_ref, customer_id, amount, date_created, transaction_type, payment_method, description) VALUES (?, ?, ?, NOW(), 'Others', ?, ?)");
                $stmt->bind_param("sidss", $transactionReference, $customerId, $totalAmount, $paymentMethod, $description);
                $stmt->execute();
                $stmt->close();

                // Handle declined credit orders
                $stmt = $conn->prepare("UPDATE credit_orders SET status = 'Declined', repayment_status = 'Void' WHERE order_id = ?");
                $stmt->bind_param("i", $order_id);
                $stmt->execute();
                $stmt->close();
            }
            $transaction_type = 'Others';
            $status1 = 'Declined';
            $paymentMethod = 'Declined Order';
            $revenue_type = 6;
            $stmt = $conn->prepare("INSERT INTO transactions (transaction_ref, customer_id, order_id, transaction_type, amount, transaction_date, payment_method, status, created_at, revenue_type_id) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, NOW(), ?)");
            $stmt->bind_param("siisdssi", $transactionReference, $customerId, $order_id, $transaction_type, $totalAmount, $paymentMethod, $status1, $revenue_type);
            $stmt->execute();
            $stmt->close();

            // Update food stock
            $stmt = $conn->prepare("SELECT food_id, quantity FROM order_details WHERE order_id = ?");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $foodId = $row['food_id'];
                $quantity = $row['quantity'];
                $updateFoodStmt = $conn->prepare("UPDATE food SET available_quantity = available_quantity + ? WHERE food_id = ?");
                $updateFoodStmt->bind_param("ii", $quantity, $foodId);
                $updateFoodStmt->execute();
                $updateFoodStmt->close();
            }
            $stmt->close();

            // Update order status to Declined
            $updateDeliveryStatusQuery = "UPDATE orders SET delivery_status = 'Declined', updated_at = NOW(), approved_by = ? WHERE order_id = ?";
            $stmt = $conn->prepare($updateDeliveryStatusQuery);
            $stmt->bind_param("ii", $approvalID, $order_id);
            $stmt->execute();
            $stmt->close();
        }

        // Update orders and order_details status
        $updateStatusQueries = [
            "UPDATE orders SET status = ?, updated_at = NOW() WHERE order_id = ?",
            "UPDATE order_details SET status = ?, updated_at = NOW() WHERE order_id = ?"
        ];
        foreach ($updateStatusQueries as $query) {
            $stmt = $conn->prepare($query);
            $stmt->bind_param("si", $status, $order_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Commit transaction if all operations succeed
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Order update successful.']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()]);
}

$conn->close();


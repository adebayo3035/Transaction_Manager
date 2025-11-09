<?php
header('Content-Type: application/json');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection and other required files
include('restriction_checker.php');

$approvalID = $_SESSION['unique_id'];
logActivity("Approval process started by user with ID: $approvalID");

// Retrieve JSON input
$rawInput = file_get_contents("php://input");
logActivity("Raw input received: " . $rawInput);

$data = json_decode($rawInput, true);
if (!$data) {
    logActivity("Failed to decode JSON input.");
    echo json_encode(['success' => false, 'message' => 'Invalid JSON format.']);
    exit;
}

// Handle both single and bulk updates
if (isset($data['orders']) && is_array($data['orders'])) {
    $orders = $data['orders']; // Bulk update
    logActivity("Bulk orders received: " . json_encode($orders));
} elseif (isset($data['order_id'], $data['status'])) {
    $orders = [[ 'order_id' => $data['order_id'], 'status' => $data['status'] ]]; // Convert single order to array
    logActivity("Single order received: " . json_encode($orders));
} else {
    logActivity("Invalid request data. Missing order_id or orders array.");
    echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
    exit;
}

// Validate order statuses
$valid_statuses = ['Approved', 'Declined', 'Cancelled'];

if (isset($data['orders']) && is_array($data['orders'])) {
    foreach ($data['orders'] as $order) {
        if (!isset($order['order_id'], $order['status']) || !in_array($order['status'], $valid_statuses)) {
            logActivity("Invalid data in bulk order: " . json_encode($order));
            echo json_encode(['success' => false, 'message' => 'Invalid order data or status value.']);
            exit;
        }
    }
    logActivity("All bulk orders passed validation.");
} elseif (isset($data['order_id'], $data['status'])) {
    if (!in_array($data['status'], $valid_statuses)) {
        logActivity("Invalid status value in single order: " . $data['status']);
        echo json_encode(['success' => false, 'message' => 'Invalid status value.']);
        exit;
    }
    logActivity("Single order passed validation.");
} else {
    logActivity("Validation failed due to missing keys.");
    echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
    exit;
}

// Function to generate transaction reference
function generateTransactionReference() {
    // Prefix (3) + 7-char unique + 2-digit random = 12 chars total
    return 'TRX' . strtoupper(substr(sha1(uniqid(mt_rand(), true)), 0, 7)) . mt_rand(10, 99);
}


// Check available drivers and food stock only for Approved orders
$approvedOrders = array_filter($orders, function($order) {
    return $order['status'] === 'Approved';
});
logActivity("Filtered approved orders: " . json_encode($approvedOrders));

if (!empty($approvedOrders)) {
    logActivity("Checking resource availability for approved orders.");

    // Check available drivers
    $driverCountQuery = "SELECT COUNT(*) as available_drivers FROM driver WHERE status = 'Available' AND restriction = 0 AND delete_status IS NULL";
    logActivity("Executing driver count query: $driverCountQuery");
    $driverCountResult = $conn->query($driverCountQuery);

    if (!$driverCountResult) {
        logActivity("Driver count query failed: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Failed to retrieve driver data.']);
        exit;
    }

    $availableDrivers = $driverCountResult->fetch_assoc()['available_drivers'];
    $requiredDrivers = count($approvedOrders);
    logActivity("Available drivers: $availableDrivers, Required drivers: $requiredDrivers");

    // Check available food stock
    $foodStockQuery = "SELECT food_id, available_quantity FROM food";
    logActivity("Executing food stock query: $foodStockQuery");
    $foodStockResult = $conn->query($foodStockQuery);

    if (!$foodStockResult) {
        logActivity("Food stock query failed: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Failed to retrieve food stock.']);
        exit;
    }

    $availableFoodStock = [];
    while ($row = $foodStockResult->fetch_assoc()) {
        $availableFoodStock[$row['food_id']] = $row['available_quantity'];
    }
    logActivity("Available food stock: " . json_encode($availableFoodStock));

    // Calculate required food per order
    $requiredFoodStock = [];
    foreach ($approvedOrders as $order) {
        $stmt = $conn->prepare("SELECT food_id, quantity FROM order_details WHERE order_id = ?");
        if (!$stmt) {
            logActivity("Prepare failed for order_id {$order['order_id']}: " . $conn->error);
            echo json_encode(['success' => false, 'message' => 'Internal error preparing statement.']);
            exit;
        }

        $stmt->bind_param("i", $order['order_id']);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $requiredFoodStock[$row['food_id']] = ($requiredFoodStock[$row['food_id']] ?? 0) + $row['quantity'];
        }

        $stmt->close();
    }
    logActivity("Total required food stock: " . json_encode($requiredFoodStock));

    // Validate food stock
    $foodShortage = false;
    foreach ($requiredFoodStock as $foodId => $neededQuantity) {
        if (!isset($availableFoodStock[$foodId]) || $availableFoodStock[$foodId] < $neededQuantity) {
            logActivity("Food shortage detected for food_id $foodId: Needed $neededQuantity, Available " . ($availableFoodStock[$foodId] ?? 0));
            $foodShortage = true;
            break;
        }
    }

    // Validate drivers
    if ($availableDrivers < $requiredDrivers) {
        logActivity("Insufficient drivers. Available: $availableDrivers, Required: $requiredDrivers");
        echo json_encode(['success' => false, 'message' => 'Insufficient available drivers to process all approved orders.']);
        exit;
    }

    if ($foodShortage) {
        logActivity("Insufficient food stock to process all approved orders.");
        echo json_encode(['success' => false, 'message' => 'Insufficient food stock to process all approved orders.']);
        exit;
    }

    logActivity("Sufficient drivers and food stock available. Proceeding with order updates.");
}
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

                logActivity("Driver assigned to order #$order_id. Driver ID: $driver_id by approver $approvalID");

                // Update driver status
                $updateDriverStatusQuery = "UPDATE driver SET status = 'Not Available' WHERE id = ?";
                $stmt = $conn->prepare($updateDriverStatusQuery);
                $stmt->bind_param('i', $driver_id);
                $stmt->execute();
                $stmt->close();

                logActivity("Driver status set to 'Not Available'. Driver ID: $driver_id");

                // Check if order is on credit
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

                    logActivity("Credit order approved. Order ID: $order_id");
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

                logActivity("Refunded ₦$totalAmount to customer ID $customerId for declined order ID $order_id");

                // Insert refund transaction
                $description = "Declined Order Refund - Order ID: $order_id";
                $paymentMethod = "Transaction Refund";
                $stmt = $conn->prepare("INSERT INTO customer_transactions (transaction_ref, customer_id, amount, date_created, transaction_type, payment_method, description) VALUES (?, ?, ?, NOW(), 'credit', ?, ?)");
                $stmt->bind_param("sidss", $transactionReference, $customerId, $totalAmount, $paymentMethod, $description);
                $stmt->execute();
                $stmt->close();

                logActivity("Customer transaction logged for refund. Ref: $transactionReference");
            } else {
                $description = "Declined Food Order on Credit for Order ID: " . $order_id;
                $paymentMethod = "Not Applicable";
                $stmt = $conn->prepare("INSERT INTO customer_transactions (transaction_ref, customer_id, amount, date_created, transaction_type, payment_method, description) VALUES (?, ?, ?, NOW(), 'Others', ?, ?)");
                $stmt->bind_param("sidss", $transactionReference, $customerId, $totalAmount, $paymentMethod, $description);
                $stmt->execute();
                $stmt->close();

                logActivity("Credit order decline logged in customer_transactions. Ref: $transactionReference");

                // Declined credit order update
                $stmt = $conn->prepare("UPDATE credit_orders SET status = 'Declined', repayment_status = 'Void' WHERE order_id = ?");
                $stmt->bind_param("i", $order_id);
                $stmt->execute();
                $stmt->close();

                logActivity("Credit order marked as Declined/Void. Order ID: $order_id");
            }

            $transaction_type = 'Others';
            $status1 = 'Declined';
            $paymentMethod = 'Declined Order';
            $revenue_type = 6;
            $stmt = $conn->prepare("INSERT INTO transactions (transaction_ref, customer_id, order_id, transaction_type, amount, transaction_date, payment_method, status, created_at, revenue_type_id) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, NOW(), ?)");
            $stmt->bind_param("siisdssi", $transactionReference, $customerId, $order_id, $transaction_type, $totalAmount, $paymentMethod, $status1, $revenue_type);
            $stmt->execute();
            $stmt->close();

            logActivity("Logged refund transaction in transactions table. Ref: $transactionReference");

            // Return food quantity to stock
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

                logActivity("Restocked food item ID $foodId by $quantity units from order ID $order_id");
            }
            $stmt->close();

            // Update delivery status to Declined
            $updateDeliveryStatusQuery = "UPDATE orders SET delivery_status = 'Declined', updated_at = NOW(), approved_by = ? WHERE order_id = ?";
            $stmt = $conn->prepare($updateDeliveryStatusQuery);
            $stmt->bind_param("ii", $approvalID, $order_id);
            $stmt->execute();
            $stmt->close();

            logActivity("Delivery status updated to Declined for order ID $order_id");
        }
        elseif ($status === 'Cancelled') {
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

                logActivity("Refunded ₦$totalAmount to customer ID $customerId for Cancelled order ID $order_id");

                // Insert refund transaction
                $description = "Cancelled Order Refund for - Order ID: $order_id";
                $paymentMethod = "Transaction Refund";
                $stmt = $conn->prepare("INSERT INTO customer_transactions (transaction_ref, customer_id, amount, date_created, transaction_type, payment_method, description) VALUES (?, ?, ?, NOW(), 'credit', ?, ?)");
                $stmt->bind_param("sidss", $transactionReference, $customerId, $totalAmount, $paymentMethod, $description);
                $stmt->execute();
                $stmt->close();

                logActivity("Customer transaction logged for refund. Ref: $transactionReference");
            } else {
                $description = "Cancelled Food Order on Credit for Order ID: " . $order_id;
                $paymentMethod = "Not Applicable";
                $stmt = $conn->prepare("INSERT INTO customer_transactions (transaction_ref, customer_id, amount, date_created, transaction_type, payment_method, description) VALUES (?, ?, ?, NOW(), 'Others', ?, ?)");
                $stmt->bind_param("sidss", $transactionReference, $customerId, $totalAmount, $paymentMethod, $description);
                $stmt->execute();
                $stmt->close();

                logActivity("Credit order Cancelled logged in customer_transactions. Ref: $transactionReference");

                // Declined credit order update
                $stmt = $conn->prepare("UPDATE credit_orders SET status = 'Declined', repayment_status = 'Void' WHERE order_id = ?");
                $stmt->bind_param("i", $order_id);
                $stmt->execute();
                $stmt->close();

                logActivity("Credit order marked as Declined/Void. Order ID: $order_id");
            }

            $transaction_type = 'Others';
            $status1 = 'Declined';
            $paymentMethod = 'Declined Order';
            $revenue_type = 6;
            $stmt = $conn->prepare("INSERT INTO transactions (transaction_ref, customer_id, order_id, transaction_type, amount, transaction_date, payment_method, status, created_at, revenue_type_id) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, NOW(), ?)");
            $stmt->bind_param("siisdssi", $transactionReference, $customerId, $order_id, $transaction_type, $totalAmount, $paymentMethod, $status1, $revenue_type);
            $stmt->execute();
            $stmt->close();

            logActivity("Logged refund transaction in transactions table. Ref: $transactionReference");

            // Return food quantity to stock
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

                logActivity("Restocked food item ID $foodId by $quantity units from order ID $order_id");
            }
            $stmt->close();

            // Update delivery status to Cancelled
            $updateDeliveryStatusQuery = "UPDATE orders SET delivery_status = 'Cancelled', updated_at = NOW(), approved_by = ? WHERE order_id = ?";
            $stmt = $conn->prepare($updateDeliveryStatusQuery);
            $stmt->bind_param("ii", $approvalID, $order_id);
            $stmt->execute();
            $stmt->close();

            logActivity("Delivery status updated to Cancelled for order ID $order_id");
        }


        // Common updates to order and order_details
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

        logActivity("Order and order details status updated for order ID $order_id to '$status'");
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Order update successful.']);
    logActivity("All order operations committed successfully by approver $approvalID");
} catch (Exception $e) {
    $conn->rollback();
    logActivity("Transaction failed and rolled back. Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()]);
}

$conn->close();


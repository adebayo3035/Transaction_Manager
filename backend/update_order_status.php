<?php
header('Content-Type: application/json');

// Include database connection file
include('config.php');

// Get the request data
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['order_id']) && isset($data['status'])) {
    $order_id = $data['order_id'];
    $status = $data['status'];

    // Validate the status value
    $valid_statuses = ['Approved', 'Declined'];
    if (!in_array($status, $valid_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status value.']);
        exit;
    }

    // Start a transaction
    $conn->begin_transaction();

    try {
        // Update the order status in the orders table
        $query = "UPDATE orders SET status = ?, updated_at = NOW() WHERE order_id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Prepare statement failed: ' . $conn->error);
        }
        $stmt->bind_param("si", $status, $order_id);
        $stmt->execute();
        if ($stmt->affected_rows === 0) {
            throw new Exception('No rows updated in orders table.');
        }
        $stmt->close();

        // Update the status in the revenue table
        $revenue_query = "UPDATE revenue SET status = ? WHERE order_id = ?";
        $revenue_stmt = $conn->prepare($revenue_query);
        if (!$revenue_stmt) {
            throw new Exception('Prepare statement failed for revenue: ' . $conn->error);
        }
        $revenue_stmt->bind_param("si", $status, $order_id);
        $revenue_stmt->execute();
        $revenue_stmt->close();

        // Update the status in the order_details table
        $order_details_query = "UPDATE order_details SET status = ?, updated_at = NOW() WHERE order_id = ?";
        $order_details_stmt = $conn->prepare($order_details_query);
        if (!$order_details_stmt) {
            throw new Exception('Prepare statement failed for order_details: ' . $conn->error);
        }
        $order_details_stmt->bind_param("si", $status, $order_id);
        $order_details_stmt->execute();
        $order_details_stmt->close();

        // Get total amount and customer id from the order
        $stmt = $conn->prepare("SELECT total_amount, customer_id FROM orders WHERE order_id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $stmt->bind_result($totalAmount, $customerId);
        $stmt->fetch();
        $stmt->close();

        if ($status === 'Declined') {
            // Refund the customer
            $stmt = $conn->prepare("UPDATE wallets SET balance = balance + ? WHERE customer_id = ?");
            $stmt->bind_param("di", $totalAmount, $customerId);
            $stmt->execute();
            $stmt->close();
        }

        // Commit the transaction
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Order status updated successfully.']);

    } catch (Exception $e) {
        // Rollback the transaction if an error occurred
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
}

$conn->close();
?>

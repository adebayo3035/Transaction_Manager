<?php
header('Content-Type: application/json');
require 'config.php';
session_start();
if (!isset($_SESSION['customer_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}

$customerId = $_SESSION['customer_id'];

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = ($page - 1) * $limit;

try {
    // Fetch credits with pagination
    $stmt = $conn->prepare("SELECT credit_order_id, order_id, created_at, remaining_balance, repayment_status 
                            FROM credit_orders where customer_id = ?
                            LIMIT ? OFFSET ?");
    $stmt->bind_param("iii", $customerId, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $credits = [];
    while ($row = $result->fetch_assoc()) {
        $credits[] = $row;
    }

    // Fetch total credits count for pagination
    $countResult = $conn->query("SELECT COUNT(*) as total FROM credit_orders");
    $totalCredits = $countResult->fetch_assoc()['total'];

    echo json_encode([
        'success' => true,
        'credits' => $credits,
        'total' => $totalCredits,
        'page' => $page,
        'limit' => $limit
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}


<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

// Log start
logActivity("Pending orders fetch process started");

try {
    if (!isset($_SESSION['unique_id'])) {
        logActivity("Unauthenticated access attempt to pending orders");
        echo json_encode(["success" => false, "message" => "Not logged in."]);
        exit();
    }

    $adminId = $_SESSION['unique_id'];
    $userRole = $_SESSION['role'] ?? null;
    logActivity("Request initiated by Admin ID: $adminId (Role: $userRole)");

    // Pagination setup
    $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(1, (int) $_GET['limit']) : 10;
    $offset = ($page - 1) * $limit;

    $conn->begin_transaction();
    logActivity("Database transaction started");

    // Total count query
    if ($userRole === "Admin") {
        $countSql = "SELECT COUNT(*) AS total FROM orders WHERE status = 'Pending' AND assigned_to = ?";
        $stmt = $conn->prepare($countSql);
        $stmt->bind_param("i", $adminId);
    } elseif ($userRole === "Super Admin") {
        $countSql = "SELECT COUNT(*) AS total FROM orders WHERE status = 'Pending'";
        $stmt = $conn->prepare($countSql);
    } else {
        throw new Exception("Unauthorized role: $userRole");
    }

    $stmt->execute();
    $countResult = $stmt->get_result();
    $total = ($countResult && $row = $countResult->fetch_assoc()) ? (int)$row['total'] : 0;
    $totalPages = $limit > 0 ? ceil($total / $limit) : 0;
    $stmt->close();

    // Main data query
    if ($userRole === "Admin") {
        $sql = "
            SELECT o.order_id, o.customer_id, o.order_date, o.total_amount, o.status,
                   a.firstname AS assigned_admin_firstname, a.lastname AS assigned_admin_lastname
            FROM orders o
            INNER JOIN admin_tbl a ON o.assigned_to = a.unique_id
            WHERE o.status = 'Pending' AND o.assigned_to = ?
            ORDER BY o.order_date DESC
            LIMIT ? OFFSET ?
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $adminId, $limit, $offset);
    } else { // Super Admin
        $sql = "
            SELECT o.order_id, o.customer_id, o.order_date, o.total_amount, o.status,
                   o.assigned_to, a.firstname AS assigned_admin_firstname, 
                   a.lastname AS assigned_admin_lastname
            FROM orders o
            LEFT JOIN admin_tbl a ON o.assigned_to = a.unique_id
            WHERE o.status = 'Pending'
            ORDER BY o.order_date DESC
            LIMIT ? OFFSET ?
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $limit, $offset);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $orders = [];

    while ($row = $result->fetch_assoc()) {
        $row['total_amount'] = number_format($row['total_amount'], 2);
        $orders[] = $row;
    }

    $conn->commit();
    logActivity("Retrieved " . count($orders) . " pending orders by Admin ID $adminId");

    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'totalPages' => $totalPages,
        'requested_by' => $adminId,
        'user_role' => $userRole,
        'timestamp' => date('c')
    ]);
    logActivity("Pending orders fetch completed successfully");

} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    logActivity("Error fetching pending orders: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch pending orders',
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
    logActivity("Pending orders fetch process ended");
}

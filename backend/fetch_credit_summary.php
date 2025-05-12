<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

// Initialize logging
logActivity("Credit orders listing process started");

try {
    // Check authentication
    if (!isset($_SESSION['unique_id'])) {
        $errorMsg = "Unauthenticated access attempt";
        logActivity($errorMsg);
        echo json_encode(["success" => false, "message" => "Not logged in."]);
        exit();
    }

    $adminId = $_SESSION['unique_id'];
    logActivity("Request initiated by admin ID: " . $adminId);

    // Validate and sanitize pagination parameters
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['page']) ? (int)$_GET['limit'] : 10;
    
    // Validate parameters
    if ($page < 1) $page = 1;
    if ($limit < 1 || $limit > 100) $limit = 10; // Prevent excessive load
    
    $offset = ($page - 1) * $limit;
    logActivity("Fetching credits - Page: $page, Limit: $limit, Offset: $offset");

    // Start transaction for consistent data view
    $conn->begin_transaction();
    logActivity("Transaction started for credit orders listing");

    try {
        // Prepare and execute main query with sorting and filtering
        $query = "SELECT co.*, 
                 CONCAT(c.firstname, ' ', c.lastname) AS customer_name,
                 (SELECT SUM(amount_paid) FROM repayment_history 
                  WHERE credit_order_id = co.credit_order_id) AS total_paid
                 FROM credit_orders co
                 JOIN customers c ON co.customer_id = c.customer_id
                 ORDER BY co.created_at DESC
                 LIMIT ? OFFSET ?";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed for credit orders: " . $conn->error);
        }

        $stmt->bind_param("ii", $limit, $offset);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed for credit orders: " . $stmt->error);
        }

        $result = $stmt->get_result();
        $credits = [];

        while ($row = $result->fetch_assoc()) {
            // Calculate additional fields
            $currentDate = new DateTime();
            $dueDate = new DateTime($row['due_date']);
            
            $row['is_overdue'] = ($currentDate > $dueDate && 
                                 $row['repayment_status'] !== 'Paid');
            $row['days_overdue'] = $row['is_overdue'] ? 
                                  $currentDate->diff($dueDate)->days : 0;
            $row['remaining_balance'] = max(0, $row['total_credit_amount'] - $row['total_paid']);
            
            $credits[] = $row;
        }

        // $stmt->close();
        logActivity("Fetched " . count($credits) . " credit orders");

        // Get total count for pagination
        $countQuery = "SELECT COUNT(*) as total FROM credit_orders";
        $countStmt = $conn->prepare($countQuery);
        if (!$countStmt) {
            throw new Exception("Prepare failed for count: " . $conn->error);
        }

        if (!$countStmt->execute()) {
            throw new Exception("Execute failed for count: " . $countStmt->error);
        }

        $countResult = $countStmt->get_result();
        $totalCount = $countResult->fetch_assoc()['total'];
        // $countStmt->close();

        $conn->commit();

        // Prepare response
        $response = [
            'success' => true,
            'data' => [
                'credits' => $credits,
                'pagination' => [
                    'total' => (int)$totalCount,
                    'page' => $page,
                    'limit' => $limit,
                    'total_pages' => ceil($totalCount / $limit)
                ]
            ],
            'meta' => [
                'requested_by' => $adminId,
                'timestamp' => date('c'),
                'version' => '1.0'
            ]
        ];

        echo json_encode($response);
        logActivity("Credit orders listing completed successfully");

    } catch (Exception $e) {
        $conn->rollback();
        throw $e; // Re-throw to outer catch block
    }
} catch (Exception $e) {
    $errorMsg = "Error fetching credit orders: " . $e->getMessage();
    logActivity($errorMsg);
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to fetch credit orders',
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    if (isset($countStmt) && $countStmt instanceof mysqli_stmt) {
        $countStmt->close();
    }
    $conn->close();
    logActivity("Credit orders listing process completed");
}

<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

// Initialize logging
logActivity("Transaction listing fetch process started");

try {
    // Check authentication
    if (!isset($_SESSION['unique_id'])) {
        $errorMsg = "Unauthenticated access attempt to transaction listing";
        logActivity($errorMsg);
        echo json_encode(["success" => false, "message" => "Not logged in."]);
        exit();
    }

    $adminId = $_SESSION['unique_id'];
    $userRole = $_SESSION['role'] ?? null;
    logActivity("Request initiated by user ID: $adminId (Role: $userRole)");

    // Validate and sanitize pagination parameters
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    
    if ($page < 1 || $limit < 1 || $limit > 100) {
        $errorMsg = "Invalid pagination parameters - Page: $page, Limit: $limit";
        logActivity($errorMsg);
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid pagination parameters. Page must be ≥ 1 and limit between 1-100.'
        ]);
        exit();
    }

    $offset = ($page - 1) * $limit;
    logActivity("Fetching transactions - Page: $page, Limit: $limit, Offset: $offset");

    try {
        // Start transaction for consistent data view
        $conn->begin_transaction();
        logActivity("Database transaction started");

        // Fetch total count of transactions
        $totalQuery = "SELECT COUNT(*) as total FROM transactions";
        $totalResult = $conn->query($totalQuery);
        
        if (!$totalResult) {
            throw new Exception("Total count query failed: " . $conn->error);
        }

        $totalRow = $totalResult->fetch_assoc();
        $total = $totalRow['total'];
        $totalPages = ceil($total / $limit);
        logActivity("Total transactions found: $total, Total pages: $totalPages");

        // Fetch paginated transactions
        $query = "SELECT transaction_ref, transaction_type, amount, 
                         payment_method, status, transaction_date 
                  FROM transactions 
                  ORDER BY transaction_date DESC 
                  LIMIT ?, ?";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("ii", $offset, $limit);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $result = $stmt->get_result();
        $transactions = [];

        while ($row = $result->fetch_assoc()) {
            // Format amount as currency if needed
            $row['amount'] = number_format($row['amount'], 2);
            $transactions[] = $row;
        }

       
        $conn->commit();

        logActivity("Successfully retrieved " . count($transactions) . " transactions");

        // Prepare response
        $response = [
            'success' => true,
            'transactions' => $transactions,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'totalPages' => $totalPages,
                'hasNext' => $page < $totalPages,
                'hasPrev' => $page > 1
            ],
            'requested_by' => $adminId,
            'user_role' => $userRole,
            'timestamp' => date('c')
        ];

        echo json_encode($response);
        logActivity("Transaction listing fetch completed successfully");

    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
} catch (Exception $e) {
    $errorMsg = "Error fetching transaction listing: " . $e->getMessage();
    logActivity($errorMsg);
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to fetch transactions',
        'error' => $e->getMessage(),
        'timestamp' => date('c')
    ]);
} finally {
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    if (isset($totalResult)) {
        $totalResult->free();
    }
    $conn->close();
    logActivity("Transaction listing fetch process completed");
}
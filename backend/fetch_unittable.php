<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

// Initialize logging
logActivity("Unit listing fetch process started");

try {
    // Check authentication
    if (!isset($_SESSION['unique_id'])) {
        $errorMsg = "Unauthenticated access attempt to unit listing";
        logActivity($errorMsg);
        echo json_encode(["success" => false, "message" => "Not logged in."]);
        exit();
    }

    $userId = $_SESSION['unique_id'];
    $userRole = $_SESSION['role'] ?? null;
    logActivity("Request initiated by user ID: $userId (Role: $userRole)");

    // Validate user role
    if (!in_array($userRole, ['Admin', 'Super Admin'])) {
        $errorMsg = "Unauthorized access attempt by user ID: $userId (Role: $userRole)";
        logActivity($errorMsg);
        echo json_encode(["success" => false, "message" => "Unauthorized access"]);
        exit();
    }

    try {
        // Start transaction for consistent data view
        $conn->begin_transaction();
        logActivity("Database transaction started");

        // Get page and limit from query parameters (default to page 1, 10 per page)
        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? max(1, (int) $_GET['limit']) : 10;
        $offset = ($page - 1) * $limit;

        // Base query parts
        $selectClause = "SELECT u.unit_id, u.unit_name, u.group_id, g.group_name";
        $fromClause = "FROM unit u JOIN groups g ON u.group_id = g.group_id";
        $orderClause = "ORDER BY g.group_name ASC, u.unit_name ASC";
        
        // Modify query based on user role
        if ($userRole === 'Admin') {
            // Admin can only see non-deleted units
            $whereClause = "WHERE u.delete_status IS NULL";
            $countQuery = "SELECT COUNT(*) as total FROM unit u WHERE u.delete_status IS NULL";
        } else {
            // Super Admin can see all units with delete_status indicator
            $selectClause .= ", u.delete_status";
            $whereClause = "";
            $countQuery = "SELECT COUNT(*) as total FROM unit u";
        }

        // Get total count for pagination
        $countResult = $conn->query($countQuery);
        $totalCount = (int) $countResult->fetch_assoc()['total'];

        // Build final query
        $query = "$selectClause $fromClause $whereClause $orderClause LIMIT ? OFFSET ?";

        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed for units query: " . $conn->error);
        }

        $stmt->bind_param("ii", $limit, $offset);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed for units query: " . $stmt->error);
        }

        $result = $stmt->get_result();
        $units = [];

        while ($row = $result->fetch_assoc()) {
            // For Super Admin, format delete_status for clarity
            if ($userRole === 'Super Admin' && isset($row['delete_status'])) {
                $row['is_deleted'] = ($row['delete_status'] === 1) ? true : false;
                unset($row['delete_status']); // Remove the raw status if needed
            }
            $units[] = $row;
        }

        $conn->commit();
        logActivity("Successfully retrieved " . count($units) . " paginated units");

        // Prepare paginated response
        $response = [
            'success' => true,
            'units' => $units,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $totalCount,
                'total_pages' => ceil($totalCount / $limit)
            ],
            'requested_by' => $userId,
            'user_role' => $userRole,
            'timestamp' => date('c'),
            'data_scope' => ($userRole === 'Admin') ? 'active_only' : 'all_records'
        ];

        echo json_encode($response);
        logActivity("Unit listing fetch completed successfully");

    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    $errorMsg = "Error fetching unit listing: " . $e->getMessage();
    logActivity($errorMsg);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch units',
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    $conn->close();
    logActivity("Unit listing fetch process completed");
}
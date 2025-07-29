<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

logActivity("Unit listing fetch process started");

try {
    if (!isset($_SESSION['unique_id'])) {
        logActivity("Unauthenticated access attempt to unit listing");
        echo json_encode(["success" => false, "message" => "Not logged in."]);
        exit();
    }

    $userId = $_SESSION['unique_id'];
    $userRole = $_SESSION['role'] ?? null;
    logActivity("Request initiated by user ID: $userId (Role: $userRole)");

    if (!in_array($userRole, ['Admin', 'Super Admin'])) {
        logActivity("Unauthorized access attempt by user ID: $userId (Role: $userRole)");
        echo json_encode(["success" => false, "message" => "Unauthorized access"]);
        exit();
    }

    $conn->begin_transaction();
    logActivity("Database transaction started");

    $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(1, (int) $_GET['limit']) : 10;
    $offset = ($page - 1) * $limit;

    // Always include delete_status
    $selectClause = "SELECT u.unit_id, u.unit_name, u.group_id, g.group_name, u.delete_status";
    $fromClause = "FROM unit u JOIN groups g ON u.group_id = g.group_id";
    $orderClause = "ORDER BY g.group_name ASC, u.unit_name ASC";

    if ($userRole === 'Admin') {
        $whereClause = "WHERE u.delete_status IS NULL";
        $countQuery = "SELECT COUNT(*) as total FROM unit u WHERE u.delete_status IS NULL";
    } else {
        $whereClause = "";
        $countQuery = "SELECT COUNT(*) as total FROM unit u";
    }

    // Get total count for pagination
    $countResult = $conn->query($countQuery);
    $totalCount = (int) $countResult->fetch_assoc()['total'];

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
        $row['is_deleted'] = !is_null($row['delete_status']);
        $units[] = $row;
    }

    $conn->commit();
    logActivity("Successfully retrieved " . count($units) . " paginated units");

    echo json_encode([
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
    ]);

    logActivity("Unit listing fetch completed successfully");

} catch (Exception $e) {
    $conn->rollback();
    logActivity("Error fetching unit listing: " . $e->getMessage());
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

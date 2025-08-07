<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

// Initialize variables
$stmt = null;
$countStmt = null;

logActivity("Rating listing fetch process started");

try {
    // Check if user is logged in
    if (!isset($_SESSION['unique_id'])) {
        $errorMsg = "Unauthenticated access attempt to rating listing";
        logActivity($errorMsg);
        echo json_encode(["success" => false, "message" => "Not logged in."]);
        exit();
    }

    $userId = $_SESSION['unique_id'];
    $userRole = $_SESSION['role'] ?? null;

    logActivity("Request initiated by user ID: $userId (Role: $userRole)");

    // Validate pagination
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;

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

    // Start DB transaction
    $conn->begin_transaction();
    logActivity("Database transaction started");

    // Determine role-specific query
    $params = [];
    $orderBy = " ORDER BY rating_id DESC";

    if ($userRole === "Admin") {
        // Admin sees only ratings where the order was assigned to them
        $baseQuery = "
            SELECT r.*
            FROM order_ratings r
            INNER JOIN orders o ON r.order_id = o.order_id
            WHERE o.assigned_to = ?
        ";
        $countQuery = "
            SELECT COUNT(*) as total
            FROM order_ratings r
            INNER JOIN orders o ON r.order_id = o.order_id
            WHERE o.assigned_to = ?
        ";
        $params[] = $userId;
    } elseif ($userRole === "Super Admin") {
        // Super Admin sees all ratings
        $baseQuery = "SELECT * FROM order_ratings";
        $countQuery = "SELECT COUNT(*) as total FROM order_ratings";
    } else {
        throw new Exception("Unauthorized role: " . $userRole);
    }

    // Prepare count query
    $countStmt = $conn->prepare($countQuery);
    if (!$countStmt) {
        throw new Exception("Prepare failed for count query: " . $conn->error);
    }

    if ($userRole === "Admin") {
        $countStmt->bind_param("i", $userId);
    }

    if (!$countStmt->execute()) {
        throw new Exception("Execute failed for count query: " . $countStmt->error);
    }

    $countResult = $countStmt->get_result();
    $totalRatings = $countResult->fetch_assoc()['total'];
    $countStmt->close();

    $totalPages = ceil($totalRatings / $limit);
    logActivity("Total ratings found: $totalRatings, Total pages: $totalPages");

    // Prepare data query with pagination
    $dataQuery = $baseQuery . $orderBy . " LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($dataQuery);
    if (!$stmt) {
        throw new Exception("Prepare failed for data query: " . $conn->error);
    }

    if ($userRole === "Admin") {
        $stmt->bind_param("iii", $userId, $limit, $offset);
    } else {
        $stmt->bind_param("ii", $limit, $offset);
    }

    if (!$stmt->execute()) {
        throw new Exception("Execute failed for data query: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $ratings = [];

    while ($row = $result->fetch_assoc()) {
        $ratings[] = $row;
    }

    $conn->commit();
    logActivity("Successfully retrieved " . count($ratings) . " rating(s)");

    echo json_encode([
        'success' => true,
        'ratings' => $ratings,
        'pagination' => [
            'total' => $totalRatings,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => $totalPages,
            'hasNext' => $page < $totalPages,
            'hasPrev' => $page > 1
        ],
        'requested_by' => $userId,
        'user_role' => $userRole,
        'timestamp' => date('c')
    ]);

    logActivity("Rating listing fetch completed successfully");

} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }

    $errorMsg = "Error fetching rating listing: " . $e->getMessage();
    logActivity($errorMsg);

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch ratings',
        'error' => $e->getMessage()
    ]);
} finally {
    if ($stmt) {
        $stmt->close();
    }
    if ($conn) {
        $conn->close();
    }
    logActivity("Rating listing fetch process completed");
}


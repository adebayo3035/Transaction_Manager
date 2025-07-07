<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

// Initialize variables for cleanup
$stmt = null;

// Initialize logging
logActivity("Group listing fetch process started");

try {
    // Check authentication
    if (!isset($_SESSION['unique_id'])) {
        $errorMsg = "Unauthenticated access attempt to group listing";
        logActivity($errorMsg);
        echo json_encode(["success" => false, "message" => "Not logged in."]);
        exit();
    }

    $userId = $_SESSION['unique_id'];
    $userRole = $_SESSION['role'] ?? null;
    logActivity("Request initiated by user ID: $userId (Role: $userRole)");

    // Pagination setup
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 10;
    $offset = ($page - 1) * $limit;

    try {
        $conn->begin_transaction();
        logActivity("Database transaction started");

        // Get total count of groups
        $countQuery = "SELECT COUNT(*) as total FROM groups";
        $countResult = $conn->query($countQuery);
        $totalCount = (int) $countResult->fetch_assoc()['total'];

        // Fetch paginated groups
        $query = "SELECT group_id, group_name FROM groups ORDER BY group_id ASC LIMIT ? OFFSET ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed for group listing: " . $conn->error);
        }

        $stmt->bind_param("ii", $limit, $offset);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed for group listing: " . $stmt->error);
        }

        $result = $stmt->get_result();
        $groups = [];

        while ($row = $result->fetch_assoc()) {
            $groups[] = $row;
        }

        $conn->commit();
        logActivity("Successfully retrieved " . count($groups) . " paginated groups");

        // Prepare paginated response
        $response = [
            'success' => true,
            'groups' => $groups,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $totalCount,
                'total_pages' => ceil($totalCount / $limit)
            ],
            'requested_by' => $userId,
            'user_role' => $userRole,
            'timestamp' => date('c')
        ];

        echo json_encode($response);
        logActivity("Group listing fetch completed successfully");

    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
        }
        throw $e;
    }
} catch (Exception $e) {
    $errorMsg = "Error fetching group listing: " . $e->getMessage();
    logActivity($errorMsg);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch groups',
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
    logActivity("Group listing fetch process completed");
}

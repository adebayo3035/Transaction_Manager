<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

logActivity("Food listing fetch process started");

// Authentication check
if (!isset($_SESSION['unique_id'])) {
    logActivity("Unauthenticated access attempt to food listing");
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}

$userId = $_SESSION['unique_id'];
$userRole = $_SESSION['role'] ?? null;
logActivity("Request initiated by user ID: $userId (Role: $userRole)");

$stmt = null;

try {
    // Get pagination inputs with default values
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 10;
    $offset = ($page - 1) * $limit;

    $conn->begin_transaction();
    logActivity("Database transaction started");

    // 1. Get total number of records
    $countResult = $conn->query("SELECT COUNT(*) AS total FROM food");
    $totalRows = $countResult->fetch_assoc()['total'];

    // 2. Fetch paginated results
    $query = "SELECT * FROM food ORDER BY food_name ASC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);

    $stmt->bind_param("ii", $limit, $offset);
    if (!$stmt->execute()) throw new Exception("Execute failed: " . $stmt->error);

    $result = $stmt->get_result();
    $foods = [];

    while ($row = $result->fetch_assoc()) {
        if (isset($row['price'])) {
            $row['price_formatted'] = number_format((float)$row['price'], 2);
        }
        $foods[] = $row;
    }

    $conn->commit();
    logActivity("Retrieved " . count($foods) . " food items");

    echo json_encode([
        'success' => true,
        'foods' => $foods,
        'count' => count($foods),
        'pagination' => [
            'total' => (int)$totalRows,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($totalRows / $limit)
        ],
        'requested_by' => $userId,
        'user_role' => $userRole,
        'timestamp' => date('c')
    ]);

    logActivity("Food listing fetch completed successfully");

} catch (Exception $e) {
    $conn->rollback();
    logActivity("Error fetching food listing: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch food listing',
        'error' => $e->getMessage()
    ]);
} finally {
    if ($stmt instanceof mysqli_stmt) $stmt->close();
    if ($conn instanceof mysqli) $conn->close();
    logActivity("Food listing fetch process completed");
}

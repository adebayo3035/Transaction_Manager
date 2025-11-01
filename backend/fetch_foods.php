<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

logActivity("=== Food listing fetch process initiated ===");

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
    logActivity("Pagination parameters -> Page: $page, Limit: $limit, Offset: $offset");

    // Get filters and sanitize
    $priceMin = isset($_GET['price_min']) ? (float) $_GET['price_min'] : null;
    $priceMax = isset($_GET['price_max']) ? (float) $_GET['price_max'] : null;
    $availability = isset($_GET['availability_status']) ? trim($_GET['availability_status']) : null;

    logActivity("Raw filters received -> price_min: " . var_export($priceMin, true) . 
                ", price_max: : " . var_export($priceMax, true) . 
                ", availability_status: " . var_export($availability, true));

    // Validate inputs
    if ($priceMin !== null && $priceMin < 0) {
        throw new Exception("Invalid price_min: must be >= 0");
    }
    if ($priceMax !== null && $priceMax < 0) {
        throw new Exception("Invalid price_max: must be >= 0");
    }
    if ($priceMin !== null && $priceMax !== null && $priceMin > $priceMax) {
        throw new Exception("Invalid price range: min cannot exceed max");
    }
    if ($availability !== null && !in_array($availability, ['0','1'], true)) {
        throw new Exception("Invalid availability_status: must be 0 or 1");
    }

    logActivity("Filters validated successfully");

    $conn->begin_transaction();
    logActivity("Database transaction started");

    // Build dynamic conditions
    $whereClauses = [];
    $types = "";
    $values = [];

    if ($priceMin !== null) {
        $whereClauses[] = "food_price >= ?";
        $types .= "d";
        $values[] = $priceMin;
    }
    if ($priceMax !== null) {
        $whereClauses[] = "food_price <= ?";
        $types .= "d";
        $values[] = $priceMax;
    }
    if ($availability !== null) {
        $whereClauses[] = "availability_status = ?";
        $types .= "i";
        $values[] = (int) $availability;
    }

    $whereSQL = count($whereClauses) > 0 ? "WHERE " . implode(" AND ", $whereClauses) : "";
    logActivity("Constructed WHERE clause: " . ($whereSQL ?: "none"));

    // Count query
    $countSql = "SELECT COUNT(*) AS total FROM food $whereSQL";
    $countStmt = $conn->prepare($countSql);
    if (!$countStmt) throw new Exception("Count prepare failed: " . $conn->error);

    if ($values) {
        $countStmt->bind_param($types, ...$values);
    }
    logActivity("Executing count query...");
    if (!$countStmt->execute()) throw new Exception("Count execute failed: " . $countStmt->error);

    $countResult = $countStmt->get_result();
    $totalRows = $countResult->fetch_assoc()['total'] ?? 0;
    logActivity("Total matching rows: $totalRows");
    $countStmt->close();

    // Main query - FIXED: Properly handle parameter binding
    $query = "SELECT * FROM food $whereSQL ORDER BY food_name ASC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) throw new Exception("Main query prepare failed: " . $conn->error);

    // Combine all parameters for the main query
    $allParams = $values; // Start with filter values
    $allTypes = $types;   // Start with filter types
    
    // Add pagination parameters
    $allTypes .= "ii";    // Add types for limit and offset (both integers)
    $allParams[] = $limit;
    $allParams[] = $offset;

    logActivity("Main query params -> Types: $allTypes, Values: " . json_encode($allParams));

    // Bind parameters if we have any
    if (!empty($allParams)) {
        $stmt->bind_param($allTypes, ...$allParams);
    }

    logActivity("Executing main query...");
    if (!$stmt->execute()) throw new Exception("Main query execute failed: " . $stmt->error);

    $result = $stmt->get_result();
    $foods = [];

    while ($row = $result->fetch_assoc()) {
        if (isset($row['food_price'])) {
            $row['price_formatted'] = number_format((float)$row['food_price'], 2);
        }
        $foods[] = $row;
    }

    $conn->commit();
    logActivity("Query executed successfully, rows fetched: " . count($foods));

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
        'filters' => [
            'price_min' => $priceMin,
            'price_max' => $priceMax,
            'availability_status' => $availability
        ],
        'timestamp' => date('c')
    ]);

    logActivity("Response sent successfully to user $userId");

} catch (Exception $e) {
    $conn->rollback();
    logActivity("Error during fetch process: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch food listing',
        'error' => $e->getMessage()
    ]);
} finally {
    if ($stmt instanceof mysqli_stmt) $stmt->close();
    if ($conn instanceof mysqli) $conn->close();
    logActivity("=== Food listing fetch process completed ===");
}
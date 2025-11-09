<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

logActivity("Driver listing fetch process started");

try {
    // ✅ Authentication check
    if (!isset($_SESSION['unique_id'])) {
        logActivity("Unauthenticated access attempt to driver listing");
        echo json_encode(["success" => false, "message" => "Not logged in."]);
        exit;
    }

    $adminId = $_SESSION['unique_id'];
    $userRole = $_SESSION['role'] ?? null;
    logActivity("Request initiated by admin ID: $adminId (Role: $userRole)");

    // ✅ Pagination
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int) ($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;

    // ✅ Filters
    $status = trim($_GET['status'] ?? '');
    $restriction = trim($_GET['restriction'] ?? '');
    $delete_status = trim($_GET['delete_status'] ?? '');

    // Allowed values
    $allowedStatuses = ['Available', 'Not Available'];
    $allowedRestriction = ['0', '1'];
    $allowedDeleteStatus = ['Yes', 'NULL'];

    // Validate
    if ($status && !in_array($status, $allowedStatuses, true)) {
        throw new Exception("Invalid status value");
    }
    if ($restriction !== '' && !in_array($restriction, $allowedRestriction, true)) {
        throw new Exception("Invalid restriction value");
    }
    if ($delete_status && !in_array($delete_status, $allowedDeleteStatus, true)) {
        throw new Exception("Invalid delete_status value");
    }

    // ✅ Build dynamic WHERE clause
    $filters = buildFilters($status, $restriction, $delete_status);
    $whereSql = $filters['sql'] ? "WHERE {$filters['sql']}" : "";

    $conn->begin_transaction();

    // ✅ Count total drivers
    $countSql = "SELECT COUNT(*) AS total FROM driver $whereSql";
    $countStmt = $conn->prepare($countSql);
    if (!$countStmt) throw new Exception("Count query prepare failed: " . $conn->error);

    if ($filters['types']) {
        $countStmt->bind_param($filters['types'], ...$filters['params']);
    }
    $countStmt->execute();
    $total = $countStmt->get_result()->fetch_assoc()['total'] ?? 0;
    $totalPages = ceil($total / $limit);
    logActivity("Total drivers found: $total | Filters: " . json_encode($filters));

    // ✅ Fetch drivers
    $query = "SELECT 
                id, firstname, lastname, email, phone_number, 
                license_number, status, restriction, delete_status, date_updated 
              FROM driver
              $whereSql
              ORDER BY restriction DESC, status DESC, date_updated DESC 
              LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) throw new Exception("Data query prepare failed: " . $conn->error);

    // Add pagination to binding
    $types = $filters['types'] . "ii";
    $params = array_merge($filters['params'], [$limit, $offset]);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    $drivers = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['fullname'] = "{$row['firstname']} {$row['lastname']}";
        $row['phone_formatted'] = formatPhoneNumber($row['phone_number']);
        $row['license_masked'] = maskSensitiveData($row['license_number']);
        $drivers[] = $row;
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'drivers' => $drivers,
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
    ]);

    logActivity("Driver listing fetch completed successfully");

} catch (Exception $e) {
    if (isset($conn) && $conn->errno === 0) $conn->rollback();
    $errorMsg = "Error fetching driver listing: " . $e->getMessage();
    logActivity($errorMsg);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    if (isset($stmt) && $stmt instanceof mysqli_stmt) $stmt->close();
    if (isset($countStmt) && $countStmt instanceof mysqli_stmt) $countStmt->close();
    if (isset($conn)) $conn->close();
    logActivity("Driver listing fetch process completed");
}

/* ------------------------------------------
   🔧 Helper: Build Dynamic WHERE Clause
------------------------------------------ */
function buildFilters($status, $restriction, $delete_status)
{
    $where = [];
    $params = [];
    $types = "";

    if ($status) {
        $where[] = "status = ?";
        $params[] = $status;
        $types .= "s";
    }

    if ($restriction !== '') {
        $where[] = "restriction = ?";
        $params[] = (int) $restriction;
        $types .= "i";
    }

    if ($delete_status) {
        if ($delete_status === 'NULL') {
            $where[] = "delete_status IS NULL";
        } else {
            $where[] = "delete_status = ?";
            $params[] = $delete_status;
            $types .= "s";
        }
    }

    return [
        'sql' => implode(" AND ", $where),
        'params' => $params,
        'types' => $types
    ];
}

/* ------------------------------------------
   📱 Helper: Format phone number
------------------------------------------ */
function formatPhoneNumber($phone)
{
    $phone = preg_replace('/\D/', '', $phone);
    return strlen($phone) === 10
        ? '(' . substr($phone, 0, 3) . ') ' . substr($phone, 3, 3) . '-' . substr($phone, 6)
        : $phone;
}

/* ------------------------------------------
   🔒 Helper: Mask sensitive license data
------------------------------------------ */
function maskSensitiveData($data, $visibleChars = 4)
{
    $len = strlen($data);
    return $len <= $visibleChars * 2
        ? substr($data, 0, $visibleChars) . str_repeat('*', $len - $visibleChars)
        : substr($data, 0, $visibleChars) . str_repeat('*', $len - ($visibleChars * 2)) . substr($data, -$visibleChars);
}

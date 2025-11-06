<?php
session_start();
require 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['unique_id']) || $_SESSION['role'] !== 'Super Admin') {
    $userId = $_SESSION['unique_id'] ?? 'Guest';
    logActivity("Unauthorized attempt by user ID: {$userId} to access deleted staff records.");
    http_response_code(403);
    echo json_encode(["error" => "Access denied. Only Super Admins can view deleted staff."]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

$admin_id = $_SESSION['unique_id'];
logActivity("Super Admin ID {$admin_id} is retrieving deleted staff records.");

try {
    // Pagination
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
    $offset = ($page - 1) * $limit;

    // Filter parameters
    $deactivator = isset($_GET['deactivator']) ? trim($_GET['deactivator']) : '';
    $reactivator = isset($_GET['reactivator']) ? trim($_GET['reactivator']) : '';
    $deactivationStatus = isset($_GET['deactivationStatus']) ? trim($_GET['deactivationStatus']) : '';

    // Log filter parameters
    logActivity("Filters applied - Deactivator: {$deactivator}, Reactivator: {$reactivator}, Status: {$deactivationStatus} by Admin ID: {$admin_id}");

    // Build WHERE clauses for filters
    $whereClauses = [];
    $params = [];
    $types = '';

    // Deactivator filter
    if (!empty($deactivator)) {
        $whereClauses[] = "d.deactivated_by = ?";
        $params[] = $deactivator;
        $types .= 's';
    }

    // Reactivator filter
    if (!empty($reactivator)) {
        $whereClauses[] = "l.reactivated_by = ?";
        $params[] = $reactivator;
        $types .= 's';
    }

    // Status filter
    if (!empty($deactivationStatus)) {
        if ($deactivationStatus === 'deactivated') {
            $whereClauses[] = "l.reactivated_by IS NULL";
        } elseif ($deactivationStatus === 'reactivated') {
            $whereClauses[] = "l.reactivated_by IS NOT NULL";
        } elseif ($deactivationStatus === 'declined') {
            $whereClauses[] = "l.status = 'Declined'";
        }
    }

    // Build WHERE clause
    $whereSQL = '';
    if (!empty($whereClauses)) {
        $whereSQL = "WHERE " . implode(" AND ", $whereClauses);
    }

    // Count query with filters
    $countQuery = "
        SELECT COUNT(*) AS total 
        FROM admin_deactivation_logs d
        INNER JOIN admin_tbl a ON d.admin_id = a.unique_id
        LEFT JOIN admin_reactivation_logs l ON d.id = l.deactivation_log_id
        {$whereSQL}
    ";

    $stmt = $conn->prepare($countQuery);
    if (!$stmt) {
        throw new Exception("Failed to prepare count query: " . $conn->error);
    }

    // Bind parameters for count query if any
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $totalRow = $result->fetch_assoc();
    $total = $totalRow['total'];
    $stmt->close();

    // Main query with filters - BASIC DETAILS ONLY
    $query = "
        SELECT 
            d.id AS deactivation_id,
            d.date_created AS deactivation_date,
            d.reason AS deactivation_reason,
            d.deactivated_by,
            deactivator.firstname AS deactivated_by_firstname,
            deactivator.lastname AS deactivated_by_lastname,
            
            a.unique_id AS staff_id,
            a.firstname AS staff_firstname,
            a.lastname AS staff_lastname,
            a.email AS staff_email,
            
            COALESCE(l.status, 'No Reactivation Request') AS reactivation_status,
            l.date_created AS reactivation_date,
            l.reactivated_by as reactivated_by,
            l.date_last_updated as date_last_updated,
            reactivator.firstname AS reactivated_by_firstname,
            reactivator.lastname AS reactivated_by_lastname
            
        FROM admin_deactivation_logs d
        INNER JOIN admin_tbl a ON d.admin_id = a.unique_id
        LEFT JOIN admin_reactivation_logs l ON d.id = l.deactivation_log_id
        LEFT JOIN admin_tbl deactivator ON d.deactivated_by = deactivator.unique_id
        LEFT JOIN admin_tbl reactivator ON l.reactivated_by = reactivator.unique_id
        {$whereSQL}
        ORDER BY d.id DESC, l.date_last_updated DESC
        LIMIT ? OFFSET ?
    ";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Failed to prepare main query: " . $conn->error);
    }

    // Add pagination parameters to existing params
    $paramsWithPagination = $params;
    $paramsWithPagination[] = $limit;
    $paramsWithPagination[] = $offset;

    $stmtTypes = $types . 'ii';
    
    // Bind parameters
    if (!empty($paramsWithPagination)) {
        $stmt->bind_param($stmtTypes, ...$paramsWithPagination);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $deletedStaff = [];
    while ($row = $result->fetch_assoc()) {
        $deletedStaff[] = [
            'deactivation_id' => $row['deactivation_id'],
            'deactivation_date' => $row['deactivation_date'],
            'deactivation_reason' => $row['deactivation_reason'] ?? null,
            
            'staff' => [
                'staff_id' => $row['staff_id'],
                'firstname' => $row['staff_firstname'],
                'lastname' => $row['staff_lastname'],
                'email' => $row['staff_email']
            ],
            
            'reactivation_status' => $row['reactivation_status'],
            'reactivation_date' => $row['reactivation_date'] ?? null,
            'date_last_updated' => $row['date_last_updated'] ?? null,
            
            'deactivated_by' => [
                'admin_id' => $row['deactivated_by'],
                'name' => trim($row['deactivated_by_firstname'] . ' ' . $row['deactivated_by_lastname'])
            ],
            
            'reactivated_by' => [
                'admin_id' => $row['reactivated_by'] ?? null,
                'name' => isset($row['reactivated_by_firstname']) 
                         ? trim($row['reactivated_by_firstname'] . ' ' . $row['reactivated_by_lastname'])
                         : null
            ]
        ];
    }

    $stmt->close();

    // Also fetch Super Admins for dropdown population
    $superAdminsQuery = "SELECT unique_id, firstname, lastname FROM admin_tbl 
                        WHERE role = 'Super Admin' 
                        AND (delete_status IS NULL OR delete_status != 'Yes') 
                        ORDER BY firstname, lastname";
    
    $superAdminsStmt = $conn->prepare($superAdminsQuery);
    $superAdmins = [];
    
    if ($superAdminsStmt) {
        $superAdminsStmt->execute();
        $superAdminsResult = $superAdminsStmt->get_result();
        while ($admin = $superAdminsResult->fetch_assoc()) {
            $superAdmins[] = $admin;
        }
        $superAdminsStmt->close();
    }

    logActivity("Super Admin ID: $admin_id fetched basic deactivation list with filters. Found {$total} records.");
    
    echo json_encode([
        'success' => true,
        'message' => 'Deactivated Staff Account records fetched successfully',
        'deletedStaff' => $deletedStaff,
        'super_admins' => $superAdmins,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'filters' => [
            'deactivator' => $deactivator,
            'reactivator' => $reactivator,
            'deactivationStatus' => $deactivationStatus
        ]
    ]);

} catch (Exception $e) {
    logActivity("Error fetching basic deactivation list by Admin ID: $admin_id - " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Internal Server Error: " . $e->getMessage()]);
}

$conn->close();

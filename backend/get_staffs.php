<?php
include 'config.php';
session_start();

try {
    if (!isset($_SESSION['unique_id'])) {
        logActivity("Unauthorized access attempt: No session found.");
        echo json_encode(["success" => false, "message" => "Not logged in."]);
        exit();
    }

    $adminId = $_SESSION['unique_id'];
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = ($page - 1) * $limit;
    $loggedInUserRole = $_SESSION['role'] ?? 'Unknown';

    // Connect to database
    if (!$conn) {
        logActivity("Database connection failed.");
        echo json_encode(["success" => false, "message" => "Database connection error."]);
        exit();
    }

    // Fetch total count of active (non-deleted) staff
    $totalQuery = "SELECT COUNT(*) as total FROM admin_tbl WHERE delete_status != 'Yes' OR delete_status IS NULL";
    $stmt = $conn->prepare($totalQuery);

    if (!$stmt) {
        logActivity("Failed to prepare total staff count query: " . $conn->error);
        throw new Exception("Database error.");
    }

    $stmt->execute();
    $totalResult = $stmt->get_result();
    $totalStaffs = $totalResult->fetch_assoc()['total'] ?? 0;
    $stmt->close();
    logActivity("Fetched total staff count: $totalStaffs.");

    // Fetch paginated staff list
    $query = "SELECT 
                admin_tbl.*, 
                admin_active_sessions.status AS admin_status
            FROM 
                admin_tbl
            LEFT JOIN 
                admin_active_sessions 
            ON 
                admin_tbl.unique_id = admin_active_sessions.unique_id 
            -- WHERE 
            --     admin_tbl.delete_status != 'Yes' 
            --     OR admin_tbl.delete_status IS NULL
            ORDER BY 
                admin_tbl.role ASC, admin_tbl.id DESC
            LIMIT ? OFFSET ?";

    $stmt = $conn->prepare($query);

    if (!$stmt) {
        logActivity("Failed to prepare staff fetch query: " . $conn->error);
        throw new Exception("Database error.");
    }

    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $staffs = [];
    while ($row = $result->fetch_assoc()) {
        $staffs[] = $row;
    }

    logActivity("Fetched " . count($staffs) . " staff records for page $page.");

    $stmt->close();
    $conn->close();

    echo json_encode([
        "success" => true,
        "staffs" => $staffs,
        "total" => $totalStaffs,
        "page" => $page,
        "limit" => $limit,
        "logged_in_user_role" => $loggedInUserRole
    ]);
    logActivity("Staff listing successfully sent to client.");

} catch (Exception $e) {
    if (isset($conn) && $conn->connect_errno == 0) {
        $conn->close();
    }
    logActivity("Error occurred: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "An unexpected error occurred."]);
    exit();
}


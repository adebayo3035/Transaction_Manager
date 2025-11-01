<?php
session_start();
require 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['unique_id']) || $_SESSION['role'] !== 'Super Admin') {
    $userId = $_SESSION['unique_id'] ?? 'Guest';
    logActivity("Unauthorized attempt by user ID: {$userId} to access deactivation details.");
    http_response_code(403);
    echo json_encode(["error" => "Access denied. Only Super Admins can view deactivation details."]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

$admin_id = $_SESSION['unique_id'];

// Get deactivation ID from request
$deactivation_id = isset($_GET['deactivation_id']) ? (int) $_GET['deactivation_id'] : 0;

if ($deactivation_id <= 0) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid deactivation ID"]);
    exit;
}

logActivity("Super Admin ID {$admin_id} is retrieving details for deactivation ID: {$deactivation_id}");

try {
    // Detailed query with comprehensive information
    $query = "
        SELECT 
            d.id AS deactivation_id,
            d.date_created AS deactivation_date,
            d.reason AS deactivation_reason,
            d.additional_notes AS deactivation_notes,
            d.deactivated_by,
            deactivator.firstname AS deactivated_by_firstname,
            deactivator.lastname AS deactivated_by_lastname,
            deactivator.email AS deactivated_by_email,
            deactivator.role AS deactivated_by_role,
            
            a.unique_id AS staff_id,
            a.firstname AS staff_firstname,
            a.lastname AS staff_lastname,
            a.email AS staff_email,
            a.phone AS staff_phone,
            a.gender AS staff_gender,
            a.role AS staff_role,
            a.date_created AS staff_join_date,
            a.restriction_id AS staff_restriction,
            a.block_id AS staff_block_status,
            
            l.id AS reactivation_log_id,
            l.status AS reactivation_status,
            l.reactivation_reason,
            l.additional_notes AS reactivation_notes,
            l.date_created AS reactivation_date,
            l.date_last_updated AS reactivation_updated_date,
            l.reactivated_by,
            reactivator.firstname AS reactivated_by_firstname,
            reactivator.lastname AS reactivated_by_lastname,
            reactivator.email AS reactivated_by_email,
            reactivator.role AS reactivated_by_role,
            
            -- Additional details for comprehensive view
            d.deactivation_data,
            l.reactivation_data
            
        FROM admin_deactivation_logs d
        INNER JOIN admin_tbl a ON d.admin_id = a.unique_id
        LEFT JOIN admin_reactivation_logs l ON d.id = l.deactivation_log_id
        LEFT JOIN admin_tbl deactivator ON d.deactivated_by = deactivator.unique_id
        LEFT JOIN admin_tbl reactivator ON l.reactivated_by = reactivator.unique_id
        WHERE d.id = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Failed to prepare details query: " . $conn->error);
    }

    $stmt->bind_param("i", $deactivation_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        http_response_code(404);
        echo json_encode(["error" => "Deactivation record not found"]);
        exit;
    }

    $row = $result->fetch_assoc();
    $stmt->close();

    // Format the detailed response
    $detailedRecord = [
        'deactivation' => [
            'id' => $row['deactivation_id'],
            'date' => $row['deactivation_date'],
            'reason' => $row['deactivation_reason'],
            'additional_notes' => $row['deactivation_notes'],
            'data' => $row['deactivation_data'] ? json_decode($row['deactivation_data'], true) : null
        ],
        
        'staff' => [
            'id' => $row['staff_id'],
            'firstname' => $row['staff_firstname'],
            'lastname' => $row['staff_lastname'],
            'email' => $row['staff_email'],
            'phone' => $row['staff_phone'],
            'gender' => $row['staff_gender'],
            'role' => $row['staff_role'],
            'join_date' => $row['staff_join_date'],
            'restriction_status' => $row['staff_restriction'],
            'block_status' => $row['staff_block_status']
        ],
        
        'deactivated_by' => [
            'admin_id' => $row['deactivated_by'],
            'name' => trim($row['deactivated_by_firstname'] . ' ' . $row['deactivated_by_lastname']),
            'email' => $row['deactivated_by_email'],
            'role' => $row['deactivated_by_role']
        ],
        
        'reactivation' => $row['reactivation_log_id'] ? [
            'id' => $row['reactivation_log_id'],
            'status' => $row['reactivation_status'],
            'reason' => $row['reactivation_reason'],
            'additional_notes' => $row['reactivation_notes'],
            'request_date' => $row['reactivation_date'],
            'updated_date' => $row['reactivation_updated_date'],
            'data' => $row['reactivation_data'] ? json_decode($row['reactivation_data'], true) : null
        ] : null,
        
        'reactivated_by' => $row['reactivated_by'] ? [
            'admin_id' => $row['reactivated_by'],
            'name' => trim($row['reactivated_by_firstname'] . ' ' . $row['reactivated_by_lastname']),
            'email' => $row['reactivated_by_email'],
            'role' => $row['reactivated_by_role']
        ] : null
    ];

    logActivity("Super Admin ID: $admin_id successfully retrieved details for deactivation ID: {$deactivation_id}");
    
    echo json_encode([
        'success' => true,
        'message' => 'Deactivation details fetched successfully',
        'record' => $detailedRecord
    ]);

} catch (Exception $e) {
    logActivity("Error fetching deactivation details by Admin ID: $admin_id for record {$deactivation_id} - " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Internal Server Error: " . $e->getMessage()]);
}

$conn->close();
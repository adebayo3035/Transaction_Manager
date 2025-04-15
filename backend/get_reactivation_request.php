<?php
session_start();
require 'config.php';
header('Content-Type: application/json');

// Only Super Admin can access
if (!isset($_SESSION['unique_id']) || $_SESSION['role'] !== 'Super Admin') {
    http_response_code(403);
    echo json_encode(["error" => "Unauthorized access"]);
    exit;
}

$staff_id = $_GET['staff_id'] ?? null;
$reactivation_id = $_GET['reactivation_id'] ?? null;

if ((!$staff_id) || (!$reactivation_id)) {
    http_response_code(400);
    echo json_encode(["error" => "Missing Required Parameters"]);
    exit;
}

try {
    // Base query (same as original)
    $query = "
        SELECT 
            d.id AS deactivation_id,
            d.deactivated_by,
            d.date_created AS deactivation_date,
            d.reason AS deactivation_reason,
            -- Deactivator's details
            deactivator.unique_id AS deactivator_id,
            deactivator.firstname AS deactivator_firstname,
            deactivator.lastname AS deactivator_lastname,
            -- Staff details
            staff.unique_id AS staff_id,
            staff.firstname AS staff_firstname,
            staff.lastname AS staff_lastname,
            -- Reactivation details
            r.id AS reactivation_id,
            r.status,
            r.comment,
            r.reactivation_reason,
            r.date_created AS reactivation_date,
            -- Reactivator details
            reactivator.firstname AS reactivated_by_firstname,
            reactivator.lastname AS reactivated_by_lastname
        FROM admin_deactivation_logs d
        JOIN admin_tbl staff ON d.admin_id = staff.unique_id
        LEFT JOIN admin_tbl deactivator ON d.deactivated_by = deactivator.unique_id
        LEFT JOIN admin_reactivation_logs r ON d.id = r.deactivation_log_id
        LEFT JOIN admin_tbl reactivator ON r.reactivated_by = reactivator.unique_id
        WHERE d.admin_id = ?
    ";

    // Add reactivation_id filter if provided
    if ($reactivation_id) {
        $query .= " AND r.id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $staff_id, $reactivation_id);
    } else {
        // Maintain original behavior: get most recent record
        $query .= " ORDER BY r.id DESC LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $staff_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    if (!$data) {
        http_response_code(404);
        echo json_encode(["error" => "Deactivation record not found"]);
        exit;
    }

    // EXACT ORIGINAL RESPONSE STRUCTURE
    $response = [
        "success" => true,
        "data" => [
            "deactivation" => [
                "id" => $data['deactivation_id'],
                "date" => $data['deactivation_date'] ?? null,
                "deactivation_reason" => $data['deactivation_reason']
            ],
            "staff" => [
                "id" => $data['staff_id'],
                "firstname" => $data['staff_firstname'] ?? '',
                "lastname" => $data['staff_lastname'] ?? ''
            ],
            "deactivator" => $data['deactivated_by'] ? [
                "id" => $data['deactivator_id'] ?? '',
                "firstname" => $data['deactivator_firstname'] ?? '',
                "lastname" => $data['deactivator_lastname'] ?? ''
            ] : null,
            "reactivation" => $data['reactivation_id'] ? [
                "id" => $data['reactivation_id'],
                "status" => $data['status'] ?? '',
                "comment" => $data['comment'] ?? '',
                "date" => $data['reactivation_date'] ?? null,
                "reactivation_reason" => $data['reactivation_reason'],
                "reactivated_by" => isset($data['reactivated_by_firstname']) ? [
                    "firstname" => $data['reactivated_by_firstname'] ?? '',
                    "lastname" => $data['reactivated_by_lastname'] ?? ''
                ] : null
            ] : null
        ]
    ];

    echo json_encode($response);

} catch (Exception $e) {
    error_log("Error fetching details: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Server error"]);
}
<?php
session_start();
require 'config.php';
header('Content-Type: application/json');

// Initialize logging
logActivity("Staff deactivation/reactivation details fetch process started");

// Initialize variables for cleanup
$stmt = null;

try {
    // Check authentication and authorization
    if (!isset($_SESSION['unique_id'])) {
        $errorMsg = "Unauthenticated access attempt";
        logActivity($errorMsg);
        http_response_code(401);
        echo json_encode(["error" => "Unauthorized access"]);
        exit;
    }

    if ($_SESSION['role'] !== 'Super Admin') {
        $errorMsg = "Unauthorized role attempt: " . $_SESSION['role'];
        logActivity($errorMsg);
        http_response_code(403);
        echo json_encode(["error" => "Insufficient privileges"]);
        exit;
    }

    $adminId = $_SESSION['unique_id'];
    logActivity("Request initiated by Super Admin ID: $adminId");

    // Validate and sanitize input parameters
    $staff_id = filter_var($_GET['staff_id'] ?? null, FILTER_VALIDATE_INT);
    $reactivation_id = filter_var($_GET['reactivation_id'] ?? null, FILTER_VALIDATE_INT);

    if (!$staff_id || $staff_id < 1) {
        $errorMsg = "Invalid staff_id parameter";
        logActivity($errorMsg);
        http_response_code(400);
        echo json_encode(["error" => "Invalid staff ID"]);
        exit;
    }

    logActivity("Processing details for staff ID: $staff_id" . ($reactivation_id ? " and reactivation ID: $reactivation_id" : ""));

    try {
        // Start transaction for consistent data view
        $conn->begin_transaction();
        logActivity("Database transaction started");

        // Build query with parameterized conditions
        $query = "SELECT 
                    d.id AS deactivation_id,
                    d.deactivated_by,
                    d.date_created AS deactivation_date,
                    d.reason AS deactivation_reason,
                    deactivator.unique_id AS deactivator_id,
                    deactivator.firstname AS deactivator_firstname,
                    deactivator.lastname AS deactivator_lastname,
                    staff.unique_id AS staff_id,
                    staff.firstname AS staff_firstname,
                    staff.lastname AS staff_lastname,
                    r.id AS reactivation_id,
                    r.status,
                    r.comment,
                    r.reactivation_reason,
                    r.date_created AS reactivation_date,
                    reactivator.firstname AS reactivated_by_firstname,
                    reactivator.lastname AS reactivated_by_lastname
                  FROM admin_deactivation_logs d
                  JOIN admin_tbl staff ON d.admin_id = staff.unique_id
                  LEFT JOIN admin_tbl deactivator ON d.deactivated_by = deactivator.unique_id
                  LEFT JOIN admin_reactivation_logs r ON d.id = r.deactivation_log_id
                  LEFT JOIN admin_tbl reactivator ON r.reactivated_by = reactivator.unique_id
                  WHERE d.admin_id = ?";
        
        // Add reactivation_id filter if provided
        $params = [$staff_id];
        $paramTypes = "i";
        
        if ($reactivation_id) {
            $query .= " AND r.id = ?";
            $params[] = $reactivation_id;
            $paramTypes .= "i";
        } else {
            $query .= " ORDER BY r.id DESC LIMIT 1";
        }

        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        // Dynamic parameter binding
        $stmt->bind_param($paramTypes, ...$params);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $result = $stmt->get_result();
        $data = $result->fetch_assoc();

        if (!$data) {
            logActivity("No records found for staff ID: $staff_id");
            http_response_code(404);
            echo json_encode(["error" => "Deactivation record not found"]);
            exit;
        }

        $conn->commit();
        logActivity("Successfully retrieved record for staff ID: $staff_id");

        // Prepare response with original structure
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
        logActivity("Details fetch completed successfully");

    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
        }
        throw $e;
    }
} catch (Exception $e) {
    $errorMsg = "Error fetching details: " . $e->getMessage();
    logActivity($errorMsg);
    error_log($errorMsg);
    http_response_code(500);
    echo json_encode(["error" => "Server error"]);
} finally {
    // Clean up resources
    if (isset($stmt)) {
        $stmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
    logActivity("Staff deactivation/reactivation details fetch process completed");
}
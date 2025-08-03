<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

// Initialize variables for cleanup
$stmt = null;
$countStmt = null;

// Initialize logging
logActivity("Driver listing fetch process started");

try {
    // Check authentication
    if (!isset($_SESSION['unique_id'])) {
        $errorMsg = "Unauthenticated access attempt to driver listing";
        logActivity($errorMsg);
        echo json_encode(["success" => false, "message" => "Not logged in."]);
        exit();
    }

    $adminId = $_SESSION['unique_id'];
    $userRole = $_SESSION['role'] ?? null;
    logActivity("Request initiated by admin ID: $adminId (Role: $userRole)");

    // Validate and sanitize pagination parameters
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    
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
    logActivity("Fetching drivers - Page: $page, Limit: $limit, Offset: $offset");

    try {
        // Start transaction for consistent data view
        $conn->begin_transaction();
        logActivity("Database transaction started");

        // Fetch total count of drivers
        $totalQuery = "SELECT COUNT(*) as total FROM driver";
        $countStmt = $conn->prepare($totalQuery);
        if (!$countStmt) {
            throw new Exception("Prepare failed for count query: " . $conn->error);
        }

        if (!$countStmt->execute()) {
            throw new Exception("Execute failed for count query: " . $countStmt->error);
        }

        $totalResult = $countStmt->get_result();
        $totalDrivers = $totalResult->fetch_assoc()['total'];
        $totalPages = ceil($totalDrivers / $limit);
        logActivity("Total drivers found: $totalDrivers, Total pages: $totalPages");

        // Fetch paginated drivers with essential fields only
        $query = "SELECT 
                    id,
                    firstname, 
                    lastname, 
                    email, 
                    phone_number, 
                    license_number,
                    status,
                    restriction,
                    delete_status,
                    date_updated 
                  FROM driver
                  ORDER BY restriction DESC, status DESC, date_updated DESC 
                  LIMIT ? OFFSET ?";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed for data query: " . $conn->error);
        }

        $stmt->bind_param("ii", $limit, $offset);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed for data query: " . $stmt->error);
        }

        $result = $stmt->get_result();
        $drivers = [];

        while ($row = $result->fetch_assoc()) {
            // Format data for better display
            $row['fullname'] = $row['firstname'] . ' ' . $row['lastname'];
            $row['phone_formatted'] = formatPhoneNumber($row['phone_number']);
            $row['license_masked'] = maskSensitiveData($row['license_number']);
            $drivers[] = $row;
        }

        $conn->commit();
        logActivity("Successfully retrieved " . count($drivers) . " drivers");

        // Prepare response
        $response = [
            'success' => true,
            'drivers' => $drivers,
            'pagination' => [
                'total' => $totalDrivers,
                'page' => $page,
                'limit' => $limit,
                'totalPages' => $totalPages,
                'hasNext' => $page < $totalPages,
                'hasPrev' => $page > 1
            ],
            'requested_by' => $adminId,
            'user_role' => $userRole,
            'timestamp' => date('c')
        ];

        echo json_encode($response);
        logActivity("Driver listing fetch completed successfully");

    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
        }
        throw $e;
    }
} catch (Exception $e) {
    $errorMsg = "Error fetching driver listing: " . $e->getMessage();
    logActivity($errorMsg);
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to fetch drivers',
        'error' => $e->getMessage()
    ]);
} finally {
    // Clean up resources
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    if (isset($countStmt) && $countStmt instanceof mysqli_stmt) {
        $countStmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
    logActivity("Driver listing fetch process completed");
}

// Helper function to format phone numbers
function formatPhoneNumber($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) == 10) {
        return '(' . substr($phone, 0, 3) . ') ' . substr($phone, 3, 3) . '-' . substr($phone, 6);
    }
    return $phone;
}

// Helper function to mask sensitive data
function maskSensitiveData($data, $visibleChars = 4) {
    $length = strlen($data);
    if ($length <= $visibleChars * 2) {
        return substr($data, 0, $visibleChars) . str_repeat('*', $length - $visibleChars);
    }
    return substr($data, 0, $visibleChars) . str_repeat('*', $length - ($visibleChars * 2)) . substr($data, -$visibleChars);
}
<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

// Initialize variables for cleanup
$stmt = null;
$countStmt = null;

// Initialize logging
logActivity("Customer listing fetch process started");

try {
    // Check authentication
    if (!isset($_SESSION['unique_id'])) {
        $errorMsg = "Unauthenticated access attempt to customer listing";
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
    logActivity("Fetching customers - Page: $page, Limit: $limit, Offset: $offset");

    try {
        // Start transaction for consistent data view
        $conn->begin_transaction();
        logActivity("Database transaction started");

        // Fetch total count of customers
        $totalQuery = "SELECT COUNT(*) as total FROM customers";
        $countStmt = $conn->prepare($totalQuery);
        
        if (!$countStmt) {
            throw new Exception("Prepare failed for count query: " . $conn->error);
        }

        if (!$countStmt->execute()) {
            throw new Exception("Execute failed for count query: " . $countStmt->error);
        }

        $totalResult = $countStmt->get_result();
        $totalCustomers = $totalResult->fetch_assoc()['total'];
        $totalPages = ceil($totalCustomers / $limit);
        logActivity("Total customers found: $totalCustomers, Total pages: $totalPages");

        // Fetch paginated customers
        $query = "SELECT 
                    customer_id, 
                    firstname, 
                    lastname, 
                    gender,
                    restriction,
                    delete_status,
                    date_updated 
                  FROM customers 
                  ORDER BY date_updated DESC 
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
        $customers = [];

        while ($row = $result->fetch_assoc()) {
            // Format phone number if needed
            if (isset($row['mobile_number'])) {
                $row['formatted_phone'] = formatPhoneNumber($row['mobile_number']);
            }
            $row['fullname'] = $row['firstname'] . ' ' . $row['lastname'];
            $customers[] = $row;
        }

        $conn->commit();
        logActivity("Successfully retrieved " . count($customers) . " customers");

        // Prepare response
        $response = [
            'success' => true,
            'customers' => $customers,
            'pagination' => [
                'total' => $totalCustomers,
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
        logActivity("Customer listing fetch completed successfully");

    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
        }
        throw $e;
    }
} catch (Exception $e) {
    $errorMsg = "Error fetching customer listing: " . $e->getMessage();
    logActivity($errorMsg);
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to fetch customers',
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
    logActivity("Customer listing fetch process completed");
}

// Helper function to format phone numbers
function formatPhoneNumber($phone) {
    // Remove all non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Format as (XXX) XXX-XXXX
    if (strlen($phone) == 10) {
        return '(' . substr($phone, 0, 3) . ') ' . substr($phone, 3, 3) . '-' . substr($phone, 6);
    }
    
    return $phone;
}
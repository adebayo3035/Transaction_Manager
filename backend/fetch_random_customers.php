<?php
header('Content-Type: application/json');
require 'config.php';

// Initialize variables for cleanup
$result = null;

try {
    // Initialize logging
    logActivity("Random customer names fetch process started");

    // Start transaction for consistent data view
    $conn->begin_transaction();
    logActivity("Database transaction started");

    // Fetch random customer names
    $query = "SELECT customer_id, firstname, lastname FROM customers ORDER BY RAND() LIMIT 3";
    $result = $conn->query($query);
    
    if ($result === false) {
        throw new Exception("Database query failed: " . $conn->error);
    }

    $customerNames = [];
    while ($row = $result->fetch_assoc()) {
        // Format names consistently
        $row['fullname'] = ucwords(strtolower($row['firstname'] . ' ' . $row['lastname']));
        $customerNames[] = $row;
    }

    $conn->commit();
    logActivity("Successfully retrieved " . count($customerNames) . " random customer names");

    // Prepare response
    $response = [
        'success' => true,
        'customer_names' => $customerNames,
        'count' => count($customerNames),
        'timestamp' => date('c')
    ];

    echo json_encode($response);
    logActivity("Random customer names fetch completed successfully");

} catch (Exception $e) {
    // Rollback transaction if it was started
    if (isset($conn)) {
        $conn->rollback();
    }
    
    $errorMsg = "Error fetching random customer names: " . $e->getMessage();
    logActivity($errorMsg);
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to fetch customer names',
        'error' => $e->getMessage()
    ]);
} finally {
    // Clean up resources
    if (isset($result)) {
        $result->free();
    }
    if (isset($conn)) {
        $conn->close();
    }
    logActivity("Random customer names fetch process completed");
}
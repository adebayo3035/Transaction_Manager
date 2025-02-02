<?php
header('Content-Type: application/json');

// Include database connection file
include('config.php');
include('activity_logger.php'); // Include the logger file

// Log the start of the script
logActivity("Fetching random customer names script started.");

try {
    // Fetch random customer names
    $query = "SELECT firstname, lastname FROM customers ORDER BY RAND() LIMIT 3";
    $result = $conn->query($query);

    if ($result === false) {
        // Log database query error
        $error_message = "Database query failed: " . $conn->error;
        error_log($error_message);
        logActivity($error_message);
        throw new Exception("An error occurred while fetching customer names.");
    }

    $customer_names = [];
    while ($row = $result->fetch_assoc()) {
        $customer_names[] = $row;
    }

    // Log the successful retrieval of customer names
    logActivity("Successfully fetched " . count($customer_names) . " random customer names.");

    // Return the result as JSON
    echo json_encode(["success" => true, "customer_names" => $customer_names]);
} catch (Exception $e) {
    // Log the exception
    error_log("Exception: " . $e->getMessage());
    logActivity("Exception: " . $e->getMessage());

    // Return an error response
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}

// Close the database connection
$conn->close();

// Log the end of the script
logActivity("Fetching random customer names script completed.");
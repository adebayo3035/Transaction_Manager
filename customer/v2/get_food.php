<?php
// Include database connection and logger files
include 'config.php';
include 'activity_logger.php'; // Include the logger file
session_start();

// Log the start of the script
logActivity("Fetching food items script started.");

// Check if the customer is logged in
if (!isset($_SESSION['customer_id'])) {
    $error_message = "Customer not logged in. Session customer_id not found.";
    error_log($error_message);
    logActivity($error_message);
    echo json_encode(["success" => false, "message" => $error_message]);
    exit();
}

// Log the customer ID for debugging
logActivity("Customer ID found in session: " . $_SESSION['customer_id']);

try {
    // Fetch food items from the database
    $sql = "SELECT food_id, food_name, food_price FROM food WHERE availability_status != 0 AND available_quantity != 0;";
    $result = mysqli_query($conn, $sql);

    if (!$result) {
        throw new Exception("Database query failed: " . mysqli_error($conn));
    }

    $foodItems = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $foodItems[] = $row;
    }

    // Log the number of food items fetched
    logActivity("Fetched " . count($foodItems) . " food items.");

    // Return the food items as JSON
    header('Content-Type: application/json');
    echo json_encode($foodItems);

    // Log the successful completion of the script
    logActivity("Fetching food items script completed successfully.");
} catch (Exception $e) {
    // Log the exception
    error_log("Exception: " . $e->getMessage());
    logActivity("Exception: " . $e->getMessage());

    // Return an error response
    header('Content-Type: application/json');
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
} finally {
    // Close the database connection
    mysqli_close($conn);
}
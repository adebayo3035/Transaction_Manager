<?php
// Include database connection and logger files
include 'config.php';

session_start();
$customerId = $_SESSION['customer_id'];
logActivity("Srarting session Check for new User");
checkSession($customerId);
// Log the customer ID for debugging
logActivity("Customer ID found in session: " . $_SESSION['customer_id']);
// Log the start of the script with timestamp
logActivity("Fetching food items script started at " . date("Y-m-d H:i:s"));

try {
    // Fetch food items from the database
    $sql = "SELECT food_id, food_name, food_price FROM food WHERE availability_status != 0 AND available_quantity != 0;";
    logActivity("Executing query: $sql");

    $result = mysqli_query($conn, $sql);

    if (!$result) {
        logActivity("Database query failed: " . mysqli_error($conn));
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
    logActivity("Fetching food items script completed successfully at " . date("Y-m-d H:i:s"));
} catch (Exception $e) {
    // Log the exception with the message
    error_log("ERROR: Exception occurred - " . $e->getMessage());
    logActivity("ERROR: Exception occurred - " . $e->getMessage());
    
    // Return an error response
    header('Content-Type: application/json');
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
} finally {
    // Close the database connection
    mysqli_close($conn);
    logActivity("Database connection closed at " . date("Y-m-d H:i:s"));
}

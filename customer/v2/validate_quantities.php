<?php
include 'config.php';
include 'activity_logger.php'; // Include the logger file

// Log the start of the script
logActivity("Order quantity validation script started.");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Log the request method
    logActivity("POST request received.");

    // Decode the JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    // Check if order items are provided
    if (!isset($input['order_items'])) {
        $error_message = "No order items found in the request.";
        error_log($error_message);
        logActivity($error_message);
        echo json_encode(["success" => false, "message" => $error_message]);
        exit();
    }

    $orderItems = $input['order_items'];

    // Validate the order items format
    if (!is_array($orderItems)) {
        $error_message = "Invalid order items format.";
        error_log($error_message);
        logActivity($error_message);
        echo json_encode(["success" => false, "message" => $error_message]);
        exit();
    }

    // Log the number of order items received
    logActivity("Received " . count($orderItems) . " order items for validation.");

    try {
        // Validate the quantity of each order item
        foreach ($orderItems as $item) {
            $food_id = $item['food_id'];
            $quantity = $item['quantity'];

            // Fetch available quantity and food name from the database
            $query = "SELECT available_quantity, food_name FROM food WHERE food_id = ?";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Failed to prepare query: " . $conn->error);
            }
            $stmt->bind_param("i", $food_id);
            $stmt->execute();
            $stmt->bind_result($available_quantity, $food_name);
            $stmt->fetch();
            $stmt->close();

            // Calculate the available food quantity (rounded down to the nearest integer)
            $available_food = round($available_quantity / 1.5);

            // Log the available and requested quantities for debugging
            logActivity("Food ID: $food_id, Food Name: $food_name, Available Quantity: $available_food, Requested Quantity: $quantity");

            // Check if the requested quantity exceeds the available quantity
            if ($quantity > $available_food) {
                $error_message = "The requested quantity for $food_name is not available. Available quantity is: $available_food";
                error_log($error_message);
                logActivity($error_message);
                echo json_encode(["success" => false, "message" => $error_message]);
                exit();
            }
        }

        // Log successful validation
        logActivity("All order items have valid quantities.");

        // Return success response
        echo json_encode(["success" => true, "message" => "Quantities are valid."]);
    } catch (Exception $e) {
        // Log the exception
        error_log("Exception: " . $e->getMessage());
        logActivity("Exception: " . $e->getMessage());

        // Return an error response
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
} else {
    // Log invalid request method
    $error_message = "Invalid request method. Expected POST.";
    error_log($error_message);
    logActivity($error_message);

    // Return error response
    echo json_encode(["success" => false, "message" => $error_message]);
}

// Log the end of the script
logActivity("Order quantity validation script completed.");
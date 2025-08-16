<?php
session_start();
include 'config.php';
$customerId = $_SESSION["customer_id"];
checkSession($customerId);
logActivity("Order quantity validation script started.");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $requiredFields = ['order_items', 'pack_count'];
    foreach ($requiredFields as $field) {
        if (!isset($input[$field])) {
            $error_message = "Missing required field: $field";
            error_log($error_message);
            logActivity($error_message);
            echo json_encode(["success" => false, "message" => $error_message]);
            exit();
        }
    }

    $orderItems = $input['order_items'];
    $packCount = $input['pack_count'];

    // Validate pack count consistency
    $uniquePacks = array_unique(array_column($orderItems, 'pack_id'));
    if (count($uniquePacks) > $packCount) {
        $error_message = "More packs detected than declared pack count";
        error_log($error_message);
        logActivity($error_message);
        echo json_encode(["success" => false, "message" => $error_message]);
        exit();
    }

    // Validate order items structure
    $requiredItemFields = ['food_id', 'quantity', 'pack_id'];
    foreach ($orderItems as $item) {
        foreach ($requiredItemFields as $field) {
            if (!isset($item[$field])) {
                $error_message = "Missing $field in order item";
                error_log($error_message);
                logActivity($error_message);
                echo json_encode(["success" => false, "message" => $error_message]);
                exit();
            }
        }
    }

    try {
        // Prepare batch query for better performance
        $foodIds = array_unique(array_column($orderItems, 'food_id'));
        $placeholders = implode(',', array_fill(0, count($foodIds), '?'));
        
        $query = "SELECT food_id, available_quantity, food_name FROM food 
                 WHERE food_id IN ($placeholders)";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Failed to prepare query: " . $conn->error);
        }
        
        // Bind parameters dynamically
        $types = str_repeat('i', count($foodIds));
        $stmt->bind_param($types, ...$foodIds);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Create lookup array of food items
        $foodItems = [];
        while ($row = $result->fetch_assoc()) {
            $foodItems[$row['food_id']] = [
                'available' => round($row['available_quantity'] / 1.5),
                'name' => $row['food_name']
            ];
        }
        $stmt->close();

        // Validate quantities per pack
        $packQuantities = [];
        foreach ($orderItems as $item) {
            $foodId = $item['food_id'];
            $quantity = $item['quantity'];
            $packId = $item['pack_id'];
            
            if (!isset($foodItems[$foodId])) {
                throw new Exception("Invalid food ID: $foodId");
            }
            
            $available = $foodItems[$foodId]['available'];
            $foodName = $foodItems[$foodId]['name'];
            
            // Track quantities per pack
            if (!isset($packQuantities[$packId][$foodId])) {
                $packQuantities[$packId][$foodId] = 0;
            }
            $packQuantities[$packId][$foodId] += $quantity;
            
            // Validate against available quantity
            if ($packQuantities[$packId][$foodId] > $available) {
                $error_message = "Insufficient quantity for $foodName in $packId (Available: $available)";
                throw new Exception($error_message);
            }
        }

        // Return success with pack information
        echo json_encode([
            "success" => true,
            "message" => "Quantities are valid",
            "pack_count" => $packCount,
            "unique_packs" => $uniquePacks
        ]);
        
    } catch (Exception $e) {
        error_log("Exception: " . $e->getMessage());
        logActivity("Exception: " . $e->getMessage());
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
} else {
    $error_message = "Invalid request method. Expected POST.";
    error_log($error_message);
    logActivity($error_message);
    echo json_encode(["success" => false, "message" => $error_message]);
}

logActivity("Order quantity validation script completed.");
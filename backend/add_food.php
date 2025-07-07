<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

// include("config.php");
include('restriction_checker.php');

// Initialize logging
logActivity("Food item addition process started");

try {
    // Get and sanitize input data
    $data = json_decode(file_get_contents('php://input'), true);
    $inputFields = [
        'food_name' => trim($data['food_name'] ?? ''),
        'food_description' => trim($data['food_description'] ?? ''),
        'food_price' => trim($data['food_price'] ?? ''),
        'food_quantity' => trim($data['food_quantity'] ?? ''),
        'available_status' => trim($data['available_status'] ?? '')
    ];

    logActivity("Received input data: " . json_encode($inputFields));

    // Validate required fields
    $requiredFields = ['food_name', 'food_description', 'food_price', 'food_quantity', 'available_status'];
    $missingFields = [];

    foreach ($requiredFields as $field) {
        if (empty($inputFields[$field])) {
            $missingFields[] = $field;
        }
    }

    if (!empty($missingFields)) {
        $errorMsg = 'Missing required fields: ' . implode(', ', $missingFields);
        logActivity("Validation failed - " . $errorMsg);
        echo json_encode(["success" => false, "message" => $errorMsg]);
        exit();
    }

    // Validate price format
    if (!preg_match('/^\d+(\.\d{1,2})?$/', $inputFields['food_price'])) {
        $errorMsg = 'Invalid price amount: ' . $inputFields['food_price'];
        logActivity($errorMsg);
        echo json_encode(["success" => false, "message" => $errorMsg]);
        exit();
    }

    // Validate quantity format
    if (!preg_match('/^\d+$/', $inputFields['food_quantity'])) {
        $errorMsg = 'Invalid quantity: ' . $inputFields['food_quantity'];
        logActivity($errorMsg);
        echo json_encode(["success" => false, "message" => $errorMsg]);
        exit();
    }

    // Function to calculate the similarity percentage between two strings
    function levenshteinPercentage($str1, $str2) {
        $lev = levenshtein($str1, $str2);
        $maxLen = max(strlen($str1), strlen($str2));
        return ($maxLen == 0) ? 100 : (1 - $lev / $maxLen) * 100;
    }

    // Function to check if a similar food name exists
    function isSimilarFoodExists($conn, $newFoodName, $threshold = 50) {
        logActivity("Checking for similar food items to: " . $newFoodName);
        $stmt = $conn->prepare("SELECT food_name FROM food WHERE food_name LIKE ?");
        $searchTerm = '%' . $newFoodName . '%';
        $stmt->bind_param("s", $searchTerm);
        
        if (!$stmt->execute()) {
            logActivity("Database error in similarity check: " . $stmt->error);
            return false;
        }
        
        $result = $stmt->get_result();
        $similarItems = [];

        while ($row = $result->fetch_assoc()) {
            $similarity = levenshteinPercentage($row['food_name'], $newFoodName);
            $similarItems[$row['food_name']] = $similarity;
            
            if ($similarity >= $threshold) {
                logActivity("Found similar food item: " . $row['food_name'] . " (similarity: " . $similarity . "%)");
                $stmt->close();
                return true;
            }
        }
        
        logActivity("No similar food items found above threshold. Similarities: " . json_encode($similarItems));
        $stmt->close();
        return false;
    }

    // Check for similar food name
    if (isSimilarFoodExists($conn, $inputFields['food_name'])) {
        $errorMsg = "A similar food name exists: " . $inputFields['food_name'];
        logActivity($errorMsg);
        echo json_encode(["success" => false, "message" => $errorMsg]);
        exit();
    }

    // Prepare the SQL statement
    $stmt = $conn->prepare("INSERT INTO food (food_name, food_description, food_price, available_quantity, availability_status) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("ssdis", 
        $inputFields['food_name'],
        $inputFields['food_description'],
        $inputFields['food_price'],
        $inputFields['food_quantity'],
        $inputFields['available_status']
    );

    // Execute the statement
    if ($stmt->execute()) {
        $successMsg = "Food item added successfully: " . $inputFields['food_name'];
        logActivity($successMsg);
        echo json_encode(["success" => true, "message" => $successMsg]);
    } else {
        throw new Exception("Execute failed: " . $stmt->error);
    }

} catch (Exception $e) {
    $errorMsg = "Error adding food item: " . $e->getMessage();
    logActivity($errorMsg);
    echo json_encode(["success" => false, "message" => $errorMsg]);
} finally {
    // Clean up resources
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    $conn->close();
    logActivity("Food addition process completed");
}
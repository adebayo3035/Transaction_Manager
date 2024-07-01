<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

include ("config.php");
// Create connection
// $conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die(json_encode(["success" => false, "message" => "Connection failed: " . $conn->connect_error]));
}

$data = json_decode(file_get_contents('php://input'), true);

$food_name = $data['name'];
$description = $data['description'];
$price = $data['price'];
$quantity = $data['quantity'];
$available = $data['available'];


function levenshteinPercentage($str1, $str2)
{
    $lev = levenshtein($str1, $str2);
    $maxLen = max(strlen($str1), strlen($str2));
    if ($maxLen == 0) {
        return 100; // Both strings are empty
    }
    return (1 - $lev / $maxLen) * 100;
}


// Function to check if a similar group exists
function isSimilarGroupExists($conn, $newFoodName, $threshold = 50)
{
    $sql = "SELECT food_name FROM food WHERE food_name LIKE '%$newFoodName%'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $existingFoodName = $row['food_name'];
            if (levenshteinPercentage($existingFoodName, $newFoodName) >= $threshold) {
                return true;
            }
        }
    }
    return false;
}

if (!empty($food_name)) {
    // $sql_check = mysqli_query($conn, "SELECT * FROM groups WHERE group_name = '{$group_name}'");

    if (isSimilarGroupExists($conn, $food_name)) {
        echo "A similar food name exists. Please Try another Name.";
        exit();
    } else {
        $sql = "INSERT INTO food (food_name, food_description, food_price, available_quantity, availability_status) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssdis", $food_name, $description, $price, $quantity, $available);

        if ($stmt->execute()) {
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "message" => "Failed to add food item: " . $stmt->error]);
        }

        $stmt->close();
        $conn->close();
    }
}







<?php
header('Content-Type: application/json');
include_once "config.php";

// Function to calculate the Levenshtein similarity percentage
function levenshteinPercentage($str1, $str2) {
    $lev = levenshtein($str1, $str2);
    $maxLen = max(strlen($str1), strlen($str2));
    if ($maxLen == 0) {
        return 100; // Both strings are empty
    }
    return (1 - $lev / $maxLen) * 100;
}

$group_id = mysqli_real_escape_string($conn, $_POST['add_group_name']);
$unit_name = mysqli_real_escape_string($conn, $_POST['add_unit_name']);

// Function to check if a similar group exists
function isSimilarUnitExists($conn, $newUnitName, $threshold = 50) {
    $sql = "SELECT unit_name FROM unit WHERE unit_name LIKE ?";
    $likeQuery = '%' . $newUnitName . '%';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $likeQuery);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $existingUnitName = $row['unit_name'];
            if (levenshteinPercentage($existingUnitName, $newUnitName) >= $threshold) {
                return true;
            }
        }
    }
    return false;
}

if (!empty($unit_name) || !empty($group_id)) {
    // Check for similar group names
    if (isSimilarUnitExists($conn, $unit_name)) {
        echo json_encode([
            "success" => false,
            "message" => "A similar Unit name exists. Please try another name."
        ]);
        exit();
    } 

        // Insert new group using a prepared statement
        $sql = "INSERT INTO unit (unit_name, group_id) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si', $unit_name, $group_id);

        if ($stmt->execute()) {
            // Retrieve the newly inserted group to confirm the addition
            $sql_select = "SELECT * FROM unit WHERE unit_name = ?";
            $stmt_select = $conn->prepare($sql_select);
            $stmt_select->bind_param('s', $unit_name);
            $stmt_select->execute();
            $result = $stmt_select->get_result();

            if ($result->num_rows > 0) {
                $unit = $result->fetch_assoc();
                echo json_encode([
                    "success" => true,
                    "message" => "New Unit has been Successfully created.",
                    "unit" => $unit
                ]);
            } else {
                echo json_encode([
                    "success" => false,
                    "message" => "Something went wrong, please try again."
                ]);
            }
        } else {
            echo json_encode([
                "success" => false,
                "message" => "Something went wrong, please try again.",
                "error" => $stmt->error
            ]);
        }

        $stmt->close();
 
}else {
    echo json_encode([
        "success" => false,
        "message" => "Please provide a Group or Unit name."
    ]);
}
$conn->close();

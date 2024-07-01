<?php
header('Content-Type: application/json');

// Include your database connection file
include "config.php";

if (!isset($_GET['id'])) {
    echo json_encode(["success" => false, "message" => "Food ID is required."]);
    exit();
}

$id = $conn->real_escape_string($_GET['id']);

$sql = "DELETE FROM food WHERE food_id='$id'";

if ($conn->query($sql) === TRUE) {
    echo json_encode(["success" => true, "message" => "Food item deleted successfully."]);
} else {
    echo json_encode(["success" => false, "message" => "Error deleting record: " . $conn->error]);
}

$conn->close();


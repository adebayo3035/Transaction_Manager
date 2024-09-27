<?php
header('Content-Type: application/json');
include('config.php');

// $data = json_decode(file_get_contents('php://input'), true);


// Prepare the query
    $query = "SELECT * FROM food";
    $stmt = $conn->prepare($query);
    // $stmt->bind_param("i", $group_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $foods = [];

    // Fetch the units
    while ($row = $result->fetch_assoc()) {
        $foods[] = $row;
    }

    if (!empty($foods)) {
        echo json_encode(['success' => true, 'foods' => $foods]);
    } else {
        echo json_encode(['success' => true, 'foods' => []]); // Return an empty array if no units
    }

    $stmt->close();

$conn->close();

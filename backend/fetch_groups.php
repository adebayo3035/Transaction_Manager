<?php
// header('Content-Type: application/json');
include('config.php');

// $data = json_decode(file_get_contents('php://input'), true);


// Prepare the query
    $query = "SELECT group_id, group_name FROM groups";
    $stmt = $conn->prepare($query);
    // $stmt->bind_param("i", $group_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $groups = [];

    // Fetch the units
    while ($row = $result->fetch_assoc()) {
        $groups[] = $row;
    }

    if (!empty($groups)) {
        echo json_encode(['success' => true, 'groups' => $groups]);
    } else {
        echo json_encode(['success' => true, 'groups' => []]); // Return an empty array if no units
    }

    $stmt->close();

$conn->close();

<?php
// header('Content-Type: application/json');
include('config.php');

// Prepare the query
    $query = "SELECT * FROM revenue_types";
    $stmt = $conn->prepare($query);
    // $stmt->bind_param("i", $revenue_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $revenueTypes = [];

    // Fetch the units
    while ($row = $result->fetch_assoc()) {
        $revenueTypes[] = $row;
    }

    if (!empty($revenueTypes)) {
        echo json_encode(['success' => true, 'revenueTypes' => $revenueTypes]);
    } else {
        echo json_encode(['success' => true, 'revenueTypes' => []]); // Return an empty array if no units
    }

    $stmt->close();

$conn->close();

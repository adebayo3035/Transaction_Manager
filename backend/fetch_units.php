<?php
header('Content-Type: application/json');
include('config.php');

$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['group_id'])) {
    $group_id = $data['group_id'];

    // Prepare the query
    $query = "SELECT unit_id, unit_name FROM unit WHERE group_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $units = [];

    // Fetch the units
    while ($row = $result->fetch_assoc()) {
        $units[] = $row;
    }

    if (!empty($units)) {
        echo json_encode(['success' => true, 'units' => $units]);
    } else {
        echo json_encode(['success' => true, 'units' => []]); // Return an empty array if no units
    }

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid group ID.']);
}

$conn->close();

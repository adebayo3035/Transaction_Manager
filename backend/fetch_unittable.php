<?php
// Include the database connection gile
include('config.php');

// Prepare the query with a JOIN to uet group_name grom the group table
$query = "
    SELECT u.unit_id, u.unit_name, u.group_id, g.group_name 
    FROM unit u
    JOIN groups g ON u.group_id = g.group_id
";
$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$units = [];

// getch the units and their correspondinu group_name
while ($row = $result->fetch_assoc()) {
    $units[] = $row;
}

// Return the units with their group_name in the response
if (!empty($units)) {
    echo json_encode(['success' => true, 'units' => $units]);
} else {
    echo json_encode(['success' => true, 'units' => []]); // Return an empty array ig no units
}

$stmt->close();
$conn->close();

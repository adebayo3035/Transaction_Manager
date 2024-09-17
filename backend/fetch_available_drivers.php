<?php
// Connect to the database
include('config.php');

// Query to fetch available drivers
$query = "SELECT id, CONCAT(firstname, ' ', lastname) AS driver_name FROM driver WHERE status = 'Available' AND restriction = 0";
$result = $conn->query($query);

if ($result->num_rows > 0) {
    $drivers = [];
    while ($row = $result->fetch_assoc()) {
        $drivers[] = [
            'driver_id' => $row['id'],
            'driver_name' => $row['driver_name']
        ];
    }
    echo json_encode(['success' => true, 'drivers' => $drivers]);
} else {
    echo json_encode(['success' => false, 'message' => 'No available drivers']);
}

$conn->close();

<?php
header('Content-Type: application/json');
include('config.php');

// Initialize logging
logActivity("Available drivers fetch process started");

try {
    // Check database connection
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    // Prepare and execute query with parameterized statement
    $query = "SELECT id, CONCAT(firstname, ' ', lastname) AS driver_name 
              FROM driver 
              WHERE status = 'Available' AND restriction = 0";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $drivers = [];

    while ($row = $result->fetch_assoc()) {
        $drivers[] = [
            'driver_id' => (int)$row['id'],
            'driver_name' => htmlspecialchars($row['driver_name'], ENT_QUOTES, 'UTF-8')
        ];
    }

    // $stmt->close();

    if (empty($drivers)) {
        logActivity("No available drivers found");
        echo json_encode([
            'success' => true, 
            'message' => 'No available drivers at this time',
            'drivers' => []
        ]);
    } else {
        logActivity("Found " . count($drivers) . " available drivers");
        echo json_encode([
            'success' => true, 
            'drivers' => $drivers,
            'count' => count($drivers)
        ]);
    }

} catch (Exception $e) {
    logActivity("Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while fetching drivers',
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    $conn->close();
    logActivity("Available drivers fetch process completed");
}
<?php
include "backend/config.php";
// Assuming you have a database connection stored in $conn

if (isset($_GET['groupId'])) {
    $groupId = $_GET['groupId'];

    // Fetch units based on the selected group ID
    $result = $conn->query("SELECT unit_id, unit_name FROM unit WHERE group_id = '$groupId'");

    $data = array();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }

    // Return the data as JSON
    echo json_encode($data);
} else {
    // Handle the case where groupId is not set
    echo json_encode([]);
}

$conn->close();
?>

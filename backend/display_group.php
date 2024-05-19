<?php
// Include your database connection file
include "config.php";

// Fetch data from the database
$query = "SELECT * FROM groups";
$result = $conn->query($query);
$staff_role = $_SESSION['role'];

// Check if there are rows in the result
if ($result->num_rows > 0) {
    // Output data of each row
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['group_id'] . "</td>";
        echo "<td>" . $row['group_name'] . "</td>";

        // Display edit and Delete Icons Only for Super Admin
        require_once('check_role2.php');
        $path1 = 'edit_group.php';
        $path2 = 'delete_group.php';
        DisplayEditIcon($path1,$staff_role, $row['group_id']);
        DisplayDeleteIcon($path2,$staff_role, $row['group_id']);
        echo "</tr>";

    }
} else {
    echo "<tr><td colspan='5'>No records found</td></tr>";
}

// Close the database connection
$conn->close();

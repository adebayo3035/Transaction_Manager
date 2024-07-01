<?php
// Include your database connection file
include "config.php";

// Fetch data from the unit table
$query = "SELECT * FROM unit";
$result = $conn->query($query);

// Get Staff Role from session
$staff_role = $_SESSION['role'];

// Check if there are rows in the result
if ($result->num_rows > 0) {
    // Output data of each row
    while ($row = $result->fetch_assoc()) {
        // Fetch the corresponding group record
        $select_sql2 = mysqli_query($conn, "SELECT * FROM groups WHERE group_id = '{$row['group_id']}'");
        $group_found = false;
        $group_name = "Group not found";

        // Check if the group record exists
        if ($row2 = mysqli_fetch_assoc($select_sql2)) {
            $group_found = true;
            $group_name = $row2['group_name'];
        }

        // Display unit information along with the group name (or "Group not found")
        echo "<tr>";
        echo "<td>" . $row['unit_id'] . "</td>";
        echo "<td>" . $row['unit_name'] . "</td>";
        echo "<td>" . $group_name . "</td>";

        // Display edit and delete icons
        require_once('check_role2.php');
        $path1 = 'edit_unit.php';
        $path2 = 'backend/delete_unit.php';
        DisplayEditIcon($path1, $staff_role, $row['unit_id']);
        DisplayDeleteIcon($path2, $staff_role, $row['unit_id']);
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='5'>No records found</td></tr>";
}

// Close the database connection
$conn->close();
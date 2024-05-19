<?php
// Include your database connection file
include "config.php";

// Fetch data from the database


$query = "SELECT * FROM team";
$result = $conn->query($query);

// Get Staff Role from session
$staff_role = $_SESSION['role'];

// Check if there are rows in the result
if ($result->num_rows > 0) {
    // Output data of each row
    while ($row = $result->fetch_assoc()) {
        $select_sql2 = mysqli_query($conn, "SELECT * FROM groups WHERE group_id = '{$row['group_id']}'");
        while ($row2 = mysqli_fetch_assoc($select_sql2)) {
           
            echo "<tr>";
            echo "<td>" . $row['team_id'] . "</td>";
            echo "<td>" . $row2['group_name'] . "</td>";
            echo "<td>" . $row['team_name'] . "</td>";
            
            // Display edit and Delete Icons Only for Super Admin
            require_once ('check_role2.php');
            require_once('check_role2.php');
            $path1 = 'edit_team.php';
            $path2 = 'delete_team.php';
            DisplayEditIcon($path1,$staff_role, $row['team_id']);
            DisplayDeleteIcon($path2,$staff_role, $row['team_id']);
            echo "</tr>";
            
          }

    }
} else {
    echo "<tr><td colspan='5'>No records found</td></tr>";
}

// Close the database connection
$conn->close();

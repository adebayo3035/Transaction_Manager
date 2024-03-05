<?php
// Include your database connection file
include "config.php";

// Fetch data from the database


$query = "SELECT * FROM team";
$result = $conn->query($query);

// Check if there are rows in the result
if ($result->num_rows > 0) {
    // Output data of each row
    while ($row = $result->fetch_assoc()) {
        $select_sql2 = mysqli_query($conn, "SELECT * FROM groups WHERE group_id = '{$row['group_id']}'");
        while ($row2 = mysqli_fetch_assoc($select_sql2)) {
            echo "<tr>";
            echo "<td>" . $row['team_id'] . "</td>";
            echo "<td>" . $row['team_name'] . "</td>";
            echo "<td>" . $row2['group_name'] . "</td>";
            // echo "<td>" . $row['group_name'] . "</td>";

            echo "<td><a href='edit.php?id=" . $row['team_id'] . "'><span class='edit-icon'>&#9998;</span></a></td>";
            echo "<td><a href='delete.php?id=" . $row['team_id'] . "'><span class='delete-icon'>&#128465;</span></a></td>";
            echo "</tr>";
          }

    }
} else {
    echo "<tr><td colspan='5'>No records found</td></tr>";
}

// Close the database connection
$conn->close();

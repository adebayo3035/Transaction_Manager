<?php
// Include your database connection file
include "config.php";

// Fetch data from the unit table
$query = "SELECT * FROM customers";
$result = $conn->query($query);

// Check if there are rows in the result
if ($result->num_rows > 0) {
    // Output data of each row
    while ($row = $result->fetch_assoc()) {
        // Fetch the corresponding group record
        $select_sql2 = mysqli_query($conn, "SELECT * FROM groups WHERE group_id = '{$row['group_id']}'");
        $select_sql3 = mysqli_query($conn, "SELECT * FROM unit WHERE unit_id = '{$row['unit_id']}'");
        $group_found = false;
        $unit_found = false;
        $group_name = "Group not found";
        $unit_name = "Unit not found";

        // Check if the group record exists
        if ($row2 = mysqli_fetch_assoc($select_sql2)) {
            $group_found = true;
            $group_name = $row2['group_name'];
        }
        // Check if Unit record exists
        if ($row3 = mysqli_fetch_assoc($select_sql3)) {
            $unit_found = true;
            $unit_name = $row3['unit_name'];
        }

        // Display customer information along with the group name and Unit name (or "Group not found")
        echo "<tr>";
            echo "<td>" . $row['customer_id'] . "</td>";
            echo "<td>" . $row['firstname'] ." ". $row['lastname'] . "</td>";
            echo "<td>" . $row['gender'] . "</td>";
            echo "<td>" . $row['email'] . "</td>";
            echo "<td>" . $row['mobile_number'] . "</td>";
            echo "<td>" . $group_name . "</td>";
            echo "<td>" . $unit_name . "</td>";

            echo "<td><a href='edit_customer.php?id=" . $row['customer_id'] . "'><span class='edit-icon'>&#9998;</span></a></td>";
            // echo "<td><a href='delete_customer.php?id=" . $row['customer_id'] . "'><span class='delete-icon'>&#128465;</span></a></td>";
            echo '<td><span class="delete-icon" onclick="confirmDelete(\'' . $row['customer_id'] . '\')">&#128465;</span></td>';
            echo "</tr>";
    }
} else {
    echo "<tr><td colspan='9'>No records found</td></tr>";
}

// Close the database connection
$conn->close();
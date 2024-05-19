<?php
// Include your database connection file
include "config.php";

// Fetch data from the database
$query = "SELECT * FROM customers";
$result = $conn->query($query);

// Check if there are rows in the result
if ($result->num_rows > 0) {
    // Output data of each row
    while ($row = $result->fetch_assoc()) {
        $select_sql2 = mysqli_query($conn, "SELECT * FROM groups WHERE group_id = '{$row['group_id']}'");
        $select_sql3 = mysqli_query($conn, "SELECT * FROM unit WHERE unit_id = '{$row['unit_id']}'");
        while (($row2 = mysqli_fetch_assoc($select_sql2)) && ($row3 = mysqli_fetch_assoc($select_sql3)) ){
            echo "<tr>";
            echo "<td>" . $row['customer_id'] . "</td>";
            echo "<td>" . $row['firstname'] ." ". $row['lastname'] . "</td>";
            echo "<td>" . $row['gender'] . "</td>";
            echo "<td>" . $row['email'] . "</td>";
            echo "<td>" . $row['mobile_number'] . "</td>";
            echo "<td>" . $row2['group_name'] . "</td>";
            echo "<td>" . $row3['unit_name'] . "</td>";

            echo "<td><a href='edit_customer.php?id=" . $row['customer_id'] . "'><span class='edit-icon'>&#9998;</span></a></td>";
            // echo "<td><a href='delete_customer.php?id=" . $row['customer_id'] . "'><span class='delete-icon'>&#128465;</span></a></td>";
            echo '<td><span class="delete-icon" onclick="confirmDelete(\'' . $row['customer_id'] . '\')">&#128465;</span></td>';
            echo "</tr>";

        }
        
    }
} else {
    echo "<tr><td colspan='5'>No records found</td></tr>";
}

// Close the database connection
$conn->close();

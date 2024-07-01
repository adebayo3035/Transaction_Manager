<?php
// Include your database connection file
include "config.php";

// Fetch data from the database
$query = "SELECT * FROM food";
$result = $conn->query($query);
$staff_role = $_SESSION['role'];

// Check if there are rows in the result
if ($result->num_rows > 0) {
    // Output data of each row
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['food_id'] . "</td>";
        echo "<td>" . $row['food_name'] . "</td>";
        // echo "<td>" . $row['food_description'] . "</td>";
        echo "<td>" . $row['food_price'] . "</td>";
        echo "<td>" . $row['available_quantity'] . "</td>";
        echo "<td>" . $row['availability_status'] . "</td>";

        // Display edit and Delete Icons Only for Super Admin
        require_once('check_role2.php');
        $path1 = 'edit_food.php';
        $path2 = 'backend/delete_food.php';
        DisplayEditIcon($path1,$staff_role, $row['food_id']);
        DisplayDeleteFoodIcon($staff_role, $row['food_id']);
        echo "</tr>";

    }
} else {
    echo "<tr><td colspan='8'>No records found</td></tr>";
}

// Close the database connection
$conn->close();

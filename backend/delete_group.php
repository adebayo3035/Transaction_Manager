<?php
// Include your database connection file
include "config.php";

// Check if the 'id' parameter is set in the URL
if (isset($_GET['id'])) {
    // Get the 'id' parameter from the URL
    $group_id = intval($_GET['id']);
    
    // Prepare the DELETE statement
    $query = "DELETE FROM groups WHERE group_id = ?";
    
    // Initialize the statement
    if ($stmt = $conn->prepare($query)) {
        // Bind the 'id' parameter to the statement
        $stmt->bind_param("i", $group_id);
        
        // Execute the statement
        if ($stmt->execute()) {
            // If the delete was successful, redirect to a success page or display a success message
            echo "<script>alert('Group has been deleted Successfully.');window.location.href='../groups.php'; </script>";
            exit();
        } else {
            // If there was an error executing the statement, display an error message
            echo "Error: Could not execute the delete statement.";
        }
        
        // Close the statement
        $stmt->close();
    } else {
        // If there was an error preparing the statement, display an error message
        echo "Error: Could not prepare the delete statement.";
    }
} else {
    // If the 'id' parameter is not set in the URL, display an error message
    echo "Error: Invalid request.";
}

// Close the database connection
$conn->close();

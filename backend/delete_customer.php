<?php
include_once ('config.php');
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = mysqli_real_escape_string($conn, $_POST['customer_id']);

    if (!empty($customer_id)) {
        $select_photo = mysqli_query($conn, "SELECT photo FROM customers WHERE customer_id='$customer_id'");
        $row_customer = mysqli_fetch_array($select_photo);

        if ($row_customer) {
            $customer_photo = ($row_customer['photo']);
            $query = "DELETE FROM customers WHERE customer_id = '$customer_id'";
            $result = mysqli_query($conn, $query);

            if ($result) {
                $oldPicturePath = "customer_photos/" . $customer_photo;
                    if (file_exists($oldPicturePath)) {
                        unlink($oldPicturePath);
                    }
                    else{ echo "File Not Fount";}
                echo "<script>alert('Customer deleted successfully.');window.location.href='../customer.php'; </script>";
            } 
            else {
                echo "<script>alert('Error deleting customer: " . mysqli_error($conn) . "'); window.location.href='../customer.php';</script>";
            }
        }
        else {
            echo "<script>alert('Customer Photo cannot be found: " . mysqli_error($conn) . "'); window.location.href='../customer.php';</script>";
        }

        
    } else {
        echo "<script>alert('Invalid Customer ID.'); window.location.href='../customer.php';</script>";
    }
} 
else {
    header('Location: customer.php');
}





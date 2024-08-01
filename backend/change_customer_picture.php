<?php
if (isset($_POST['btnChangeCustomerPicture'])) {
    include_once('config.php');
    include('restriction_checker.php');
    session_start();
    $staff_id = $_SESSION['unique_id'];
    $customer_id = (filter_var($_POST['customer_id'], FILTER_SANITIZE_STRING));
    $secret_answer = md5(filter_var($_POST['secret_answer'], FILTER_SANITIZE_STRING));


    if (isset($_FILES['photo'])) {
        $img_name = $_FILES['photo']['name'];
        $img_type = $_FILES['photo']['type'];
        $tmp_name = $_FILES['photo']['tmp_name'];

        $img_explode = explode('.', $img_name);
        $img_ext = end($img_explode);

        $extensions = ["jpeg", "png", "jpg"];
        if (in_array($img_ext, $extensions) === true) {
            $types = ["image/jpeg", "image/jpg", "image/png"];
            if (in_array($img_type, $types) === true) {
                if ($_FILES["photo"]["size"] > 500000) {
                    echo "<script>alert('Image Size is too Large.'); window.location.href='../change_customer_picture.php?id=" . $customer_id . "';</script>";
                    exit();
                }

                $time = time();
                $new_img_name = $time . $img_name;
                if (move_uploaded_file($tmp_name, "customer_photos/" . $new_img_name)) {
                    $secret_answer_query = mysqli_query($conn, "SELECT secret_answer FROM admin_tbl WHERE unique_id='$staff_id'");
                    $select_photo = mysqli_query($conn, "SELECT photo FROM customers WHERE customer_id='$customer_id'");                    
                    $row_admin = mysqli_fetch_array($secret_answer_query);
                    $row_customer = mysqli_fetch_array($select_photo);

                    if ($row_admin) {
                        $decrypted_answer = ($row_admin['secret_answer']); // Assuming secret_answer is stored in plain text in the DB

                        if ($secret_answer === $decrypted_answer) { 
                            $old_image_name = $row_customer['photo'];
                            $updatePhotoQuery = "UPDATE customers SET photo = '{$new_img_name}' WHERE customer_id = '{$customer_id}'";
                            $updatePhotoResult = mysqli_query($conn, $updatePhotoQuery);

                            if ($updatePhotoResult) {
                                $oldPicturePath = "customer_photos/" . $old_image_name;
                                if (file_exists($oldPicturePath)) {
                                    unlink($oldPicturePath);
                                }
                                echo "<script>alert('Profile Picture has been changed Successfully.'); window.location.href='../change_customer_picture.php?id=" . $customer_id . "';</script>";
                            } else {
                                echo "<script>alert('Error Updating Customer Profile Picture'); window.location.href='../change_customer_picture.php?id=" . $customer_id . "'; </script>";
                            }
                        } else {
                            echo "<script>alert('Invalid Secret Answer.'); window.location.href='../change_customer_picture.php?id=" . $customer_id . "'; </script>";
                        }
                    } else {
                        echo "<script>alert('Customer not found or query error.'); window.location.href='../change_customer_picture.php?id=" . $customer_id . "'; </script>";
                    }
                } else {
                    echo "<script>alert('An Error Occurred Please Try Again.'); window.location.href='../change_customer_picture.php?id=" . $customer_id . "';</script>";
                }
            } else {
                echo "<script>alert('Please Upload an Image file with jpg - png or - jpeg.'); window.location.href='../change_customer_picture.php?id=" . $customer_id . "';</script>";
            }
        } else {
            echo "<script>alert('Please Upload an Image file with jpg - png or - jpeg.'); window.location.href='../change_customer_picture.php?id=" . $customer_id . "';</script>";
        }
    }
}

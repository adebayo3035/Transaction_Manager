<?php
if (isset($_POST['btnChangeCustomerPicture'])) {
    include_once('config.php');
    include('restriction_checker.php');

    session_start();
    $staff_id = $_SESSION['unique_id'];
    $customer_id = filter_var($_POST['customer_id'], FILTER_SANITIZE_STRING);
    $secret_answer = md5(filter_var($_POST['secret_answer'], FILTER_SANITIZE_STRING));

    if (!isset($_FILES['photo'])) {
        logActivity("CHANGE_PICTURE_FAILED: No image uploaded by staff ID $staff_id");
        echo "<script>alert('No image uploaded.'); window.location.href='../change_customer_picture.php?id=$customer_id';</script>";
        exit();
    }

    $img_name = $_FILES['photo']['name'];
    $img_type = $_FILES['photo']['type'];
    $tmp_name = $_FILES['photo']['tmp_name'];

    $img_ext = strtolower(pathinfo($img_name, PATHINFO_EXTENSION));
    $valid_extensions = ["jpeg", "png", "jpg"];
    $valid_types = ["image/jpeg", "image/jpg", "image/png"];

    if (!in_array($img_ext, $valid_extensions) || !in_array($img_type, $valid_types)) {
        logActivity("CHANGE_PICTURE_FAILED: Invalid image type uploaded by staff ID $staff_id");
        echo "<script>alert('Please upload a valid image (jpg, png, jpeg).'); window.location.href='../change_customer_picture.php?id=$customer_id';</script>";
        exit();
    }

    if ($_FILES["photo"]["size"] > 500000) {
        logActivity("CHANGE_PICTURE_FAILED: Image too large by staff ID $staff_id");
        echo "<script>alert('Image size too large.'); window.location.href='../change_customer_picture.php?id=$customer_id';</script>";
        exit();
    }

    $new_img_name = time() . '_' . $img_name;
    $upload_path = "customer_photos/" . $new_img_name;

    if (!move_uploaded_file($tmp_name, $upload_path)) {
        logActivity("CHANGE_PICTURE_FAILED: Failed to move uploaded file by staff ID $staff_id");
        echo "<script>alert('Error occurred while uploading image.'); window.location.href='../change_customer_picture.php?id=$customer_id';</script>";
        exit();
    }

    // Fetch staff secret answer
    $secret_answer_query = mysqli_query($conn, "SELECT secret_answer FROM admin_tbl WHERE unique_id='$staff_id'");
    if (!$secret_answer_query || mysqli_num_rows($secret_answer_query) == 0) {
        logActivity("CHANGE_PICTURE_FAILED: Staff ID $staff_id not found or error fetching secret answer.");
        echo "<script>alert('Authentication error.'); window.location.href='../change_customer_picture.php?id=$customer_id';</script>";
        exit();
    }

    $row_admin = mysqli_fetch_assoc($secret_answer_query);
    if ($secret_answer !== $row_admin['secret_answer']) {
        logActivity("CHANGE_PICTURE_FAILED: Invalid secret answer by staff ID $staff_id");
        echo "<script>alert('Invalid secret answer.'); window.location.href='../change_customer_picture.php?id=$customer_id';</script>";
        exit();
    }

    // Fetch customer’s current photo
    $select_photo = mysqli_query($conn, "SELECT photo FROM customers WHERE customer_id='$customer_id'");
    if (!$select_photo || mysqli_num_rows($select_photo) == 0) {
        logActivity("CHANGE_PICTURE_FAILED: Customer ID $customer_id not found.");
        echo "<script>alert('Customer not found.'); window.location.href='../change_customer_picture.php?id=$customer_id';</script>";
        exit();
    }

    $row_customer = mysqli_fetch_assoc($select_photo);
    $old_image_name = $row_customer['photo'];

    // Update new image
    $updatePhotoQuery = "UPDATE customers SET photo = '$new_img_name' WHERE customer_id = '$customer_id'";
    $updatePhotoResult = mysqli_query($conn, $updatePhotoQuery);

    if ($updatePhotoResult) {
        // Delete old image if exists
        $oldPicturePath = "customer_photos/" . $old_image_name;
        if (!empty($old_image_name) && file_exists($oldPicturePath)) {
            unlink($oldPicturePath);
        }

        logActivity("CHANGE_PICTURE_SUCCESS: Staff ID $staff_id changed picture for customer ID $customer_id");
        echo "<script>alert('Profile picture changed successfully.'); window.location.href='../change_customer_picture.php?id=$customer_id';</script>";
    } else {
        logActivity("CHANGE_PICTURE_FAILED: Database update failed by staff ID $staff_id for customer ID $customer_id");
        echo "<script>alert('Failed to update customer profile picture.'); window.location.href='../change_customer_picture.php?id=$customer_id';</script>";
    }
}

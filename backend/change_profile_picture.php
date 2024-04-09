<?php
if (isset($_POST['btnChangePicture'])){
    include_once('config.php');
    session_start();
    $id = $_SESSION['unique_id'];
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
                $time = time();
                $new_img_name = $time . $img_name;
                if ($_FILES["photo"]["size"] > 500000) {
                    echo "<script>alert('Image Size is too Large.');window.location.href='../change_picture.php?id=" . $id . "';</script>";
                    exit();
                }
                
                else if (move_uploaded_file($tmp_name, "admin_photos/" . $new_img_name)) {
                    $secret_answer_query = mysqli_query($conn, "select secret_answer, photo from `admin_tbl` where unique_id='$id'");
                    $row = mysqli_fetch_array($secret_answer_query);
                    if ($row) {
                        $decrypted_answer = ($row['secret_answer']);
                        $old_image_name = ($row['photo']);
                        if ($secret_answer == $decrypted_answer) {
                            //Update customer's phone number and E-mail address
                            $updatePhotoQuery = "UPDATE admin_tbl SET photo = '{$new_img_name}' WHERE unique_id = '{$id}'";
                            $updatePhotoResult = mysqli_query($conn, $updatePhotoQuery);
    
                            if (!$updatePhotoResult) {
                                //handle_error('Error updating session: ' . mysqli_error($conn));
                                echo "<script>alert('Error Updating Profile Picture')".mysqli_error($conn)." window.location.href='../change_picture.php?id=" . $id . "'; </script>";
                            }
                            else{
                                $oldPicturePath = "admin_photos/".$old_image_name; 
                                // echo $oldPicturePath;
                                if (file_exists($oldPicturePath)) {
                                    unlink($oldPicturePath);
                                    //echo $oldPicturePath;
                                } 
                                else {
                                    echo "Old picture file not found.";
                                }
                                //echo $oldPicturePath;
                                echo "<script>alert('Profile Picture has been changed Successfully.'); window.location.href='../homepage.php'; </script>";
                            }
                            
                        } 
                        else {
                            echo "<script>alert('Invalid Secret Answer.'); window.location.href='../change_picture.php?id=" . $id . "'; </script>";
                        }
                    } 
                    else {
                        echo "Error: " . mysqli_error($conn);
                    }
                }
                else{
                        echo "<script>alert(' An Error Occured Please Try Again.'); window.location.href='../change_picture.php?id=" . $id . "';</script>";
                }
            }
            else{
                echo "<script>alert(' Please Upload an Image file with jpg - png or - jpeg.'); window.location.href='../change_picture.php?id=" . $id . "';</script>";
                exit();
            }
        }
        else{
            echo "<script>alert(' Please Upload an Image file with jpg - png or - jpeg.'); window.location.href='../change_picture.php?id=" . $id . "';</script>";
        }
    }
} 

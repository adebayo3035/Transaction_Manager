<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
include 'config.php';

$customer_id = $_SESSION['customer_id'];
$response = ['success' => false, 'message' => ''];

// Fetch current profile picture
$sql = "SELECT photo, secret_answer FROM customers WHERE customer_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $customer_id);
$stmt->execute();
$stmt->bind_result($current_photo, $secret_answer);
$stmt->fetch();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_FILES['photo']) && isset($_POST['secret_answer'])) {
        $user_provided_secret_answer = $_POST['secret_answer'];
        
        // Hash the user-provided secret answer
        $hashed_user_provided_secret_answer = md5($user_provided_secret_answer);
        
        // Check if the hashed user-provided secret answer matches the stored hashed answer
        if ($hashed_user_provided_secret_answer !== $secret_answer) {
            echo json_encode(['success' => false, 'message' => 'Secret Answer Validation Failed.']);
            exit;
        }
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
                    echo json_encode(['success' => false, 'message' => 'Image size is too Large.']);
                    exit;
                }

                // Specify Upload Directory
                $upload_dir = '../../backend/customer_photos/';
                $time = time();
                $file_name = $time . $img_name;
                $upload_file = $upload_dir . $file_name;

                if (move_uploaded_file($tmp_name, $upload_file)) {
                    $sql = "UPDATE customers SET photo = ? WHERE customer_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('si', $file_name, $customer_id);

                    if ($stmt->execute()) {
                        $oldPicturePath = $upload_dir . $current_photo;
                        if (file_exists($oldPicturePath)) {
                            unlink($oldPicturePath);
                        }
                        $response['success'] = true;
                        $response['file'] = $file_name;
                    } else {
                        $response['message'] = 'Failed to update profile picture.';
                    }

                    $stmt->close();
                } else {
                    $response['message'] = 'Failed to move uploaded file.';
                }
            } else {
                $response['message'] = 'Uploaded file is not a valid image.';
            }
        } else {
            $response['message'] = 'File upload error: ' . $file['error'];
        }
    } else {
        $response['message'] = 'Invalid secret answer.';
    }
} else {
    $response['message'] = 'Secret answer or file not provided.';
}
$conn->close();
echo json_encode($response);
exit;


<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
include 'config.php';

$driver_id = $_SESSION['driver_id'];
$response = ['success' => false, 'message' => ''];

// Fetch current profile picture
$sql = "SELECT photo, secret_answer FROM driver WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $driver_id);
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
            $response['message'] = 'Secret Answer Validation Failed.';
            logActivity("Secret answer validation failed for driver ID: $driver_id");
            http_response_code(401); // Unauthorized
            echo json_encode($response);
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
                    $response['message'] = 'Image size is too large.';
                    logActivity("Image size too large for driver ID: $driver_id");
                    http_response_code(413); // Payload Too Large
                    echo json_encode($response);
                    exit;
                }

                // Specify Upload Directory
                $upload_dir = '../../backend/driver_photos/';
                
                // Generate a unique hash of the file's contents to check for duplicates
                $file_hash = md5_file($tmp_name);
                $file_name = $file_hash . '.' . $img_ext;
                $upload_file = $upload_dir . $file_name;

                // Check if the file already exists in the directory
                if (file_exists($upload_file)) {
                    $response['message'] = 'This image has already been uploaded by another user.';
                    logActivity("Duplicate image upload attempt by driver ID: $driver_id");
                    http_response_code(409); // Conflict
                    echo json_encode($response);
                    exit;
                }

                // If the file doesn't exist, move it to the directory
                if (move_uploaded_file($tmp_name, $upload_file)) {
                    // Update the driver's profile with the new photo
                    $sql = "UPDATE driver SET photo = ? WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('si', $file_name, $driver_id);

                    if ($stmt->execute()) {
                        // Delete the old picture if it exists
                        $oldPicturePath = $upload_dir . $current_photo;
                        if (file_exists($oldPicturePath)) {
                            unlink($oldPicturePath);
                        }
                        $response['success'] = true;
                        $response['file'] = $file_name;
                        $response['message'] = "Profile picture updated successfully for driver ID: $driver_id";
                        logActivity("Profile picture updated successfully for driver ID: $driver_id");
                    } else {
                        $response['message'] = 'Failed to update profile picture.';
                        logActivity("Failed to update profile picture for driver ID: $driver_id");
                        http_response_code(500); // Internal Server Error
                    }

                    $stmt->close();
                } else {
                    $response['message'] = 'Failed to move uploaded file.';
                    logActivity("Failed to move uploaded file for driver ID: $driver_id");
                    http_response_code(500); // Internal Server Error
                }
            } else {
                $response['message'] = 'Uploaded file is not a valid image.';
                logActivity("Invalid image type uploaded by driver ID: $driver_id");
                http_response_code(400); // Bad Request
            }
        } else {
            $response['message'] = 'Invalid file extension.';
            logActivity("Invalid file extension uploaded by driver ID: $driver_id");
            http_response_code(400); // Bad Request
        }
    } else {
        $response['message'] = 'Secret answer or file not provided.';
        logActivity("Secret answer or file not provided by driver ID: $driver_id");
        http_response_code(400); // Bad Request
    }
} else {
    $response['message'] = 'Invalid request method.';
    logActivity("Invalid request method for driver ID: $driver_id");
    http_response_code(405); // Method Not Allowed
}

$conn->close();
echo json_encode($response);
exit;
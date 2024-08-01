<?php
header('Content-Type: application/json');
include_once "config.php";

$firstname = mysqli_real_escape_string($conn, $_POST['firstName']);
$lastname = mysqli_real_escape_string($conn, $_POST['lastName']);
$email = mysqli_real_escape_string($conn, $_POST['email']);
$phone = mysqli_real_escape_string($conn, $_POST['phone']);
$password = mysqli_real_escape_string($conn, $_POST['password']);
$secret_question = mysqli_real_escape_string($conn, $_POST['secret_question']);
$secret_answer = mysqli_real_escape_string($conn, $_POST['secret_answer']);
$role = mysqli_real_escape_string($conn, $_POST['role']);

// REGEX TO VALIDATE PASSWORD AND SECRET ANSWER
$minLength = 8;
$hasSpecialChar = preg_match('/[!@#$%^&*(),.?":{}|<>_]/', $password);
$hasUpperCase = preg_match('/[A-Z]/', $password);
$hasDigit = preg_match('/\d/', $password);

if (!empty($firstname) && !empty($lastname) && !empty($email) && !empty($phone) && !empty($password) && !empty($secret_question) && !empty($secret_answer) && !empty($role)) {
    if ((strlen($password) < $minLength) || !$hasSpecialChar || !$hasUpperCase || !$hasDigit) {
        echo json_encode(['success' => false, 'message' => 'Please input a valid password.']);
        exit();
    }
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $sql_email = mysqli_query($conn, "SELECT * FROM admin_tbl WHERE email = '{$email}'");
        $sql_phone = mysqli_query($conn, "SELECT * FROM admin_tbl WHERE phone = '{$phone}'");
        if (mysqli_num_rows($sql_email) > 0) {
            echo json_encode(['success' => false, 'message' => "{$email} already exists. Please use a different email."]);
            exit();
        } else if (mysqli_num_rows($sql_phone) > 0) {
            echo json_encode(['success' => false, 'message' => "{$phone} already exists. Please use a different email."]);
            exit();
        } else {
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
                        if (move_uploaded_file($tmp_name, "admin_photos/" . $new_img_name)) {
                            $ran_id = rand(time(), 100000000);
                            $status = "Active now";
                            $encrypt_pass = md5($password);
                            $encrypt_secret_question = $secret_question;
                            $encrypt_secret_answer = md5($secret_answer);
                            $insert_query = mysqli_query($conn, "INSERT INTO admin_tbl (unique_id, firstname, lastname, email, phone, password, secret_question, secret_answer, photo, role)
                                VALUES ({$ran_id}, '{$firstname}', '{$lastname}', '{$email}', '{$phone}', '{$encrypt_pass}', '{$encrypt_secret_question}', '{$encrypt_secret_answer}', '{$new_img_name}', '{$role}')");
                            if ($insert_query) {
                                $select_sql2 = mysqli_query($conn, "SELECT * FROM admin_tbl WHERE email = '{$email}'");
                                if (mysqli_num_rows($select_sql2) > 0) {
                                    $result = mysqli_fetch_assoc($select_sql2);
                                    echo json_encode(['success' => true]);
                                } else {
                                    echo json_encode(['success' => false, 'message' => 'This email address does not exist. Please try again later.']);
                                }
                            } else {
                                echo json_encode(['success' => false, 'message' => 'Something went wrong, please try again.']);
                            }
                        }
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Please upload an image file with jpg, png, or jpeg extension.']);
                        exit();
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Please upload an image file with jpg, png, or jpeg extension.']);
                }
            }
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Please fill all input fields.']);
}
?>

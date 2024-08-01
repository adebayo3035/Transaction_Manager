<?php
header('Content-Type: application/json');
include_once "config.php";


$firstname = mysqli_real_escape_string($conn, $_POST['firstName']);
$lastname = mysqli_real_escape_string($conn, $_POST['lastName']);
$gender = mysqli_real_escape_string($conn, $_POST['gender']);
$email = mysqli_real_escape_string($conn, $_POST['email']);
$password = mysqli_real_escape_string($conn, $_POST['password']);
$mobile_number = mysqli_real_escape_string($conn, $_POST['phoneNumber']);
$address = mysqli_real_escape_string($conn, $_POST['address']);
$group= mysqli_real_escape_string($conn, $_POST['group']);
$unit = mysqli_real_escape_string($conn, $_POST['unit']);
$date_created = date('Y-m-d H:i:s');
$date_updated = date('Y-m-d H:i:s');

$minLength = 8;
$hasSpecialChar = preg_match('/[!@#$%^&*(),.?":{}|<>_]/', $password);
$hasUpperCase = preg_match('/[A-Z]/', $password);
$hasDigit = preg_match('/\d/', $password);

if (!empty($firstname) && !empty($lastname) && !empty($gender) && !empty($email) && !empty($mobile_number) && !empty($address) && !empty($group) && !empty($unit)) {
    if (((strlen($password)) < $minLength) || (!$hasSpecialChar) || (!$hasUpperCase) || (!$hasDigit)) {
        echo json_encode(['success' => false, 'message' => 'Please input a valid password.']);
        exit();
    }

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $sql_email = mysqli_query($conn, "SELECT * FROM customers WHERE email = '{$email}'");
        $sql_phone = mysqli_query($conn, "SELECT * FROM customers WHERE mobile_number = '{$mobile_number}'");

        if (mysqli_num_rows($sql_email) > 0) {
            echo json_encode(['success' => false, 'message' => "{$email} already exists. Please use a different email."]);
            exit();
        } else if (mysqli_num_rows($sql_phone) > 0) {
            echo json_encode(['success' => false, 'message' => "{$mobile_number} already exists. Please use a different phone number."]);
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
                        if (move_uploaded_file($tmp_name, "customer_photos/" . $new_img_name)) {
                            $customer_id = rand(time(), 100000000);
                            $status = "Active now";
                            $encrypt_pass = md5($password);

                            $insert_query = mysqli_query($conn, "INSERT INTO customers (customer_id, firstname, lastname, gender, email, password, mobile_number, address, photo, group_id, unit_id, date_created, date_updated)
                            VALUES ({$customer_id}, '{$firstname}', '{$lastname}', '{$gender}', '{$email}', '{$encrypt_pass}', '{$mobile_number}', '{$address}', '{$new_img_name}', '{$group}', '{$unit}', '{$date_created}', '{$date_updated}')");

                            if ($insert_query) {
                                echo json_encode(['success' => true]);
                            } else {
                                echo json_encode(['success' => false, 'message' => 'Something went wrong, please try again.']);
                            }
                        }
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Please upload an image file with jpg, png, or jpeg extension.']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Please upload an image file with jpg, png, or jpeg extension.']);
                }
            }
        }
    } else {
        echo json_encode(['success' => false, 'message' => "{$email} is not a valid email address."]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Please fill all input fields.']);
}

$conn->close();


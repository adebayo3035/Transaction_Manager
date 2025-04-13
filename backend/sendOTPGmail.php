<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';
function sendEmailWithGmailSMTP($to, $body, $subject) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'rahmondebayo@gmail.com';
        $mail->Password   = 'kwmrmfjyuvtahiwm';
        // $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        // $mail->Port       = 587;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;


        // Use the same email as Username for setFrom
        $mail->setFrom('rahmondebayo@gmail.com', 'Transaction Manager');
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        echo "Mailer Error: " . $mail->ErrorInfo;  // Show actual error
        error_log("Mailer Error: " . $mail->ErrorInfo);
        logActivity("Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

// Example usage of the function
// $email = "abdulrahmonadebayo@gmail.com";  // Replace with the user's email
// $otp = rand(100000, 999999);  // Generate a random 6-digit OTP
// $body = "Your OTP is: $otp. It expires in 5 minutes.";  // OTP message
// $subject = "Email Testing for Account Deactivation";
// $status = sendEmailWithGmailSMTP($email, $body, $subject);

// if ($status) {
//     echo "OTP sent successfully!";
// } else {
//     echo "Failed to send OTP.";
// }


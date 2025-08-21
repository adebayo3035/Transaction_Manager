<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../vendor/autoload.php';
function sendEmailWithGmailSMTP($to, $body, $subject, $attachments = []) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'rahmondebayo@gmail.com';
        $mail->Password   = 'kwmrmfjyuvtahiwm';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        $mail->setFrom('rahmondebayo@gmail.com', 'Transaction Manager');
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        // Add attachments if any
        if (!empty($attachments)) {
            foreach ($attachments as $attachment) {
                // if (file_exists($attachment)) {
                //     $mail->addAttachment($attachment);
                // }

                if (file_exists($attachment)) {
                    // Use the original filename or create a nice name
                    $filename = basename($attachment);
                    if (strpos($filename, 'receipt_') === 0) {
                        // Rename to something more user-friendly
                        $mail->addAttachment($attachment, 'KaraKata_Receipt.pdf');
                    } else {
                        $mail->addAttachment($attachment);
                    }
                }
            }
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        echo "Mailer Error: " . $mail->ErrorInfo;
        error_log("Mailer Error: " . $mail->ErrorInfo);
        logActivity("Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}
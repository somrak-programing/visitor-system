<?php
// Include PHPMailer classes
require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Helper function to send notification emails.
 * Make sure to fill in your SMTP details below!
 * 
 * @param string $toEmail
 * @param string $subject
 * @param string $body
 * @return array
 */
function sendNotificationEmail($toEmail, $subject, $body) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        // === EDIT YOUR SMTP SETTINGS HERE ===
        $mail->Host       = 'smtp.office365.com';       // Set the SMTP server to Microsoft 365
        $mail->SMTPAuth   = true;                       // Enable SMTP authentication
        $mail->Username   = 'requestsystem@bscb.co.th'; // SMTP username
        $mail->Password   = 'adminK2C@#246135';      // The actual login password for requestsystem@bscb.co.th
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Enable TLS encryption
        $mail->Port       = 587;                        // TCP port to connect to
        // ====================================

        // Recipients
        $mail->setFrom($mail->Username, 'Visitor Request System');
        $mail->addAddress($toEmail);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return ["status" => "success", "message" => "Email sent successfully"];
    } catch (Exception $e) {
        return ["status" => "error", "message" => "Message could not be sent. Mailer Error: {$mail->ErrorInfo}"];
    }
}
?>

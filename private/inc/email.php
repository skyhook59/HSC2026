<?php
// private/inc/email.php
// Include the PHPMailer library
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once __DIR__ . '/../../public/vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../../public/vendor/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/../../public/vendor/phpmailer/phpmailer/src/SMTP.php';

// Function to send an email using Gmail's SMTP
function send_email(string $to, string $subject, string $body): bool
{
    // Get SMTP credentials from environment
    $smtpHost     = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
    $smtpPort     = getenv('SMTP_PORT') ?: 465;
    $smtpUsername = getenv('SMTP_USERNAME');
    $smtpPassword = getenv('SMTP_PASSWORD');
    $smtpFromName = getenv('SMTP_FROM_NAME') ?: 'HSC Picks';

    if (!$smtpUsername || !$smtpPassword) {
        error_log('FATAL: Missing SMTP credentials (SMTP_USERNAME, SMTP_PASSWORD)');
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $smtpHost;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpUsername;
        $mail->Password   = $smtpPassword;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = (int)$smtpPort;
        $mail->CharSet    = 'UTF-8';

        // Recipients
        $mail->setFrom($smtpUsername, $smtpFromName);
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

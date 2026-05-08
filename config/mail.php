<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function getMailer() {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        // User provided Gmail and App Password
        $mail->Username   = 'pesut.ptunsamarinda@gmail.com'; 
        $mail->Password   = 'lcrzcpligivovtza'; // The app password without spaces
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Enable implicit TLS encryption
        $mail->Port       = 465;

        // Default Sender
        $mail->setFrom('pesut.ptunsamarinda@gmail.com', 'Admin PESUT PTUN Samarinda');
        
        return $mail;
    } catch (Exception $e) {
        error_log("Mailer configuration error: " . $mail->ErrorInfo);
        return null;
    }
}

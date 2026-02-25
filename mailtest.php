<?php
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->Port = 587;
    $mail->SMTPSecure = 'tls';
    $mail->SMTPAuth = true;
    $mail->Username = 'your@gmail.com';      // ← change
    $mail->Password = 'your-app-password';   // ← change

    $mail->setFrom('your@gmail.com', 'Test');
    $mail->addAddress('your-own-email@gmail.com');
    $mail->Subject = 'Test from InfinityFree';
    $mail->Body = 'If you see this → SMTP works';

    $mail->send();
    echo 'Message sent!';
} catch (Exception $e) {
    echo 'Failed: ' . $mail->ErrorInfo;
}
?>
<?php
require_once __DIR__ . '/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'medicine.trackerarbaminch@gmail.com';
    $mail->Password = 'xukw hgxz odxb mgmh';
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;
    $mail->SMTPDebug = 2; // Enable verbose debug output

    $mail->setFrom('medicine.trackerarbaminch@gmail.com', 'MedTracker Test');
    $mail->addAddress('medicine.trackerarbaminch@gmail.com'); // Send to self for testing

    $mail->isHTML(true);
    $mail->Subject = 'SMTP Test';
    $mail->Body    = 'This is a test email to verify SMTP connectivity.';

    echo "Attempting to send mail...\n";
    $mail->send();
    echo "Message has been sent\n";
} catch (Exception $e) {
    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}\n";
}

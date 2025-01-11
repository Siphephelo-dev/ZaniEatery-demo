<?php

error_reporting(0);

// Include PHPMailer files
require 'C:\xampp\htdocs\Restaurantly\Restaurantly\assets\vendor\PHPMailer-master\src\PHPMailer.php';
require 'C:\xampp\htdocs\Restaurantly\Restaurantly\assets\vendor\PHPMailer-master\src\SMTP.php';
require 'C:\xampp\htdocs\Restaurantly\Restaurantly\assets\vendor\PHPMailer-master\src\Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST["name"];
    $email = $_POST["email"];
    $subject = $_POST["subject"];
    $message = $_POST["message"];

    $mail = new PHPMailer(true);

    try {
        $mail->SMTPDebug = 0; // Disable verbose debug output

        $mail->isSMTP();
        $mail->SMTPAuth = true;

        $mail->Host = "smtp.gmail.com";
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->Username = "zanieatery9@gmail.com";
        $mail->Password = "uwvpvucuahemxbod";

        $mail->setFrom($email, $name);
        $mail->addAddress("zanieatery9@gmail.com", "Zani Eatery");

        $mail->Subject = $subject;
        $mail->Body = $message;

        $mail->send();

        // Clear POST data
        $_POST = array();

        // Redirect to the index page with a success message
        echo "
        <script>
            alert('Message was sent successfully!');
            window.location.href = 'http://localhost/Zani/Zani';
        </script>";
    } catch (Exception $e) {
        // Log the error message silently
        error_log("Mailer Error: {$mail->ErrorInfo}");

        // Redirect to the index page with a generic failure message
        echo "
        <script>
            alert('Message could not be sent. Please try again later.');
            window.location.href = 'http://localhost/Zani/Zani';
        </script>";
    }
}
?>
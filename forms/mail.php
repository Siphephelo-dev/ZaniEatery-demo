<?php

error_reporting(0);

// Include PHPMailer files
require 'C:\xampp\htdocs\Zani\Zani\assets\vendor\PHPMailer-master\src\PHPMailer.php';
require 'C:\xampp\htdocs\Zani\Zani\assets\vendor\PHPMailer-master\src\SMTP.php';
require 'C:\xampp\htdocs\Zani\Zani\assets\vendor\PHPMailer-master\src\Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST["name"];
    $email = $_POST["email"];
    $subject = $_POST["subject"];
    $message = $_POST["message"];

    $mail = new PHPMailer(true);

    try {
        // Enable debug mode to see what's happening
        $mail->SMTPDebug = 0; 
        
        $mail->isSMTP();
        $mail->SMTPAuth = true;
        
        $mail->Host = "smtp.gmail.com";
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Gmail credentials
        $mail->Username = "zanieatery9@gmail.com";
        $mail->Password = "ntzesqsmrdxzatao"; // Make sure this is an App Password
        
        // Properly set sender and recipient
        $mail->setFrom("zanieatery9@gmail.com", "Zani Contact Form"); // Use fixed sender
        $mail->addReplyTo($email, $name); // Add reply-to with user's email
        $mail->addAddress("zanieatery9@gmail.com", "Zani Eatery");
        
        // Set email content with proper formatting
        $mail->isHTML(true);
        $mail->Subject = "Contact Form: " . $subject;
        $mail->Body = "
            <p><strong>Name:</strong> {$name}</p>
            <p><strong>Email:</strong> {$email}</p>
            <p><strong>Subject:</strong> {$subject}</p>
            <p><strong>Message:</strong></p>
            <p>{$message}</p>
        ";
        
        // Add plain text version
        $mail->AltBody = "From: {$name}\nEmail: {$email}\nSubject: {$subject}\nMessage: {$message}";
        
        if (!$mail->send()) {
            throw new Exception($mail->ErrorInfo);
        }
        
        $_POST = array();
        echo "<script>
            alert('Message sent successfully!');
            window.location.href = 'http://localhost/Zani/Zani';
        </script>";
        
    } catch (Exception $e) {
        error_log("Mail Error: " . $e->getMessage());
        echo "<script>
            alert('Message could not be sent. Error: " . str_replace("'", "\\'", $e->getMessage()) . "');
            window.location.href = 'http://localhost/Zani/Zani';
        </script>";
    }
}
?>
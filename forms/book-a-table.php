<?php

// Error reporting for development (comment out in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include PHPMailer autoload.php file
require 'C:\xampp\htdocs\Zani\Zani\assets\vendor\PHPMailer-master\src\PHPMailer.php';
require 'C:\xampp\htdocs\Zani\Zani\assets\vendor\PHPMailer-master\src\SMTP.php';
require 'C:\xampp\htdocs\Zani\Zani\assets\vendor\PHPMailer-master\src\Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Database connection
$servername = "localhost";
$username = "root"; // Replace with your MySQL username
$password = ""; // Replace with your MySQL password
$dbname = "Zani";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to check if booking slot is available
function isBookingAvailable($conn, $date, $time, $hours) {
    $endTime = date("H:i:s", strtotime("$time + $hours hour"));
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM bookings
        WHERE booking_date = ?
        AND (
            (booking_time <= ? AND DATE_ADD(booking_time, INTERVAL booking_hours HOUR) > ?)
            OR
            (booking_time < ? AND DATE_ADD(booking_time, INTERVAL booking_hours HOUR) >= ?)
        )
    ");
    $stmt->bind_param("sssss", $date, $time, $time, $endTime, $endTime);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return $count < 4;
}

// Function to find an available table
function findAvailableTable($conn) {
    $stmt = $conn->prepare("SELECT id FROM tables WHERE status = 'available' LIMIT 1");
    $stmt->execute();
    $stmt->bind_result($table_id);
    $stmt->fetch();
    $stmt->close();
    return $table_id;
}

// Function to confirm booking
function confirmBooking($conn, $name, $email, $phone, $date, $time, $people, $message, $table_id, $hours) {
    $stmt = $conn->prepare("INSERT INTO bookings (name, email, phone, booking_date, booking_time, people, message, table_id, booking_hours) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssisii", $name, $email, $phone, $date, $time, $people, $message, $table_id, $hours);
    return $stmt->execute();
}

// Function to update table status
function updateTableStatus($conn, $table_id, $status) {
    $stmt = $conn->prepare("UPDATE tables SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $table_id);
    $stmt->execute();
    $stmt->close();
}

// Payment processing functions

// Stripe payment processing
function processStripePayment($amount, $stripeToken) {
    // Include Stripe PHP library
    require_once('vendor/autoload.php');

    \Stripe\Stripe::setApiKey(''); // Replace with your Stripe secret key

    try {
        $charge = \Stripe\Charge::create([
            'amount' => $amount * 100, // Amount in cents
            'currency' => 'usd',
            'description' => 'Table booking payment',
            'source' => $stripeToken,
        ]);
        return $charge->status == 'succeeded';
    } catch (\Stripe\Exception\CardException $e) {
        return false;
    }
}

// PayPal payment processing
function processPaypalPayment($amount) {
    // Include PayPal PHP library
    require 'vendor/autoload.php';

    $apiContext = new \PayPal\Rest\ApiContext(
        new \PayPal\Auth\OAuthTokenCredential(
            'your_paypal_client_id',     // Replace with your PayPal client ID
            'your_paypal_client_secret'  // Replace with your PayPal client secret
        )
    );

    $payer = new \PayPal\Api\Payer();
    $payer->setPaymentMethod('paypal');

    $amountObj = new \PayPal\Api\Amount();
    $amountObj->setTotal($amount);
    $amountObj->setCurrency('USD');

    $transaction = new \PayPal\Api\Transaction();
    $transaction->setAmount($amountObj);
    $transaction->setDescription('Table booking payment');

    $redirectUrls = new \PayPal\Api\RedirectUrls();
    $redirectUrls->setReturnUrl("http://localhost/Zani/Zani/success.php")
        ->setCancelUrl("http://localhost/Zani/Zani/cancel.php");

    $payment = new \PayPal\Api\Payment();
    $payment->setIntent('sale')
        ->setPayer($payer)
        ->setTransactions([$transaction])
        ->setRedirectUrls($redirectUrls);

    try {
        $payment->create($apiContext);
        return $payment->getApprovalLink();
    } catch (Exception $e) {
        return false;
    }
}

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Get payment method and process payment
    $paymentMethod = $_POST['payment_method'];
    $paymentAmount = $_POST['hours'] * 100; // R100 per hour

    if ($paymentMethod == 'stripe') {
        $stripeToken = $_POST['stripeToken'];
        if (!processStripePayment($paymentAmount, $stripeToken)) {
            echo "<script>
                    alert('Payment failed. Please try again.');
                    window.location.href = 'http://localhost/Zani/Zani';
                  </script>";
            exit; // Stop further execution
        }
    } elseif ($paymentMethod == 'paypal') {
        $approvalLink = processPaypalPayment($paymentAmount);
        if (!$approvalLink) {
            echo "<script>
                    alert('Payment failed. Please try again.');
                    window.location.href = 'http://localhost/Zani/Zani';
                  </script>";
            exit; // Stop further execution
        } else {
            header("Location: $approvalLink");
            exit; // Stop further execution
        }
    } else {
        echo "<script>
                alert('Invalid payment method.');
                window.location.href = 'http://localhost/Zani/Zani';
              </script>";
        exit; // Stop further execution
    }

    // Instantiate PHPMailer
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'zanieatery9@gmail.com'; // Your Gmail address
        $mail->Password = 'uwvpvucuahemxbod'; // Your Gmail password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients
        $mail->setFrom($_POST['email'], $_POST['name']); // Sender's email and name
        $mail->addAddress('zanieatery9@gmail.com', 'Zani Eatery'); // Recipient's email and name

        // Content
        $mail->isHTML(false); // Set email format to plain text
        $mail->Subject = 'New table booking request from the website'; // Email subject
        $mail->Body    = "
        Name: {$_POST['name']}
        Email: {$_POST['email']}
        Phone: {$_POST['phone']}
        Date: {$_POST['date']}
        Time: {$_POST['time']}
        No. of People: {$_POST['people']}
        Message: {$_POST['message']}
        ";

        // Check if booking is available
        $bookingDate = $_POST['date'];
        $bookingTime = $_POST['time'];
        $hours = $_POST['hours'];

        if (!isBookingAvailable($conn, $bookingDate, $bookingTime, $hours)) {
            // Booking not available, redirect with error message
            echo "<script>
                    alert('Table already booked for this date and time. Please choose another time.');
                    window.location.href = 'http://localhost/Zani/Zani';
                  </script>";
            exit; // Stop further execution
        }

        // Find an available table
        $table_id = findAvailableTable($conn);
        if (!$table_id) {
            echo "<script>
                    alert('No available tables at this time. Please choose another time.');
                    window.location.href = 'http://localhost/Zani/Zani';
                  </script>";
            exit; // Stop further execution
        }

        // If booking is available, proceed to confirm booking
        if (confirmBooking($conn, $_POST['name'], $_POST['email'], $_POST['phone'], $bookingDate, $bookingTime, $_POST['people'], $_POST['message'], $table_id, $hours)) {
            // Update table status
            updateTableStatus($conn, $table_id, 'booked');
            
            // Booking confirmed, send email and redirect with success message
            $mail->send(); // Send email

            // Clear POST data
            $_POST = array();

            // Redirect with success message
            echo "<script>
                    alert('Your booking request was sent. We will call back or send an Email to confirm your reservation. Thank you!!');
                    window.location.href = 'http://localhost/Zani/Zani';
                  </script>";
        } else {
            // Booking could not be confirmed, redirect with error message
            echo "<script>
                    alert('Booking could not be confirmed. Please try again later.');
                    window.location.href = 'http://localhost/Zani/Zani';
                  </script>";
        }

    } catch (Exception $e) {
        // Log error
        error_log("Mailer Error: {$mail->ErrorInfo}");

        // Redirect with error message
        echo "<script>
                alert('Your booking request was not sent. Please try again later.');
                window.location.href = 'http://localhost/Zani/Zani';
              </script>";
    }
}

$conn->close();
?>

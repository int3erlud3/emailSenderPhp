<?php
// int3erlud3 php emailsender
// Recipient email address
$to = 'recipient@example.com';

// Subject of the email 
$subject = 'Test Email';

// Message body
$message = 'This is a test email sent from PHP script.';

// Sender email and name
$senderEmail = 'sender@example.com';
$senderName = 'YourChoose';

// Additional headers
$headers = 'From: ' . $senderName . ' <' . $senderEmail . '>' . "\r\n" .
           'Reply-To: ' . $senderEmail . "\r\n" .
           'X-Mailer: PHP/' . phpversion();

// Send email
if (mail($to, $subject, $message, $headers)) {
    echo 'Email sent successfully!';
} else {
    echo 'Failed to send email.';
}
?>

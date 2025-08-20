<?php
// Enable error reporting for debugging (important for testing email)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function test_send_approver_notification_email($approver_email, $request_id, $request_title) {
    // Subject of the email
    $subject = "Action Required: IT Request #" . $request_id . " - " . $request_title;

    // Body of the email (plain text, for simplicity)
    $message = "Dear Approver,\n\n";
    $message .= "A new IT request is pending your approval.\n\n";
    $message .= "Request ID: " . $request_id . "\n";
    $message .= "Title: " . $request_title . "\n\n";
    $message .= "Please log in to the IT Request System to review and take action:\n";
    $message .= "http://localhost/ItRequest/view_request.php?id=" . $request_id . "\n\n";
    $message .= "Thank you,\n";
    $message .= "IT Request System Team";

    // Headers (important for proper email formatting)
    $headers = "From: no-reply@itrequestsystem.com\r\n";
    $headers .= "Reply-To: no-reply@itrequestsystem.com\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    // Attempt to send the email
    $mail_sent = mail($approver_email, $subject, $message, $headers);

    if ($mail_sent) {
        return "Test email sent successfully to " . htmlspecialchars($approver_email) . " for request #" . htmlspecialchars($request_id) . ".";
    } else {
        // You might see more specific errors in your PHP error logs or server logs
        return "Failed to send test email to " . htmlspecialchars($approver_email) . ". Check your php.ini mail settings and server logs.";
    }
}

// --- HOW TO TEST ---
// 1. IMPORTANT: Configure your php.ini file (see instructions below).
// 2. Replace 'approver@example.com' with an actual email address you can access for testing.
//    (e.g., your personal email address)
$test_approver_email = 'apveend@gmail.com'; // <--- CHANGE THIS (Doesn't have to be a real email for Mailtrap)
$test_request_id = 123; // Just a dummy ID for testing
$test_request_title = "Need new software license"; // Just a dummy title for testing

echo "<h1>Mail Function Test</h1>";
echo "<p>" . test_send_approver_notification_email($test_approver_email, $test_request_id, $test_request_title) . "</p>";

?>

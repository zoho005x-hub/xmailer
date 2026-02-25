<?php
/**
 * PHPMailer Bulk Sender for Azure App Service
 * Version: 2026 edition – clean, with progress feedback
 * Works on Azure App Service PHP (Linux)
 * Use external SMTP (Gmail / Brevo / Office365 / SendGrid etc.)
**/

// Enable error display during testing (remove or comment out in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ────────────────────────────────────────────────
//  CONFIG – CHANGE THESE VALUES
// ────────────────────────────────────────────────

// SMTP settings – example for Brevo (free 300/day) or Gmail
$smtp = [
    'host'     => 'smtp-relay.brevo.com',      // Brevo: smtp-relay.brevo.com
    'port'     => 587,
    'secure'   => 'tls',                       // 'tls' or 'ssl'
    'username' => 'your-brevo-login-email@example.com',
    'password' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', // SMTP key from Brevo dashboard
    'from_email' => 'sender@yourdomain.com',
    'from_name'  => 'Your App Name',
];

// Password protection (change this!)
$admin_password = "spyx123";   // ← CHANGE THIS

// ────────────────────────────────────────────────
//  PASSWORD PROTECTION
// ────────────────────────────────────────────────
session_start();
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    if (isset($_POST['pass']) && $_POST['pass'] === $admin_password) {
        $_SESSION['auth'] = true;
    } else {
        ?>
        <!DOCTYPE html>
        <html>
        <head><title>Login</title></head>
        <body style="text-align:center; margin-top:150px;">
            <h2>Enter Password</h2>
            <form method="post">
                <input type="password" name="pass" size="30">
                <button type="submit">Login</button>
            </form>
        </body>
        </html>
        <?php
        exit;
    }
}

// ────────────────────────────────────────────────
//  PHPMailer – load via Composer or manual includes
// ────────────────────────────────────────────────
// If using Composer (recommended on Azure):
// Run in Kudu / SSH: composer require phpmailer/phpmailer
// Then:
// require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Manual includes (if no Composer):
// require 'PHPMailer/src/Exception.php';
// require 'PHPMailer/src/PHPMailer.php';
// require 'PHPMailer/src/SMTP.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send') {
    $to_list     = trim($_POST['emails'] ?? '');
    $subject_raw = trim($_POST['subject'] ?? '');
    $body_raw    = $_POST['body'] ?? '';
    $sender_name = trim($_POST['sender_name'] ?? $smtp['from_name']);
    $sender_email= trim($_POST['sender_email'] ?? $smtp['from_email']);

    $emails = array_filter(array_map('trim', explode("\n", $to_list)));

    echo "<!DOCTYPE html><html><head><title>Sending...</title>
    <style>body{font-family:monospace;padding:20px;} .ok{color:green;font-weight:bold;} .fail{color:red;}</style></head><body>";
    echo "<h2>Sending in progress...</h2><pre>";

    $count = 0;
    $success = 0;

    foreach ($emails as $email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo "[$count] $email → <span class='fail'>invalid</span>\n";
            continue;
        }

        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = $smtp['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp['username'];
            $mail->Password   = $smtp['password'];
            $mail->SMTPSecure = $smtp['secure'];
            $mail->Port       = $smtp['port'];

            // Recipients
            $mail->setFrom($sender_email, $sender_name);
            $mail->addAddress($email);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject_raw;
            $mail->Body    = $body_raw;
            $mail->AltBody = strip_tags($body_raw);

            $mail->send();
            $success++;
            echo "[$count] $email → <span class='ok'>OK</span>\n";
        } catch (Exception $e) {
            echo "[$count] $email → <span class='fail'>Failed: " . htmlspecialchars($mail->ErrorInfo) . "</span>\n";
        }

        $count++;
        flush();          // Try to show progress
        ob_flush();
        usleep(100000);   // 0.1 sec delay – helps avoid rate limits
    }

    echo "\nFinished. Sent: $success / " . count($emails) . "\n";
    echo "</pre><a href='?'>Back</a></body></html>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Simple Mailer – Azure App Service</title>
<style>
    body {font-family:Arial,sans-serif;max-width:800px;margin:40px auto;padding:20px;}
    textarea {width:100%;height:180px;}
    label {display:block;margin:12px 0 4px;}
    input[type=text], input[type=password] {width:100%;padding:8px;}
    button {padding:12px 24px;background:#0066cc;color:white;border:none;cursor:pointer;margin-top:20px;}
    button:hover {background:#0052a3;}
    pre {background:#f8f8f8;padding:15px;border:1px solid #ddd;max-height:400px;overflow-y:auto;}
</style>
</head>
<body>

<h2>Simple Bulk Mailer (PHPMailer on Azure)</h2>

<form method="post">
    <input type="hidden" name="action" value="send">

    <label>Sender Name</label>
    <input type="text" name="sender_name" value="<?= htmlspecialchars($smtp['from_name']) ?>" required>

    <label>Sender Email (must match SMTP user/domain)</label>
    <input type="email" name="sender_email" value="<?= htmlspecialchars($smtp['from_email']) ?>" required>

    <label>Subject</label>
    <input type="text" name="subject" required>

    <label>Message (HTML supported)</label>
    <textarea name="body" required placeholder="Hello [-emailuser-], your code: [-randommd5-] ..."></textarea>

    <label>Recipients (one email per line)</label>
    <textarea name="emails" required placeholder="user1@example.com
user2@example.com
..."></textarea>

    <button type="submit">Send Emails</button>
</form>

<p><strong>Note:</strong> This is basic – no advanced placeholders yet (you can add str_replace logic if needed). Test with 2–3 emails first. Use external SMTP (not Azure internal). Check Azure logs if 500 occurs.</p>

</body>
</html>
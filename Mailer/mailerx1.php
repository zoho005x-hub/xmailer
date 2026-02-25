<?php
/**
 * PHPMailer Bulk Sender for Azure App Service – 2026 Fixed Edition + Reply-To
 * Uses ZeptoMail SMTP (smtp.zeptomail.com)
 * Real-time progress, basic placeholders, better error visibility
 */
// Show errors during testing (disable in production!)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ────────────────────────────────────────────────
// CONFIG – CHANGE THESE IF NEEDED
// ────────────────────────────────────────────────
$smtp = [
    'host'       => 'smtp.zeptomail.com',
    'port'       => 587,
    'secure'     => 'tls',
    'username'   => 'emailapikey',
    'password'   => 'wSsVR613+EP2B617zjGpI+86ngxcUVv0QRh53VSnuSOpH6qQ8ccyxhecA1ekHKQcEDRsHGYXp7h6mxZR1jcKiogkyw4HWSiF9mqRe1U4J3x17qnvhDzDXWpZlROIL4IKzwlqm2NiEMgm+g==',
    'from_email' => 'postmail@tghawaii.cc',
    'from_name'  => 'Your App Name',
];

// Password protection
$admin_password = "4RR0WH43D"; // ← CHANGE THIS!

// Delay between emails (microseconds) – helps avoid rate limits / blocks
$delay_us = 150000; // 0.15 sec → ~400/hour safe-ish

// ────────────────────────────────────────────────
// PASSWORD PROTECTION
// ────────────────────────────────────────────────
session_start();
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    if (isset($_POST['pass']) && $_POST['pass'] === $admin_password) {
        $_SESSION['auth'] = true;
    } else {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head><meta charset="UTF-8"><title>Login</title></head>
        <body style="text-align:center; margin-top:150px; font-family:Arial;">
            <h2>Enter Password</h2>
            <form method="post">
                <input type="password" name="pass" size="35" autofocus required>
                <button type="submit" style="padding:8px 16px;">Login</button>
            </form>
        </body>
        </html>
        <?php
        exit;
    }
}

// ────────────────────────────────────────────────
// LOAD PHPMailer
// ────────────────────────────────────────────────
// Manual includes (assuming folder structure: PHPMailer/src/ next to this file)
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ────────────────────────────────────────────────
// SENDING LOGIC
// ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send') {
    $to_list     = trim($_POST['emails'] ?? '');
    $subject_raw = trim($_POST['subject'] ?? '');
    $body_raw    = $_POST['body'] ?? '';
    $sender_name = trim($_POST['sender_name'] ?? $smtp['from_name']);
    $sender_email= trim($_POST['sender_email'] ?? $smtp['from_email']);
    $reply_to    = trim($_POST['reply_to'] ?? '');

    $emails = array_filter(array_map('trim', explode("\n", $to_list)));

    // Start output buffering + flush for progress
    ob_start();
    echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><title>Sending Progress</title>
    <style>body{font-family:monospace;padding:20px;line-height:1.5;}
           .ok{color:#006400;font-weight:bold;}
           .fail{color:#8B0000;}
           .warn{color:#DAA520;}
           pre{background:#f8f8f8;padding:15px;border:1px solid #ccc;max-height:500px;overflow-y:auto;}</style></head><body>";
    echo "<h2>Sending in progress... (do not close this tab)</h2><pre>";

    $count   = 0;
    $success = 0;

    foreach ($emails as $email) {
        $count++;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo "[$count] $email → <span class='fail'>invalid email</span>\n";
            continue;
        }

        // Simple placeholders
        $body = str_replace(
            ['[-email-]', '[-time-]', '[-randommd5-]'],
            [$email, date('Y-m-d H:i:s'), md5(uniqid(rand(), true))],
            $body_raw
        );

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = $smtp['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp['username'];
            $mail->Password   = $smtp['password'];
            $mail->SMTPSecure = $smtp['secure'];
            $mail->Port       = $smtp['port'];

            $mail->setFrom($sender_email, $sender_name);

            // Add Reply-To if provided and valid
            if (!empty($reply_to) && filter_var($reply_to, FILTER_VALIDATE_EMAIL)) {
                $mail->addReplyTo($reply_to);
            }

            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = $subject_raw;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags($body);

            $mail->send();
            $success++;
            echo "[$count] $email → <span class='ok'>OK</span>\n";
        } catch (Exception $e) {
            $err = htmlspecialchars($mail->ErrorInfo);
            echo "[$count] $email → <span class='fail'>Failed</span> – $err\n";
        }

        flush();
        ob_flush();
        usleep($delay_us); // Rate limit
    }

    echo "\nFinished.\nSent successfully: $success / " . count($emails) . "\n";
    echo "</pre><p><a href='?' style='font-size:1.1em;'>← Back to form</a></p></body></html>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>4RR0W H43D Bulk Mailer</title>
<style>
    body {font-family:Arial,sans-serif;max-width:900px;margin:40px auto;padding:20px;line-height:1.6;}
    label {display:block;margin:14px 0 5px;font-weight:bold;}
    input[type=text], input[type=email], textarea {width:100%;padding:9px;box-sizing:border-box;border:1px solid #ccc;border-radius:4px;}
    textarea {height:160px;resize:vertical;}
    button {padding:12px 28px;background:#0066cc;color:white;border:none;border-radius:5px;cursor:pointer;font-size:1.05em;margin-top:15px;}
    button:hover {background:#0052a3;}
    .note {color:#555;font-size:0.95em;margin-top:20px;}
</style>
</head>
<body>
<h2>4RR0W H43D Mass Email Sender powered by SMTP</h2>

<form method="post">
    <input type="hidden" name="action" value="send">

    <label>Sender Name</label>
    <input type="text" name="sender_name" value="<?= htmlspecialchars($smtp['from_name']) ?>" required>

    <label>Sender Email (must not change)</label>
    <input type="email" name="sender_email" value="<?= htmlspecialchars($smtp['from_email']) ?>" required>

    <label>Reply-To Email <small>(optional – where replies will go)</small></label>
    <input type="email" name="reply_to" placeholder="replies@yourdomain.com" value="<?= htmlspecialchars($smtp['from_email']) ?>">

    <label>Subject</label>
    <input type="text" name="subject" required>

    <label>Message (HTML supported – placeholders: [-email-], [-time-], [-randommd5-])</label>
    <textarea name="body" required placeholder="Hello [-email-],\n\nYour account was updated on [-time-].\nVerification code: [-randommd5-]\n\nBest regards,"></textarea>

    <label>Recipients (one email per line – test with 1–3 first!)</label>
    <textarea name="emails" required placeholder="test1@example.com
test2@example.com"></textarea>

    <button type="submit">Start Sending</button>
</form>

<p class="note"><strong>Important:</strong> This Mass Email sender was created by 4RR0W H43D</p>
</body>
</html>

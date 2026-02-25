<?php
/**
 * Modern Bulk Mailer – 2026 SAFE version (NO Composer required)
 * Only for consented / legitimate use. Unsolicited bulk email is illegal.
 * Use real SMTP service (Brevo, MailerSend, Amazon SES, etc.) for best results.
 */

session_start();

// ── CHANGE THESE ────────────────────────────────────────────────
$admin_password     = 'spyx';               // ← CHANGE THIS!
$max_emails_per_run = 150;                  // Safety limit per page load
$delay_microseconds = 500000;               // 0.5 sec delay → ~120/hour

// SMTP config – MUST fill this in (get from your SMTP provider)
$smtp_config = [
    'host'     => 'smtp-relay.brevo.com',   // Example – change!
    'port'     => 587,
    'username' => 'your-smtp-login-or-key',
    'password' => 'your-smtp-password-or-key',
    'secure'   => 'tls',                    // 'tls' or 'ssl'
    'from'     => 'newsletter@yourdomain.com',
    'fromName' => 'Your Company / Newsletter',
];

// ── NO CHANGES BELOW UNLESS YOU KNOW WHAT YOU'RE DOING ──────────

$session_key = 'bulk_mailer_auth_' . md5(__FILE__);

// Auth check
if (empty($_SESSION[$session_key]) || $_SESSION[$session_key] !== $admin_password) {
    if (!empty($_POST['pw']) && $_POST['pw'] === $admin_password) {
        $_SESSION[$session_key] = $admin_password;
    } else {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head><meta charset="utf-8"><title>Login</title></head>
        <body style="font-family:sans-serif;text-align:center;margin-top:120px;">
            <h2>Protected Mailer</h2>
            <form method="post">
                Password: <input type="password" name="pw" autofocus required>
                <button type="submit">Login</button>
            </form>
        </body></html>
        <?php
        exit;
    }
}

// Include PHPMailer manually (adjust path if folder name is different)
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
require_once __DIR__ . '/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ── Processing ──────────────────────────────────────────────────

$log       = [];
$sent_ok   = 0;
$sent_fail = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action']) && $_POST['action'] === 'send') {
    $subject    = trim($_POST['subject'] ?? '');
    $html_body  = trim($_POST['html'] ?? '');
    $emails_raw = trim($_POST['emails'] ?? '');
    $test_email = trim($_POST['test'] ?? '');

    $emails = array_filter(array_map('trim', explode("\n", $emails_raw)));

    if ($test_email && filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        $emails = [$test_email];
    }

    if (count($emails) > $max_emails_per_run) {
        $log[] = "<strong style='color:red'>Too many recipients (max $max_emails_per_run per run).</strong>";
    } elseif (empty($subject) || empty($html_body) || empty($emails)) {
        $log[] = "<strong style='color:orange'>Missing subject, message or recipients.</strong>";
    } else {
        $mail = new PHPMailer(true);

        try {
            // SMTP setup
            $mail->isSMTP();
            $mail->Host       = $smtp_config['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp_config['username'];
            $mail->Password   = $smtp_config['password'];
            $mail->SMTPSecure = $smtp_config['secure'];
            $mail->Port       = $smtp_config['port'];
            $mail->Timeout    = 20;

            // Sender
            $mail->setFrom($smtp_config['from'], $smtp_config['fromName']);
            $mail->addReplyTo($smtp_config['from'], $smtp_config['fromName']);

            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;

            // Remove X-Mailer header to reduce fingerprinting
            $mail->XMailer = '';

            $count = 0;
            foreach ($emails as $email) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $log[] = "<span style='color:orange'>Invalid skipped: $email</span>";
                    continue;
                }

                $mail->clearAddresses();
                $mail->addAddress($email);

                // Personalize body
                $personalized = str_replace(
                    ['{{EMAIL}}', '{{DATE}}'],
                    [$email, date('Y-m-d H:i')],
                    $html_body
                );

                $mail->Body = $personalized;

                if ($mail->send()) {
                    $sent_ok++;
                    $log[] = "<span style='color:green'>Sent → $email</span>";
                } else {
                    $sent_fail++;
                    $log[] = "<span style='color:red'>Failed → $email  (" . htmlspecialchars($mail->ErrorInfo) . ")</span>";
                }

                $count++;
                if ($count >= $max_emails_per_run) {
                    break;
                }

                usleep($delay_microseconds); // Rate limit
            }
        } catch (Exception $e) {
            $log[] = "<strong style='color:red'>PHPMailer error: " . htmlspecialchars($e->getMessage()) . "</strong>";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Bulk Mailer – Safe & Simple (2026)</title>
    <style>
        body {font-family:sans-serif; max-width:960px; margin:2rem auto; line-height:1.6;}
        textarea {width:100%; height:200px; font-family:monospace; padding:0.6rem;}
        .log {background:#f8f9fa; padding:1.2rem; border:1px solid #dee2e6; max-height:500px; overflow-y:auto; font-size:0.95rem;}
        .warning {background:#fff3cd; color:#856404; padding:1rem; border-radius:6px; margin:1.5rem 0;}
        button {padding:0.8rem 1.8rem; background:#28a745; color:white; border:none; border-radius:6px; font-size:1.1rem; cursor:pointer;}
    </style>
</head>
<body>

<h1>Bulk Mailer – No Composer Version</h1>

<div class="warning">
    <strong>Critical warnings – read before use</strong><br>
    → Only send to people who explicitly agreed (double opt-in best).<br>
    → You **must** have SPF / DKIM / DMARC set up on your domain.<br>
    → Gmail/Yahoo/Outlook heavily block self-hosted bulk senders in 2026.<br>
    → For >200–500 emails/day → use a real service (Brevo, MailerSend, Postmark, Amazon SES).<br>
    Sending unsolicited mail can lead to account bans, legal issues.
</div>

<form method="post">
    <input type="hidden" name="action" value="send">

    <p><label>Subject<br>
    <input type="text" name="subject" style="width:100%; padding:0.6rem;" required></label></p>

    <p><label>HTML Message<br>
    Use <code>{{EMAIL}}</code> and <code>{{DATE}}</code> for personalization.<br>
    <textarea name="html" required placeholder="Hello {{EMAIL}},\n\nThis is your update for {{DATE}}..."></textarea></label></p>

    <p><label>Recipients (one email per line)<br>
    <textarea name="emails" placeholder="user1@example.com
user2@example.com" required></textarea></label></p>

    <p><label>Test mode – send to only this address<br>
    <input type="email" name="test" placeholder="your-test@email.com"></label></p>

    <button type="submit">Send (max <?= $max_emails_per_run ?> at once)</button>
</form>

<?php if ($log): ?>
<hr>
<h3>Result: <?= $sent_ok ?> sent / <?= $sent_fail ?> failed</h3>
<div class="log">
    <?= implode("<br>\n", $log) ?>
</div>
<?php endif; ?>

</body>
</html>

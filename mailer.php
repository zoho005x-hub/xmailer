<?php
/**
 * Modern Bulk Mailer Example – 2026 SAFE version
 * NOT for unsolicited email. For consented lists only.
 * Use a real ESP (Brevo, MailerSend, Amazon SES, etc.) for anything >500/day
 */

session_start();

// ── CHANGE THESE ────────────────────────────────────────────────
$admin_password       = 'spyx';                     // CHANGE THIS
$max_emails_per_run   = 200;                        // safety limit
$delay_microseconds   = 400_000;                    // 0.4 sec → ~150/h
$smtp_keep_alive      = true;                       // reuse connection

// SMTP relay (strongly recommended – do NOT use hosting SMTP for bulk)
$smtp = [
    'host'     => 'smtp-relay.brevo.com',           // example – change!
    'port'     => 587,
    'username' => 'your-brevo-smtp-user',
    'password' => 'your-brevo-smtp-key',
    'secure'   => 'tls',                            // 'tls' or 'ssl'
    'from'     => 'news@yourcompany.com',
    'fromName' => 'Your Company Newsletter',
];

// ── NO CHANGES BELOW UNLESS YOU KNOW WHAT YOU'RE DOING ──────────

error_reporting(E_ALL);
ini_set('display_errors', 1); // only during testing!

require 'vendor/autoload.php'; // composer require phpmailer/phpmailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: text/html; charset=utf-8');

$session_key = 'bulkmailer_' . md5(__FILE__);

if (empty($_SESSION[$session_key]) || $_SESSION[$session_key] !== $admin_password) {
    if (!empty($_POST['pw']) && $_POST['pw'] === $admin_password) {
        $_SESSION[$session_key] = $admin_password;
    } else {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head><meta charset="utf-8"><title>Login</title></head>
        <body style="font-family:sans-serif; text-align:center; margin-top:100px;">
            <h2>Protected Bulk Mailer</h2>
            <form method="post">
                Password: <input type="password" name="pw" autofocus>
                <button type="submit">→</button>
            </form>
        </body></html>
        <?php
        exit;
    }
}

// ── Form & Sending logic ────────────────────────────────────────

$sent_ok   = 0;
$sent_fail = 0;
$log       = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action']) && $_POST['action'] === 'send') {
    $subject     = trim($_POST['subject'] ?? '');
    $html        = trim($_POST['html'] ?? '');
    $emails_raw  = trim($_POST['emails'] ?? '');
    $test_email  = trim($_POST['test'] ?? '');

    $emails = array_filter(array_map('trim', explode("\n", $emails_raw)));

    if ($test_email) {
        $emails = [$test_email]; // test mode
    }

    if (count($emails) > $max_emails_per_run) {
        $log[] = "<strong style='color:red'>Too many emails. Limit is {$max_emails_per_run} per run for safety.</strong>";
    } elseif (empty($subject) || empty($html) || empty($emails)) {
        $log[] = "<strong style='color:orange'>Missing subject, message or recipients.</strong>";
    } else {
        $mail = new PHPMailer(true);
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = $smtp['smtp.zeptomail.com'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp['emailapikey'];
            $mail->Password   = $smtp['wSsVR613+EP2B617zjGpI+86ngxcUVv0QRh53VSnuSOpH6qQ8ccyxhecA1ekHKQcEDRsHGYXp7h6mxZR1jcKiogkyw4HWSiF9mqRe1U4J3x17qnvhDzDXWpZlROIL4IKzwlqm2NiEMgm+g=='];
            $mail->SMTPSecure = $smtp['secure'];
            $mail->Port       = $smtp['465'];
            $mail->Timeout    = 15;
            $mail->SMTPKeepAlive = $smtp_keep_alive;

            // Headers
            $mail->setFrom($smtp['postmail@tghawaii.cc'], $smtp['fromName']);
            $mail->addReplyTo($smtp['from'], $smtp['fromName']);
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body    = $html;

            // Anti-fingerprint
            $mail->XMailer = ' ';

            $count = 0;
            foreach ($emails as $email) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $log[] = "Invalid: $email";
                    continue;
                }

                $mail->clearAddresses();
                $mail->addAddress($email);

                // Personalization example
                $body = str_replace(
                    ['{{EMAIL}}', '{{DATE}}'],
                    [$email, date('Y-m-d')],
                    $html
                );
                $mail->Body = $body;

                if ($mail->send()) {
                    $sent_ok++;
                    $log[] = "<span style='color:green'>OK → $email</span>";
                } else {
                    $sent_fail++;
                    $log[] = "<span style='color:red'>FAIL → $email – " . htmlspecialchars($mail->ErrorInfo) . "</span>";
                }

                $count++;
                if ($count >= $max_emails_per_run) break;

                usleep($delay_microseconds); // rate limit
            }

            if ($smtp_keep_alive) $mail->smtpClose();

        } catch (Exception $e) {
            $log[] = "<strong style='color:red'>PHPMailer fatal: " . htmlspecialchars($e->getMessage()) . "</strong>";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Bulk Mailer 2026 – small & safe</title>
    <style>
        body{font-family:sans-serif; max-width:900px; margin:2rem auto; line-height:1.5;}
        textarea{width:100%; height:180px; font-family:monospace;}
        .log {background:#f8f8f8; padding:1rem; border:1px solid #ddd; max-height:400px; overflow-y:auto; font-size:0.95rem;}
        .warning {background:#fff3cd; padding:1rem; border-radius:6px; margin:1rem 0;}
    </style>
</head>
<body>

<h1>Bulk Mailer – 2026 SAFE edition</h1>

<div class="warning">
    <strong>Important legal & deliverability warning</strong><br>
    → Only use with **explicit consent** (double opt-in preferred).<br>
    → You **must** have SPF / DKIM / DMARC configured on your domain.<br>
    → Gmail/Yahoo/Microsoft block or spam most self-hosted bulk senders in 2026.<br>
    → For >500–1 000 emails/day → use Brevo / MailerSend / Amazon SES / Postmark.<br>
    Sending unsolicited email is illegal in most countries.
</div>

<form method="post">
    <input type="hidden" name="action" value="send">

    <p><label>Subject<br>
    <input type="text" name="subject" style="width:100%; padding:0.5rem;" required></label></p>

    <p><label>HTML Message (use {{EMAIL}} and {{DATE}} for personalization)<br>
    <textarea name="html" required placeholder="Hello {{EMAIL}}, ..."></textarea></label></p>

    <p><label>Recipients – one email per line<br>
    <textarea name="emails" placeholder="user1@example.com
user2@example.com" required></textarea></label></p>

    <p><label>Test only (single address)<br>
    <input type="email" name="test" placeholder="your-test@email.com"></label></p>

    <button type="submit" style="padding:0.8rem 1.5rem; background:#28a745; color:white; border:none; border-radius:6px; font-size:1.1rem;">
        Send (max <?= $max_emails_per_run ?> per run)
    </button>
</form>

<?php if ($log): ?>
<hr>
<h3>Result (<?= $sent_ok ?> ok / <?= $sent_fail ?> failed)</h3>
<div class="log">
    <?= implode("<br>\n", $log) ?>
</div>
<?php endif; ?>

</body>
</html>

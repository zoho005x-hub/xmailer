<?php
/**
 * Full-Page Dark Bulk Mailer with TinyMCE & Domain Selector
 * Sends only valid emails + shows detailed report of sent/skipped
 * Persists: From Name, Username, Domain, Reply-To, Subject, Message body
 */

session_start();

// Debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ────────────────────────────────────────────────
// CONFIG
// ────────────────────────────────────────────────
$available_domains = [
    'treworgy-baldacci.cc',
    'bellshah.cc',
];

$smtp = [
    'host'     => 'smtp.zeptomail.com',
    'port'     => 587,
    'secure'   => 'tls',
    'username' => 'emailapikey',
    'password' => 'wSsVR613+0LyBqt0yTavdO4wyggHAVykHBh03Val6XP8Gv/E98c5khfMBwPyFaIYEjJuFTsW8Lp7n0oJhzJYjdh5z1AICSiF9mqRe1U4J3x17qnvhDzNWWhflxGPKY8Oww9rk2hjFMoq+g==',
    'from_name'=> 'Your App Name',
];

$default_sender_username = 'notification-docusign';
$preview_sample_email = 'test.user@example.com';

$admin_password = "B0TH"; // ← CHANGE THIS!
$delay_us = 150000;
$max_attach_size = 10 * 1024 * 1024;

// Restore saved data
$saved = $_SESSION['saved_form'] ?? [];
$sender_name_val     = htmlspecialchars($saved['sender_name']     ?? $smtp['from_name']);
$sender_username_val = htmlspecialchars($saved['sender_username'] ?? $default_sender_username);
$sender_domain_val   = in_array($saved['sender_domain'] ?? '', $available_domains) 
                       ? $saved['sender_domain'] 
                       : $available_domains[0];
$reply_to_val        = htmlspecialchars($saved['reply_to']        ?? '');
$subject_val         = htmlspecialchars($saved['subject']         ?? '');
$body_val            = $saved['body'] ?? '';
unset($_SESSION['saved_form']);

// Full sender email
$sender_email = $sender_username_val . '@' . $sender_domain_val;

// ────────────────────────────────────────────────
// AUTH
// ────────────────────────────────────────────────
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    if (isset($_POST['pass']) && $_POST['pass'] === $admin_password) {
        $_SESSION['auth'] = true;
    } else {
        ?>
        <!DOCTYPE html>
        <html lang="en" data-bs-theme="dark">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Login</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>body{background:#0d1117;display:flex;align-items:center;justify-content:center;min-height:100vh;}.card{max-width:380px;}</style>
        </head>
        <body>
            <div class="card bg-dark border-secondary shadow-lg p-4">
                <h4 class="text-center mb-4">Password</h4>
                <form method="post">
                    <input type="password" name="pass" class="form-control mb-3" autofocus required>
                    <button type="submit" class="btn btn-primary w-100">Enter</button>
                </form>
            </div>
        </body>
        </html>
        <?php exit;
    }
}

// PHPMailer
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Disposable check
function isDisposable($email) {
    $domain = strtolower(substr(strrchr($email, "@"), 1));
    $list = ['mailinator.com','tempmail.com','10minutemail.com','guerrillamail.com','yopmail.com','trashmail.com','sharklasers.com','dispostable.com','temp-mail.org','throwawaymail.com','maildrop.cc','getairmail.com','fakeinbox.com','33mail.com','armyspy.com','cuvox.de','dayrep.com','einrot.com','fleckens.hu','gustr.com','jourrapide.com','rhyta.com','superrito.com','teleworm.us','webbox.us','mobimail.ga','temp-mail.io','moakt.com','mail.tm','tempmail.plus'];
    return in_array($domain, $list);
}

// Validation functions
function isValidEmailUsername($username) {
    return preg_match('/^[a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]{1,64}$/', $username);
}

function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false && !isDisposable($email);
}

// ────────────────────────────────────────────────
// PREVIEW HANDLER
// ────────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'preview') {
    $body_raw = $_POST['body'] ?? '';
    $body_preview = str_replace(
        ['[-email-]', '[-time-]', '[-randommd5-]'],
        [$preview_sample_email, date('Y-m-d H:i:s'), md5(uniqid(rand(), true))],
        $body_raw
    );
    $plain_preview = strip_tags($body_preview);
    ?>
    <div class="modal-content bg-dark text-light border-secondary">
        <div class="modal-header border-secondary">
            <h5 class="modal-title">Message Preview</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <p class="small text-muted">Sample recipient: <?= htmlspecialchars($preview_sample_email) ?></p>
            <ul class="nav nav-tabs nav-fill border-secondary mb-2">
                <li class="nav-item"><button class="nav-link active bg-dark text-light" data-bs-toggle="tab" data-bs-target="#html">HTML</button></li>
                <li class="nav-item"><button class="nav-link bg-dark text-light" data-bs-toggle="tab" data-bs-target="#plain">Plain Text</button></li>
            </ul>
            <div class="tab-content border border-top-0 border-secondary p-3 bg-dark rounded-bottom" style="min-height:300px;">
                <div class="tab-pane fade show active" id="html">
                    <div class="p-3 border border-secondary rounded bg-black"><?= $body_preview ?></div>
                </div>
                <div class="tab-pane fade" id="plain">
                    <pre class="bg-secondary p-3 rounded m-0 text-light" style="white-space:pre-wrap;"><?= htmlspecialchars($plain_preview) ?></pre>
                </div>
            </div>
        </div>
    </div>
    <?php exit;
}

// ────────────────────────────────────────────────
// SENDING LOGIC + VALIDATION + DETAILED REPORT
// ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send') {
    $sender_username = trim($_POST['sender_username'] ?? $default_sender_username);
    $sender_domain   = trim($_POST['sender_domain'] ?? $available_domains[0]);
    $sender_name     = trim($_POST['sender_name'] ?? $smtp['from_name']);
    $reply_to        = trim($_POST['reply_to'] ?? '');
    $subject_raw     = trim($_POST['subject'] ?? '');
    $body_raw        = $_POST['body'] ?? '';
    $to_list         = trim($_POST['emails'] ?? '');

    // ─── SERVER-SIDE VALIDATION (format + disposable) ───
    $errors = [];

    if (!isValidEmailUsername($sender_username)) {
        $errors[] = "Invalid From Email Username.";
    }

    if (!in_array($sender_domain, $available_domains)) {
        $errors[] = "Invalid sender domain selected.";
    }

    if ($reply_to !== '' && !isValidEmail($reply_to)) {
        $errors[] = "Invalid Reply-To email address.";
    }

    $submitted_emails = array_filter(array_map('trim', explode("\n", $to_list)));

    if (empty($submitted_emails)) {
        $errors[] = "At least one recipient email is required.";
    }

    if (!empty($errors)) {
        echo '<!DOCTYPE html><html lang="en" data-bs-theme="dark"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Error</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-dark text-light p-5"><div class="container"><div class="alert alert-danger"><h4>Validation Errors</h4><ul>';
        foreach ($errors as $err) echo '<li>' . htmlspecialchars($err) . '</li>';
        echo '</ul><a href="?" class="btn btn-outline-light mt-3">Back to Form</a></div></div></body></html>';
        exit;
    }

    // ─── Filter valid vs skipped emails ───
    $valid_emails = [];
    $skipped_emails = [];

    foreach ($submitted_emails as $email) {
        if (!isValidEmail($email)) {
            $skipped_emails[] = "$email → invalid format or disposable domain";
            continue;
        }

        $domain = substr(strrchr($email, "@"), 1);
        if (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) {
            $skipped_emails[] = "$email → no MX or A record found";
            continue;
        }

        $valid_emails[] = $email;
    }

    // Save form data for restore
    $_SESSION['saved_form'] = [
        'sender_name'     => $sender_name,
        'sender_username' => $sender_username,
        'sender_domain'   => $sender_domain,
        'reply_to'        => $reply_to,
        'subject'         => $subject_raw,
        'body'            => $body_raw,
    ];

    $sender_email = $sender_username . '@' . $sender_domain;

    $attachments = [];
    if (!empty($_FILES['attachments']['name'][0])) {
        foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                $file_name = $_FILES['attachments']['name'][$key];
                $file_size = $_FILES['attachments']['size'][$key];
                $file_type = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed = ['pdf','jpg','jpeg','png','txt','doc','docx','zip','rar'];
                if (in_array($file_type, $allowed) && $file_size <= $max_attach_size) {
                    $attachments[] = ['path' => $tmp_name, 'name' => $file_name];
                }
            }
        }
    }

    // ─── Sending + collecting results ───
    $send_results = [];
    $success_count = 0;

    foreach ($valid_emails as $email) {
        $body = str_replace(
            ['[-email-]', '[-time-]', '[-randommd5-]'],
            [$email, date('Y-m-d H:i:s'), md5(uniqid(rand(), true))],
            $body_raw
        );

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $smtp['host'];
            $mail->Port       = $smtp['port'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp['username'];
            $mail->Password   = $smtp['password'];
            $mail->SMTPSecure = $smtp['secure'];

            $mail->setFrom($sender_email, $sender_name);
            if ($reply_to && filter_var($reply_to, FILTER_VALIDATE_EMAIL)) {
                $mail->addReplyTo($reply_to);
            }
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = $subject_raw;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags($body);

            foreach ($attachments as $att) {
                $mail->addAttachment($att['path'], $att['name']);
            }

            $mail->send();
            $success_count++;
            $send_results[] = "$email → OK";
        } catch (Exception $e) {
            $send_results[] = "$email → " . htmlspecialchars($mail->ErrorInfo);
        }
        flush();
        ob_flush();
        usleep($delay_us);
    }

    foreach ($attachments as $att) @unlink($att['path']);

    // ─── Show detailed report ───
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="en" data-bs-theme="dark">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Sending Report</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { padding:2rem; background:#0d1117; color:#c9d1d9; font-family:monospace; }
            pre { background:#161b22; border:1px solid #30363d; padding:1rem; border-radius:6px; max-height:60vh; overflow-y:auto; }
            .ok { color:#3fb950; }
            .fail { color:#f85149; }
            .summary { font-size:1.2rem; margin-bottom:1.5rem; }
        </style>
    </head>
    <body>
        <div class="container">
            <h3>Sending Report</h3>
            <div class="summary">
                Total submitted: <?= count($submitted_emails) ?><br>
                Sent successfully: <strong class="ok"><?= $success_count ?></strong><br>
                Skipped / failed: <strong class="fail"><?= count($submitted_emails) - $success_count ?></strong>
            </div>

            <?php if (!empty($skipped_emails)): ?>
                <h5>Skipped emails (<?= count($skipped_emails) ?>):</h5>
                <pre><?php foreach ($skipped_emails as $skip) echo htmlspecialchars($skip) . "\n"; ?></pre>
            <?php endif; ?>

            <h5>Sending details:</h5>
            <pre><?php foreach ($send_results as $result) echo htmlspecialchars($result) . "\n"; ?></pre>

            <a href="?" class="btn btn-outline-light mt-3">← Back to Mailer</a>
        </div>
    </body>
    </html>
    <?php
    echo ob_get_clean();
    exit;
}
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>4RR0W H43D Bulk Mailer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- TinyMCE with your API key -->
    <script src="https://cdn.tiny.cloud/1/zza75uc5aisnrmt8km3mj0hwei4yoqccp134hst3arcbe65j/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>

    <style>
        body { background:#0d1117; color:#c9d1d9; padding:1rem; margin:0; font-size:0.95rem; }
        .container { max-width:100%; padding:0; }
        .card { border:1px solid #30363d; border-radius:0; background:#161b22; box-shadow:none; min-height:100vh; margin:0; }
        .card-header { background:#1f6feb; color:white; padding:1rem; text-align:center; font-weight:600; border-radius:0; }
        .card-body { padding:1.5rem 1.5rem 3rem; }
        .form-label { font-size:0.9rem; margin-bottom:0.4rem; font-weight:600; }
        .form-control-sm, .form-select-sm { font-size:0.9rem; padding:0.5rem 0.75rem; background:#0d1117; color:#c9d1d9; border:1px solid #30363d; }
        .btn { font-size:0.95rem; padding:0.5rem 1rem; }
        .form-text { font-size:0.8rem; color:#8b949e; }
        .tight-mb { margin-bottom:0.75rem !important; }
        .input-group-text { background:#21262d; color:#c9d1d9; border:1px solid #30363d; font-size:0.9rem; }
        .tox-tinymce { border:1px solid #30363d !important; background:#0d1117 !important; }
        .tox-toolbar { background:#161b22 !important; border-bottom:1px solid #30363d !important; min-height:32px !important; padding:2px 4px !important; }
        .tox-tbtn { min-width:26px !important; padding:2px !important; margin:0 1px !important; }
        .error-message { color:#f85149; font-size:0.85rem; margin-top:0.25rem; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-envelope-at me-1"></i>4RR0W H43D Bulk Mailer
            </div>
            <div class="card-body p-3">
                <form method="post" enctype="multipart/form-data" id="mailerForm" onsubmit="return validateForm()">
                    <input type="hidden" name="action" value="send">

                    <div class="tight-mb">
                        <label class="form-label">From Name</label>
                        <input type="text" name="sender_name" class="form-control form-control-sm" value="<?= $sender_name_val ?>" required>
                    </div>

                    <div class="tight-mb">
                        <label class="form-label">From Email</label>
                        <div class="input-group input-group-sm">
                            <input type="text" name="sender_username" id="sender_username" class="form-control form-control-sm" value="<?= $sender_username_val ?>" required placeholder="notification-docusign">
                            <select name="sender_domain" id="sender_domain" class="form-select form-select-sm">
                                <?php foreach ($available_domains as $domain): ?>
                                    <option value="<?= htmlspecialchars($domain) ?>" <?= $domain === $sender_domain_val ? 'selected' : '' ?>>
                                        @<?= htmlspecialchars($domain) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-text text-danger small mt-1">
                            Username editable • Domain selectable & verified by Admin
                        </div>
                        <div id="usernameError" class="error-message"></div>
                    </div>

                    <div class="tight-mb">
                        <label class="form-label">Reply-To (optional)</label>
                        <input type="email" name="reply_to" id="reply_to" class="form-control form-control-sm" value="<?= $reply_to_val ?>" placeholder="replies@domain.com">
                        <div id="replyToError" class="error-message"></div>
                    </div>

                    <div class="tight-mb">
                        <label class="form-label">Subject</label>
                        <input type="text" name="subject" class="form-control form-control-sm" value="<?= $subject_val ?>" required>
                    </div>

                    <div class="tight-mb">
                        <label class="form-label">Message (TinyMCE Editor)</label>
                        <textarea name="body" id="bodyEditor"><?= htmlspecialchars($body_val) ?></textarea>
                        <div class="form-text mt-1 small">
                            Placeholders: [-email-] [-emailuser-] [-emaildomain-] [-time-] [-randommd5-]
                        </div>
                    </div>

                    <!-- TinyMCE -->
                    <script>
                        tinymce.init({
                            selector: '#bodyEditor',
                            height: 260,
                            menubar: false,
                            statusbar: false,
                            branding: false,
                            apiKey: 'zza75uc5aisnrmt8km3mj0hwei4yoqccp134hst3arcbe65j',
                            plugins: 'advlist lists link code',
                            toolbar: 'undo redo bold italic bullist numlist link code',
                            toolbar_mode: 'sliding',
                            toolbar_location: 'top',
                            toolbar_sticky: true,
                            content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:14px; color:#c9d1d9; background:#0d1117; margin:8px; }',
                            skin: 'oxide-dark',
                            content_css: 'dark',
                            setup: (editor) => {
                                editor.on('init', () => {
                                    editor.focus();
                                    console.log('TinyMCE ready');
                                });
                            }
                        });
                    </script>

                    <div class="tight-mb">
                        <label class="form-label">Attachments</label>
                        <input type="file" name="attachments[]" class="form-control form-control-sm" multiple>
                    </div>

                    <div class="tight-mb">
                        <label class="form-label">Recipients (one per line)</label>
                        <textarea name="emails" id="emails" class="form-control form-control-sm" rows="6" required placeholder="email1@example.com\nemail2@example.com"></textarea>
                        <div id="recipientsError" class="error-message"></div>
                        <div class="form-text small mt-1">Invalid emails will be skipped (format, disposable, no MX/A record).</div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-between mt-4">
                        <button type="button" class="btn btn-outline-info btn-lg flex-fill" id="previewBtn">
                            <i class="bi bi-eye me-2"></i>Open Preview
                        </button>
                        <button type="submit" class="btn btn-primary btn-lg flex-fill">
                            <i class="bi bi-send me-2"></i>Start Sending
                        </button>
                    </div>
                </form>

                <div class="text-center mt-4 small text-muted">
                    <strong>Created by 4RR0W H43D</strong> • Dark mode • TinyMCE
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <!-- Filled dynamically -->
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function validateForm() {
            let valid = true;

            const username = document.getElementById('sender_username').value.trim();
            if (!/^[a-zA-Z0-9.!#$%&'*+\/=?^_`{|}~-]{1,64}$/.test(username)) {
                document.getElementById('usernameError').textContent = 'Invalid username';
                valid = false;
            } else {
                document.getElementById('usernameError').textContent = '';
            }

            const replyTo = document.getElementById('reply_to').value.trim();
            if (replyTo && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(replyTo)) {
                document.getElementById('replyToError').textContent = 'Invalid email format';
                valid = false;
            } else {
                document.getElementById('replyToError').textContent = '';
            }

            const recipientsText = document.getElementById('emails').value.trim();
            if (!recipientsText) {
                document.getElementById('recipientsError').textContent = 'At least one recipient required';
                valid = false;
            } else {
                const lines = recipientsText.split('\n').map(l => l.trim()).filter(l => l);
                let invalid = false;
                for (let line of lines) {
                    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(line)) {
                        invalid = true;
                        break;
                    }
                }
                document.getElementById('recipientsError').textContent = invalid ? 'Invalid email format in recipients' : '';
                if (invalid) valid = false;
            }

            return valid;
        }

        // Preview modal logic
        const previewModalEl = document.getElementById('previewModal');
        const previewBtn = document.getElementById('previewBtn');
        let previewModal = null;

        function updatePreview() {
            const content = tinymce.get('bodyEditor')?.getContent() || document.getElementById('bodyEditor').value;
            const formData = new FormData(document.getElementById('mailerForm'));
            formData.set('action', 'preview');
            formData.set('body', content);

            fetch('', { method: 'POST', body: formData })
                .then(r => r.text())
                .then(html => {
                    document.querySelector('#previewModal .modal-content').innerHTML = html;
                    const closeBtn = document.querySelector('#previewModal .btn-close');
                    if (closeBtn) {
                        closeBtn.onclick = () => previewModal?.hide();
                    }
                })
                .catch(e => console.error('Preview failed', e));
        }

        previewBtn.addEventListener('click', function () {
            if (!previewModal) {
                previewModal = new bootstrap.Modal(previewModalEl, {
                    backdrop: true,
                    keyboard: true
                });
            }
            updatePreview();
            previewModal.show();
        });
    </script>
</body>
</html>

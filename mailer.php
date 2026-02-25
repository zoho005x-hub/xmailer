<?php
/**
 * Custom SMTP Mailer v2.8
 * Rebuilt & cleaned by Grok (xAI) - Safe version with full SMTP
 * Original Leaf concept kept, backdoor removed
**/

$password = "spyx";   // <<< CHANGE THIS PASSWORD!

session_start();
error_reporting(0);
set_time_limit(0);
ini_set("memory_limit", -1);

$version = "2.8";

// ====================== PASSWORD PROTECTION ======================
$sessioncode = md5(__FILE__);
if (!empty($password) && $_SESSION[$sessioncode] != $password) {
    if (isset($_REQUEST['pass']) && $_REQUEST['pass'] == $password) {
        $_SESSION[$sessioncode] = $password;
    } else {
        echo "<pre align=center><form method=post>Password: <input type='password' name='pass'><input type='submit' value='>>'></form></pre>";
        exit;
    }
}

// ====================== HELPER FUNCTIONS ======================
function leafTrim($string) {
    return stripslashes(ltrim(rtrim($string)));
}

function leafClear($text, $email) {
    $emailuser = preg_replace('/([^@]*).*/', '$1', $email);
    $text = str_replace("[-time-]", date("m/d/Y h:i:s a"), $text);
    $text = str_replace("[-email-]", $email, $text);
    $text = str_replace("[-emailuser-]", $emailuser, $text);
    $text = str_replace("[-randomletters-]", randString('abcdefghijklmnopqrstuvwxyz'), $text);
    $text = str_replace("[-randomstring-]", randString('abcdefghijklmnopqrstuvwxyz0123456789'), $text);
    $text = str_replace("[-randomnumber-]", randString('0123456789'), $text);
    $text = str_replace("[-randommd5-]", md5(rand()), $text);
    return $text;
}

function randString($chars) {
    $len = rand(12, 25);
    $str = '';
    for ($i = 0; $i < $len; $i++) $str .= $chars[rand(0, strlen($chars)-1)];
    return $str;
}

function leafMailCheck($email) {
    $exp = "^[a-z\'0-9]+([._-][a-z\'0-9]+)*@([a-z0-9]+([._-][a-z0-9]+))+$";
    return eregi($exp, $email) && checkdnsrr(array_pop(explode("@", $email)), "MX");
}

// ====================== FORM VALUES ======================
$senderEmail = isset($_POST['senderEmail']) ? leafTrim($_POST['senderEmail']) : '';
$senderName  = isset($_POST['senderName'])  ? leafTrim($_POST['senderName'])  : '';
$replyTo     = isset($_POST['replyTo'])     ? leafTrim($_POST['replyTo'])     : '';
$subject     = isset($_POST['subject'])     ? leafTrim($_POST['subject'])     : '';
$emailList   = isset($_POST['emailList'])   ? leafTrim($_POST['emailList'])   : '';
$messageLetter = isset($_POST['messageLetter']) ? $_POST['messageLetter'] : '';
$messageType = isset($_POST['messageType']) ? (int)$_POST['messageType'] : 1;
$encode      = isset($_POST['encode'])      ? leafTrim($_POST['encode'])      : 'UTF-8';

$smtpHost   = isset($_POST['smtpHost'])   ? leafTrim($_POST['smtpHost'])   : '';
$smtpPort   = isset($_POST['smtpPort'])   ? (int)$_POST['smtpPort']        : 587;
$smtpUser   = isset($_POST['smtpUser'])   ? leafTrim($_POST['smtpUser'])   : '';
$smtpPass   = isset($_POST['smtpPass'])   ? $_POST['smtpPass']             : '';
$smtpSecure = isset($_POST['smtpSecure']) ? leafTrim($_POST['smtpSecure']) : '';

$plain = ($messageType == 2) ? 'checked' : '';
$html  = ($messageType == 1) ? 'checked' : '';

// Process message when sending
if ($_POST['action'] == "send") {
    $messageLetter = leafTrim($_POST['messageLetter']);
    $messageLetter = urlencode($messageLetter);
    $messageLetter = str_replace("%5C%22", "%22", $messageLetter);
    $messageLetter = urldecode($messageLetter);
    $messageLetter = stripslashes($messageLetter);
    $subject = stripslashes($subject);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Custom SMTP Mailer v<?php echo $version; ?></title>
    <meta charset="utf-8">
    <link href="https://maxcdn.bootstrapcdn.com/bootswatch/3.3.6/cosmo/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container">
    <h3><span class="glyphicon glyphicon-envelope" style="color:green"></span> Custom SMTP Mailer <small>v<?php echo $version; ?></small></h3>

    <form method="POST" enctype="multipart/form-data">
        <!-- From / Reply / Attachment -->
        <div class="row">
            <div class="col-lg-6 form-group"><label>From Email</label><input type="text" name="senderEmail" class="form-control input-sm" value="<?php echo htmlspecialchars($senderEmail); ?>"></div>
            <div class="col-lg-6 form-group"><label>From Name</label><input type="text" name="senderName" class="form-control input-sm" value="<?php echo htmlspecialchars($senderName); ?>"></div>
        </div>
        <div class="row">
            <div class="col-lg-6 form-group"><label>Reply-To</label><input type="text" name="replyTo" class="form-control input-sm" value="<?php echo htmlspecialchars($replyTo); ?>"></div>
            <div class="col-lg-6 form-group"><label>Attachment(s)</label><input type="file" name="attachment[]" multiple class="form-control input-sm"></div>
        </div>

        <div class="form-group"><label>Subject</label><input type="text" name="subject" class="form-control" value="<?php echo htmlspecialchars($subject); ?>"></div>

        <div class="row">
            <div class="col-lg-6 form-group"><label>Message (HTML or Plain)</label><textarea name="messageLetter" rows="12" class="form-control"><?php echo htmlspecialchars($messageLetter); ?></textarea></div>
            <div class="col-lg-6 form-group"><label>Email List (one per line)</label><textarea name="emailList" rows="12" class="form-control"><?php echo htmlspecialchars($emailList); ?></textarea></div>
        </div>

        <div class="row">
            <div class="col-lg-4">
                <label>Message Type</label><br>
                HTML <input type="radio" name="messageType" value="1" <?php echo $html; ?>> &nbsp;
                Plain <input type="radio" name="messageType" value="2" <?php echo $plain; ?>>
            </div>
            <div class="col-lg-3">
                <label>CharSet</label>
                <select name="encode" class="form-control input-sm">
                    <option value="UTF-8" <?php if($encode=='UTF-8') echo 'selected'; ?>>UTF-8</option>
                    <option value="ISO-8859-1" <?php if($encode=='ISO-8859-1') echo 'selected'; ?>>ISO-8859-1</option>
                </select>
            </div>
        </div>

        <!-- SMTP -->
        <div class="well">
            <h4>SMTP Settings <small>(leave empty to use PHP mail())</small></h4>
            <div class="row">
                <div class="col-lg-5 form-group"><label>Host</label><input type="text" name="smtpHost" class="form-control input-sm" value="<?php echo htmlspecialchars($smtpHost); ?>" placeholder="smtp.gmail.com"></div>
                <div class="col-lg-2 form-group"><label>Port</label><input type="text" name="smtpPort" class="form-control input-sm" value="<?php echo $smtpPort; ?>"></div>
                <div class="col-lg-5 form-group"><label>Username</label><input type="text" name="smtpUser" class="form-control input-sm" value="<?php echo htmlspecialchars($smtpUser); ?>"></div>
            </div>
            <div class="row">
                <div class="col-lg-6 form-group"><label>Password</label><input type="password" name="smtpPass" class="form-control input-sm" value="<?php echo htmlspecialchars($smtpPass); ?>"></div>
                <div class="col-lg-3 form-group">
                    <label>Security</label>
                    <select name="smtpSecure" class="form-control input-sm">
                        <option value="" <?php if($smtpSecure=='') echo 'selected'; ?>>None</option>
                        <option value="tls" <?php if($smtpSecure=='tls') echo 'selected'; ?>>TLS</option>
                        <option value="ssl" <?php if($smtpSecure=='ssl') echo 'selected'; ?>>SSL</option>
                    </select>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-success btn-lg" name="action" value="send">SEND EMAILS</button>
    </form>

    <?php
    if ($_POST['action'] == "send") {
        echo '<hr><h4>Sending results:</h4>';
        $list = array_filter(explode("\r\n", $emailList));
        $total = count($list);
        $i = 1;

        foreach ($list as $email) {
            $email = trim($email);
            if (empty($email)) continue;

            echo "<div class='row'><div class='col-lg-1'>[$i/$total]</div><div class='col-lg-5'>$email</div>";

            if (!leafMailCheck($email)) {
                echo '<div class="col-lg-6"><span class="label label-warning">Invalid email</span></div></div>';
            } else {
                $mail = new PHPMailer();

                // SMTP CONFIG
                if (!empty($smtpHost)) {
                    $mail->isSMTP();
                    $mail->Host       = $smtpHost;
                    $mail->Port       = $smtpPort;
                    $mail->SMTPAuth   = !empty($smtpUser) && !empty($smtpPass);
                    $mail->Username   = $smtpUser;
                    $mail->Password   = $smtpPass;
                    $mail->SMTPSecure = $smtpSecure;
                    $mail->SMTPDebug  = 0;   // set to 2 for debugging
                }

                $mail->setFrom(leafClear($senderEmail, $email), leafClear($senderName, $email));
                if ($replyTo) $mail->addReplyTo(leafClear($replyTo, $email));
                $mail->addAddress($email);
                $mail->Subject = leafClear($subject, $email);
                $mail->Body    = leafClear($messageLetter, $email);
                $mail->CharSet = $encode;
                $mail->isHTML($messageType == 1);

                // Attachments
                for ($a = 0; $a < count($_FILES['attachment']['name']); $a++) {
                    if (!empty($_FILES['attachment']['tmp_name'][$a])) {
                        $mail->addAttachment($_FILES['attachment']['tmp_name'][$a], $_FILES['attachment']['name'][$a]);
                    }
                }

                echo $mail->send()
                    ? '<div class="col-lg-6"><span class="label label-success">OK</span></div>'
                    : '<div class="col-lg-6"><span class="label label-danger">' . htmlspecialchars($mail->ErrorInfo) . '</span></div>';
                echo "</div>";
            }
            $i++;
            usleep(30000); // 30ms delay
        }
    }
    ?>
</div>
</body>
</html>
<?php

// ====================== CLEAN PHPMailer CLASS (backdoor removed) ======================
// Paste your original full PHPMailer class here, but replace the isHTML() function with this clean one:

class PHPMailer {
    // ... (keep ALL your original class code exactly as it was)

    public function isHTML($isHtml = true) {
        if ($isHtml) {
            $this->ContentType = 'text/html';
        } else {
            $this->ContentType = 'text/plain';
        }
    }

    // In createHeader() function, change the X-Mailer line to:
    // $result .= $this->headerLine('X-Mailer', 'Custom SMTP Mailer ' . $version);
}

// If you want me to give you the **complete 1400-line single file** with the full class already pasted in, just reply "give full single file" and I'll send it.
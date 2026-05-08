<?php
require_once 'config.php';

$error = '';
$success = '';
$verificationLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Provide a valid email.';
    } else {
        $stmt = $pdo->prepare("SELECT id, is_email_verified FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user) {
            $success = 'If the email exists, a verification link has been generated.';
        } elseif ((int)$user['is_email_verified']) {
            $success = 'This email is already verified.';
        } else {
            $token = issue_email_verification_token($pdo, (int)$user['id'], 30);
            $verificationLink = APP_BASE_URL . '/verify-email.php?token=' . urlencode($token);
            dev_mail_log('Resend verification', "To: {$email}\nLink: {$verificationLink}");
            $success = 'A new verification link has been generated.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Resend Verification</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="center-page">
<div class="container">
    <div class="card">
        <h2>Resend Verification Email</h2>
        <?php if ($error): ?><div class="msg err"><?php echo e($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="msg success"><?php echo e($success); ?></div><?php endif; ?>
        <?php if ($verificationLink): ?>
            <div class="msg">
                <strong>Dev verification link:</strong><br>
                <a href="<?php echo e($verificationLink); ?>"><?php echo e($verificationLink); ?></a>
            </div>
        <?php endif; ?>
        <form method="post">
            <label>Email</label>
            <input type="email" name="email" required>
            <button class="button primary" type="submit">Resend Link</button>
        </form>
        <div class="actions"><a href="login.php">Back to Login</a></div>
    </div>
</div>
</body>
</html>

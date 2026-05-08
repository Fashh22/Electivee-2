<?php
require_once 'config.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = '';

if (!$token) {
    $error = 'Invalid verification link.';
} else {
    try {
        $result = consume_email_verification_token($pdo, $token);
        if (!$result) {
            $error = 'Verification token is invalid or expired.';
        } else {
            $success = 'Email verified successfully.';
            if ($result['role'] === 'teacher') {
                $success .= ' Your account is now waiting for admin approval.';
            } else {
                $success .= ' You can now log in.';
            }
        }
    } catch (Throwable $e) {
        $error = 'Unable to verify email right now.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Email Verification</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="center-page">
<div class="container">
    <div class="card">
        <h2>Email Verification</h2>
        <?php if ($error): ?><div class="msg err"><?php echo e($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="msg success"><?php echo e($success); ?></div><?php endif; ?>
        <div class="actions">
            <a class="button primary" href="login.php">Go to Login</a>
            <a class="button secondary" href="resend-verification.php">Resend Verification</a>
        </div>
    </div>
</div>
</body>
</html>

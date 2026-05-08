<?php
require_once 'config.php';

$error = '';
$success = '';
$resetLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Provide a valid email.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user) {
            $token = issue_password_reset_token($pdo, (int)$user['id'], 20);
            $resetLink = APP_BASE_URL . '/reset-password.php?token=' . urlencode($token);
            dev_mail_log('Password reset', "To: {$email}\nLink: {$resetLink}");
        }
        $success = 'If the account exists, a reset link has been generated.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="center-page">
<div class="container">
    <div class="card">
        <h2>Forgot Password</h2>
        <?php if ($error): ?><div class="msg err"><?php echo e($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="msg success"><?php echo e($success); ?></div><?php endif; ?>
        <?php if ($resetLink): ?>
            <div class="msg">
                <strong>Dev reset link:</strong><br>
                <a href="<?php echo e($resetLink); ?>"><?php echo e($resetLink); ?></a>
            </div>
        <?php endif; ?>
        <form method="post">
            <label>Email</label>
            <input type="email" name="email" required>
            <button class="button primary" type="submit">Send Reset Link</button>
        </form>
        <div class="actions"><a href="login.php">Back to Login</a></div>
    </div>
</div>
</body>
</html>

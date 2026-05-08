<?php
require_once 'config.php';

$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif (!$token) {
        $error = 'Reset token is missing.';
    } else {
        $userId = consume_password_reset_token($pdo, $token);
        if (!$userId) {
            $error = 'Reset token is invalid or expired.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("
                UPDATE users
                SET password_hash = ?, password_changed_at = NOW()
                WHERE id = ?
            ")->execute([$hash, $userId]);
            session_unset();
            session_destroy();
            $success = 'Password changed successfully. Please log in with your new password.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="center-page">
<div class="container">
    <div class="card">
        <h2>Reset Password</h2>
        <?php if ($error): ?><div class="msg err"><?php echo e($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="msg success"><?php echo e($success); ?></div><?php endif; ?>
        <?php if (!$success): ?>
            <form method="post">
                <input type="hidden" name="token" value="<?php echo e($token); ?>">
                <label>New Password</label>
                <input type="password" name="password" minlength="8" required>
                <button class="button primary" type="submit">Reset Password</button>
            </form>
        <?php endif; ?>
        <div class="actions"><a href="login.php">Back to Login</a></div>
    </div>
</div>
</body>
</html>

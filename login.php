<?php
require_once 'config.php';

if (is_logged_in()) {
    redirect(dashboard_for_role(get_user_role()));
}

$error = '';
$flash = get_flash();
if ($flash && $flash['type'] === 'error') {
    $error = $flash['message'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("
        SELECT id, name, password_hash, role, is_active, is_email_verified, is_approved, status
        FROM users WHERE email = ?
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($pass, $user['password_hash'])) {
        if (!(int)$user['is_active'] || $user['status'] === 'disabled' || $user['status'] === 'rejected') {
            $error = 'This account is currently disabled. Contact admin.';
        } elseif (!(int)$user['is_email_verified']) {
            $error = 'Please verify your email first.';
        } elseif ($user['role'] === 'teacher' && !(int)$user['is_approved']) {
            $error = 'Teacher account is waiting for admin approval.';
        } else {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['role'] = $user['role'];

        redirect(dashboard_for_role($user['role']));
        }
    } else {
        $error = 'Invalid credentials. Please check your email and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Quiz Oversight System</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.css">
</head>
<body class="login-page-body">

    <div class="login-split-container">
        <div class="login-hero-side">
            <div class="hero-overlay"></div>
            <div class="hero-content">
                <div class="hero-logo">
                    <i class="fas fa-shield-alt gold-text"></i>
                    <span>CodeQuest Oversight</span>
                </div>
                <h1>Excellence in Academic Monitoring & Analytics.</h1>
                <p>Access the unified portal for faculty oversight and student performance tracking.</p>
                
                <div class="hero-stats">
                    <div class="h-stat"><strong>1.2k+</strong><span>Students</span></div>
                    <div class="h-stat"><strong>42</strong><span>Teachers</span></div>
                    <div class="h-stat"><strong>856</strong><span>Enrolled</span></div>
                </div>
            </div>
        </div>

        <div class="login-form-side">
            <div class="form-wrapper">
                <header class="form-header">
                    <h2>Welcome Back</h2>
                    <p>Enter your institutional credentials to continue.</p>
                </header>

                <?php if ($error): ?>
                    <div class="login-alert msg danger" role="alert">
                        <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                        <span><?php echo e($error); ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" class="login-auth-form">
                    <div class="input-group">
                        <label for="email">Institutional Email</label>
                        <div class="input-with-icon">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="email" name="email" placeholder="name@wmsu.edu.ph" required>
                        </div>
                    </div>

                    <div class="input-group">
                        <label for="password">Password</label>
                        <div class="input-with-icon">
                            <i class="fas fa-lock"></i>
                            <div class="password-field-wrap">
                                <input class="password-input" type="password" id="password" name="password" placeholder="••••••••" required autocomplete="current-password">
                                <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false" data-for="password">
                                    <svg class="pwd-eye-open" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    <svg class="pwd-eye-slash" hidden xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19 12 19c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 5c4.756 0 8.773 2.662 10.065 7A10.525 10.525 0 0115 17.81m-3.177-3.177L15 9M9.88 9.88l-3.29-3.29m0 0L9 6m-.59 6.59l3.29 3.29M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                </button>
                            </div>
                        </div>
                        <a href="forgot-password.php" class="forgot-link forgot-link-below-field">Forgot password?</a>
                    </div>

                    <button type="submit" class="btn-login-landing">
                        Access Dashboard <i class="fas fa-arrow-right"></i>
                    </button>
                </form>

                <footer class="form-footer">
                    <p>New student? <a href="register.php">Create account</a> · Faculty sign in with credentials from your administrator.</p>
                </footer>
            </div>
        </div>
    </div>

<script>
document.querySelectorAll('.password-toggle').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var id = btn.getAttribute('data-for');
        var input = id ? document.getElementById(id) : null;
        if (!input) {
            return;
        }
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.setAttribute('aria-pressed', show ? 'true' : 'false');
        btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        var open = btn.querySelector('.pwd-eye-open');
        var shut = btn.querySelector('.pwd-eye-slash');
        if (open && shut) {
            if (show) {
                open.setAttribute('hidden', '');
                shut.removeAttribute('hidden');
            } else {
                shut.setAttribute('hidden', '');
                open.removeAttribute('hidden');
            }
        }
    });
});
</script>
</body>
</html>
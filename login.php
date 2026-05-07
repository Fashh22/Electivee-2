<?php
require_once 'config.php';

if (is_logged_in()) {
    redirect(get_user_role() === 'admin' ? 'admin_dashboard.php' : 'student_dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT id, name, password_hash, role FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($pass, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['role'] = $user['role'];
        
        redirect($user['role'] === 'admin' ? 'admin_dashboard.php' : 'student_dashboard.php');
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
                <div class="form-header">
                    <h2>Welcome Back</h2>
                    <p>Enter your institutional credentials to continue.</p>
                </div>

                <?php if ($error): ?>
                    <div class="msg danger mb-2" style="padding: 1rem; border-radius: 8px;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo e($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="input-group">
                        <label for="email">Institutional Email</label>
                        <div class="input-with-icon">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="email" name="email" placeholder="name@wmsu.edu.ph" required>
                        </div>
                    </div>

                    <div class="input-group">
                        <div class="label-row">
                            <label for="password">Password</label>
                            <a href="#" class="forgot-link">Forgot?</a>
                        </div>
                        <div class="input-with-icon">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="password" name="password" placeholder="••••••••" required>
                        </div>
                    </div>

                    <button type="submit" class="btn-login-landing">
                        Access Dashboard <i class="fas fa-arrow-right"></i>
                    </button>
                </form>

                <div class="form-footer">
                    <p>Don't have an account? <a href="register.php">Register as Student</a></p>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
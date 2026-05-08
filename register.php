<?php
require_once 'config.php';

if (is_logged_in()) {
    redirect(dashboard_for_role(get_user_role()));
}

$error = '';
$success = '';
$verificationLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';
    $role = 'student';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid email address.';
    } elseif (!preg_match('/@wmsu\.edu\.ph$/i', $email)) {
        $error = 'Only @wmsu.edu.ph email addresses are allowed.';
    } elseif (strlen($pass) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($name && $email && $pass) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email already exists.';
        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $isApproved = 1;
            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, password_hash, role, status, is_active, is_email_verified, is_approved)
                VALUES (?, ?, ?, ?, 'pending', 1, 0, ?)
            ");
            if ($stmt->execute([$name, $email, $hash, $role, $isApproved])) {
                $userId = (int)$pdo->lastInsertId();
                $token = issue_email_verification_token($pdo, $userId, 30);
                $verificationLink = APP_BASE_URL . '/verify-email.php?token=' . urlencode($token);
                dev_mail_log(
                    'Verify your account',
                    "To: {$email}\nLink: {$verificationLink}\nRole: {$role}"
                );
                $success = 'Registration successful. Verify your email first using the dev link below.';
            } else {
                $error = 'Something went wrong. Please try again.';
            }
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | QuizPro Oversight</title>
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
                    <span>CodeQuest ADMIN</span>
                </div>
                <h1>Start Your Journey Towards Academic Excellence.</h1>
                <p>Create your student account to access quizzes, track your scores, and monitor your progress in real-time.</p>
                
                <div class="hero-stats">
                    <div class="h-stat"><strong>1.2k+</strong><span>Students</span></div>
                    <div class="h-stat"><strong>4.8/5</strong><span>Rating</span></div>
                    <div class="h-stat"><strong>24/7</strong><span>Access</span></div>
                </div>
            </div>
        </div>

        <div class="login-form-side">
            <div class="form-wrapper">
                <div class="form-header">
                    <h2>Create Account</h2>
                    <p>Join the student portal and start learning today.</p>
                </div>

                <?php if ($error): ?>
                    <div class="msg danger mb-2" style="padding: 1rem; border-radius: 8px;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo e($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="msg success mb-2" style="padding: 1rem; border-radius: 8px; background: #dcfce7; color: #166534;">
                        <i class="fas fa-check-circle"></i> <?php echo e($success); ?> 
                        <a href="login.php" style="color: #166534; font-weight: bold; text-decoration: underline;">Login</a>
                    </div>
                    <?php if ($verificationLink): ?>
                        <div class="msg" style="padding: 1rem; margin-top: .75rem;">
                            <strong>Dev verification link:</strong><br>
                            <a href="<?php echo e($verificationLink); ?>"><?php echo e($verificationLink); ?></a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <form method="POST" class="login-auth-form">
                    <div class="input-group">
                        <label for="name">Full Name</label>
                        <div class="input-with-icon">
                            <i class="fas fa-user"></i>
                            <input type="text" id="name" name="name" placeholder="John Doe" required>
                        </div>
                    </div>

                    <div class="input-group">
                        <label for="email">Institutional Email</label>
                        <div class="input-with-icon">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="email" name="email" placeholder="name@student.wmsu.edu.ph" required>
                        </div>
                    </div>

                    <div class="input-group">
                        <label for="password">Password</label>
                        <div class="input-with-icon">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="password" name="password" placeholder="Min. 8 characters" required>
                        </div>
                    </div>
                    <p class="register-student-note">Faculty accounts are created by an administrator. This form is for students only.</p>

                    <button type="submit" class="btn-login-landing">
                        Create Account <i class="fas fa-user-plus"></i>
                    </button>
                </form>

                <div class="form-footer">
                    <p>Already have an account? <a href="login.php">Login here</a></p>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
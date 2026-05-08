<?php
require_once 'config.php';
require_admin();
$adminId = current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if (mb_strlen($phone) > 40) {
        set_flash('error', 'Phone number is too long (max 40 characters).');
    } elseif (mb_strlen($address) > 255) {
        set_flash('error', 'Address is too long (max 255 characters).');
    } elseif ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_flash('error', 'Please provide valid profile details.');
    } elseif (!preg_match('/@wmsu\.edu\.ph$/i', $email)) {
        set_flash('error', 'Profile email must use @wmsu.edu.ph.');
    } else {
        $passwordError = null;
        $newHash = null;
        if ($newPassword !== '' || $confirmPassword !== '') {
            if (strlen($newPassword) < 8) {
                $passwordError = 'New password must be at least 8 characters.';
            } elseif ($newPassword !== $confirmPassword) {
                $passwordError = 'New password and confirmation do not match.';
            } else {
                $pwStmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ? AND role = 'admin'");
                $pwStmt->execute([$adminId]);
                $pwRow = $pwStmt->fetch();
                if (!$pwRow || !password_verify($currentPassword, $pwRow['password_hash'])) {
                    $passwordError = 'Current password is incorrect.';
                } else {
                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                }
            }
        }

        if ($passwordError !== null) {
            set_flash('error', $passwordError);
        } else {
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id <> ?");
            $check->execute([$email, $adminId]);
            if ($check->fetch()) {
                set_flash('error', 'That email is already used by another account.');
            } elseif ($newHash !== null) {
                $pdo->prepare("
                    UPDATE users SET name = ?, email = ?, phone = ?, address = ?,
                    password_hash = ?, password_changed_at = NOW()
                    WHERE id = ? AND role = 'admin'
                ")->execute([
                    $name,
                    $email,
                    $phone !== '' ? $phone : null,
                    $address !== '' ? $address : null,
                    $newHash,
                    $adminId,
                ]);
                $_SESSION['name'] = $name;
                set_flash('success', 'Profile and password updated successfully.');
            } else {
                $pdo->prepare("
                    UPDATE users SET name = ?, email = ?, phone = ?, address = ?
                    WHERE id = ? AND role = 'admin'
                ")->execute([
                    $name,
                    $email,
                    $phone !== '' ? $phone : null,
                    $address !== '' ? $address : null,
                    $adminId,
                ]);
                $_SESSION['name'] = $name;
                set_flash('success', 'Profile updated successfully.');
            }
        }
    }
    redirect('admin_profile.php');
}

$flash = get_flash();
$adminStmt = $pdo->prepare("
    SELECT name, email, phone, address, created_at
    FROM users WHERE id = ? AND role = 'admin'
");
$adminStmt->execute([$adminId]);
$admin = $adminStmt->fetch();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Profile</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="teacher-page">
<div class="teacher-layout">
    <aside class="teacher-sidebar">
        <div class="teacher-sidebar-brand">Admin Panel</div>
        <a href="admin_dashboard.php#section-overview" class="teacher-nav-item">Overview</a>
        <a href="admin_dashboard.php#section-approvals" class="teacher-nav-item">Teacher Approvals</a>
        <a href="admin_dashboard.php#section-users" class="teacher-nav-item">All Users</a>
        <a href="admin_dashboard.php#section-logs" class="teacher-nav-item">Activity Logs</a>
        <a href="admin_profile.php" class="teacher-nav-item teacher-nav-profile active">Profile</a>
    </aside>

    <main class="teacher-main">
        <div class="header teacher-header">
            <div>
                <h2>Admin profile</h2>
                <p class="teacher-subtitle">Update your account details and password.</p>
            </div>
        </div>

        <div class="container wide teacher-wrap">
            <?php if ($flash): ?><div class="msg <?php echo e($flash['type']); ?>"><?php echo e($flash['message']); ?></div><?php endif; ?>

            <div class="card teacher-profile-page">
                <h3 class="teacher-card-title">Profile Settings</h3>
                <form method="post" class="teacher-form" id="adminProfileForm">
                    <input type="hidden" name="action" value="update_profile">
                    <label>Full Name</label>
                    <input type="text" name="name" value="<?php echo e($admin['name'] ?? ''); ?>" required autocomplete="name">
                    <label>Email</label>
                    <input type="email" name="email" value="<?php echo e($admin['email'] ?? ''); ?>" required autocomplete="email">
                    <label>Phone</label>
                    <input type="text" name="phone" value="<?php echo e($admin['phone'] ?? ''); ?>" maxlength="40" autocomplete="tel" placeholder="Optional">
                    <label>Address</label>
                    <textarea name="address" rows="2" maxlength="255" placeholder="Optional"><?php echo e($admin['address'] ?? ''); ?></textarea>
                    <p class="request-sub teacher-profile-meta">Member since: <?php echo e($admin['created_at'] ?? '-'); ?></p>
                    <p class="request-sub profile-password-hint">To change your password, enter your current password and a new one (min. 8 characters). Leave new fields blank to keep your password.</p>
                    <label>Current password</label>
                    <div class="password-field-wrap">
                        <input class="password-input" type="password" name="current_password" id="pwd_current" autocomplete="current-password" placeholder="Required only when setting a new password">
                        <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false" data-for="pwd_current">
                            <svg class="pwd-eye-open" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <svg class="pwd-eye-slash" hidden xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19 12 19c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 5c4.756 0 8.773 2.662 10.065 7A10.525 10.525 0 0115 17.81m-3.177-3.177L15 9M9.88 9.88l-3.29-3.29m0 0L9 6m-.59 6.59l3.29 3.29M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </button>
                    </div>
                    <label>New password</label>
                    <div class="password-field-wrap">
                        <input class="password-input" type="password" name="new_password" id="pwd_new" autocomplete="new-password" minlength="8" placeholder="Leave blank to keep current">
                        <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false" data-for="pwd_new">
                            <svg class="pwd-eye-open" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <svg class="pwd-eye-slash" hidden xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19 12 19c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 5c4.756 0 8.773 2.662 10.065 7A10.525 10.525 0 0115 17.81m-3.177-3.177L15 9M9.88 9.88l-3.29-3.29m0 0L9 6m-.59 6.59l3.29 3.29M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </button>
                    </div>
                    <label>Confirm new password</label>
                    <div class="password-field-wrap">
                        <input class="password-input" type="password" name="confirm_password" id="pwd_confirm" autocomplete="new-password" minlength="8" placeholder="Leave blank to keep current">
                        <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false" data-for="pwd_confirm">
                            <svg class="pwd-eye-open" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <svg class="pwd-eye-slash" hidden xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19 12 19c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 5c4.756 0 8.773 2.662 10.065 7A10.525 10.525 0 0115 17.81m-3.177-3.177L15 9M9.88 9.88l-3.29-3.29m0 0L9 6m-.59 6.59l3.29 3.29M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </button>
                    </div>
                </form>
                <div class="teacher-profile-actions">
                    <button class="button success" type="submit" form="adminProfileForm">Save Profile</button>
                    <a href="logout.php" class="button danger">Logout</a>
                </div>
            </div>
        </div>
    </main>
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

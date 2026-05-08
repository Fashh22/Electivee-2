<?php
require_once 'config.php';
require_admin();
$adminId = current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'approve_teacher') {
        $teacherId = (int)($_POST['teacher_id'] ?? 0);
        $pdo->prepare("
            UPDATE users
            SET is_approved = 1, approved_at = NOW(), approved_by = ?, status = 'active'
            WHERE id = ? AND role = 'teacher'
        ")->execute([$adminId, $teacherId]);
        $pdo->prepare("
            INSERT INTO activity_logs (actor_user_id, action, context_type, context_id, details)
            VALUES (?, 'approve_teacher', 'user', ?, 'Teacher approved by admin')
        ")->execute([$adminId, $teacherId]);
        set_flash('success', 'Teacher approved.');
    } elseif ($action === 'reject_teacher') {
        $teacherId = (int)($_POST['teacher_id'] ?? 0);
        $pdo->prepare("
            UPDATE users
            SET status = 'rejected', is_active = 0
            WHERE id = ? AND role = 'teacher'
        ")->execute([$teacherId]);
        $pdo->prepare("
            INSERT INTO activity_logs (actor_user_id, action, context_type, context_id, details)
            VALUES (?, 'reject_teacher', 'user', ?, 'Teacher rejected by admin')
        ")->execute([$adminId, $teacherId]);
        set_flash('success', 'Teacher rejected.');
    } elseif ($action === 'toggle_user_status') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $newActive = (int)($_POST['is_active'] ?? 0);
        $pdo->prepare("
            UPDATE users
            SET is_active = ?, status = CASE WHEN ? = 1 THEN status ELSE 'disabled' END
            WHERE id = ? AND role <> 'admin'
        ")->execute([$newActive, $newActive, $userId]);
        set_flash('success', 'User status updated.');
    } elseif ($action === 'create_teacher_account') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if ($name === '' || $email === '' || $password === '') {
            set_flash('error', 'Please fill in all teacher account fields.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash('error', 'Please provide a valid email address.');
        } elseif (!preg_match('/@wmsu\.edu\.ph$/i', $email)) {
            set_flash('error', 'Teacher email must use @wmsu.edu.ph.');
        } elseif (strlen($password) < 8) {
            set_flash('error', 'Password must be at least 8 characters.');
        } else {
            $exists = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $exists->execute([$email]);
            if ($exists->fetch()) {
                set_flash('error', 'Email already exists.');
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("
                    INSERT INTO users (
                        name, email, password_hash, role, status, is_active,
                        is_email_verified, email_verified_at, is_approved, approved_at,
                        approved_by, password_changed_at
                    )
                    VALUES (?, ?, ?, 'teacher', 'active', 1, 1, NOW(), 1, NOW(), ?, NOW())
                ")->execute([$name, $email, $hash, $adminId]);
                $newTeacherId = (int)$pdo->lastInsertId();
                $pdo->prepare("
                    INSERT INTO activity_logs (actor_user_id, action, context_type, context_id, details)
                    VALUES (?, 'create_teacher', 'user', ?, ?)
                ")->execute([$adminId, $newTeacherId, 'Teacher account created by admin']);
                set_flash('success', 'Teacher account created and activated.');
            }
        }
    }
    redirect('admin_dashboard.php');
}

$flash = get_flash();
$stats = [
    'users' => (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'teachers' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn(),
    'students' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn(),
    'subjects' => (int)$pdo->query("SELECT COUNT(*) FROM subjects")->fetchColumn(),
    'quizzes' => (int)$pdo->query("SELECT COUNT(*) FROM quizzes")->fetchColumn(),
    'admins' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn(),
    'active_users' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn(),
    'approved_teachers' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher' AND is_approved = 1")->fetchColumn(),
    'quiz_attempts' => (int)$pdo->query("SELECT COUNT(*) FROM quiz_attempts")->fetchColumn(),
    'quiz_attempts_finished' => (int)$pdo->query("SELECT COUNT(*) FROM quiz_attempts WHERE is_finished = 1")->fetchColumn(),
    'pending_join_requests' => (int)$pdo->query("SELECT COUNT(*) FROM subject_join_requests WHERE status = 'pending'")->fetchColumn(),
];

$pendingTeachers = $pdo->query("
    SELECT id, name, email, created_at
    FROM users
    WHERE role = 'teacher' AND is_email_verified = 1 AND is_approved = 0 AND is_active = 1
    ORDER BY created_at ASC
")->fetchAll();

$allUsers = $pdo->query("
    SELECT id, name, email, phone, role, is_active, is_email_verified, is_approved, status, created_at, email_verified_at
    FROM users
    ORDER BY created_at DESC
    LIMIT 100
")->fetchAll();

$userListCounts = ['admin' => 0, 'teacher' => 0, 'student' => 0, 'active' => 0, 'inactive' => 0];
foreach ($allUsers as $u) {
    $r = $u['role'] ?? '';
    if (isset($userListCounts[$r])) {
        $userListCounts[$r]++;
    }
    if ((int)($u['is_active'] ?? 0)) {
        $userListCounts['active']++;
    } else {
        $userListCounts['inactive']++;
    }
}

$logFromInput = trim((string)($_GET['log_from'] ?? ''));
$logToInput = trim((string)($_GET['log_to'] ?? ''));
$logFrom = null;
$logTo = null;
if ($logFromInput !== '') {
    $dt = DateTime::createFromFormat('Y-m-d', $logFromInput);
    if ($dt && $dt->format('Y-m-d') === $logFromInput) {
        $logFrom = $logFromInput;
    }
}
if ($logToInput !== '') {
    $dt = DateTime::createFromFormat('Y-m-d', $logToInput);
    if ($dt && $dt->format('Y-m-d') === $logToInput) {
        $logTo = $logToInput;
    }
}
if ($logFrom && $logTo && strcmp($logFrom, $logTo) > 0) {
    $tmp = $logFrom;
    $logFrom = $logTo;
    $logTo = $tmp;
}
$logFilterActive = ($logFrom !== null || $logTo !== null);

$logSql = "
    SELECT al.action, al.details, al.created_at, u.name AS actor_name
    FROM activity_logs al
    LEFT JOIN users u ON u.id = al.actor_user_id
";
$logParams = [];
$logWhere = [];
if ($logFrom !== null) {
    $logWhere[] = 'al.created_at >= ?';
    $logParams[] = $logFrom . ' 00:00:00';
}
if ($logTo !== null) {
    $logWhere[] = 'al.created_at <= ?';
    $logParams[] = $logTo . ' 23:59:59';
}
if ($logWhere) {
    $logSql .= ' WHERE ' . implode(' AND ', $logWhere);
}
$logSql .= ' ORDER BY al.created_at DESC LIMIT ' . ($logFilterActive ? 500 : 100);
$logStmt = $pdo->prepare($logSql);
$logStmt->execute($logParams);
$recentLogs = $logStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="teacher-page">
<div class="teacher-layout">
    <aside class="teacher-sidebar">
        <div class="teacher-sidebar-brand">Admin Panel</div>
        <a href="admin_dashboard.php#section-overview" class="teacher-nav-item active" data-target="section-overview">Overview</a>
        <a href="admin_dashboard.php#section-approvals" class="teacher-nav-item" data-target="section-approvals">Teacher Approvals</a>
        <a href="admin_dashboard.php#section-users" class="teacher-nav-item" data-target="section-users">All Users</a>
        <a href="admin_dashboard.php#section-logs" class="teacher-nav-item" data-target="section-logs">Activity Logs</a>
        <a href="admin_profile.php" class="teacher-nav-item teacher-nav-profile">Profile</a>
    </aside>

    <main class="teacher-main">
        <div class="header teacher-header">
            <div>
                <h2>Admin Workspace</h2>
                <p class="teacher-subtitle">Manage teacher approvals, users, and platform activity.</p>
            </div>
        </div>

        <div class="container wide teacher-wrap">
            <?php if ($flash): ?><div class="msg <?php echo e($flash['type']); ?>"><?php echo e($flash['message']); ?></div><?php endif; ?>

            <section id="section-overview" class="teacher-section active">
                <div class="admin-overview-head">
                    <h3 class="teacher-card-title admin-overview-title">System overview</h3>
                    <p class="admin-overview-lead">Live counts across accounts, teaching staff, content, and quiz activity. Jump to a workspace below when you need to act on something.</p>
                </div>

                <div class="admin-overview-stat-grid">
                    <a href="#section-users" class="admin-stat-card admin-stat-card--link" data-admin-section="section-users">
                        <div class="admin-stat-card-inner">
                            <div class="admin-stat-icon admin-stat-icon--users" aria-hidden="true">U</div>
                            <div class="admin-stat-body">
                                <span class="admin-stat-label">Total users</span>
                                <strong class="admin-stat-value"><?php echo (int)$stats['users']; ?></strong>
                                <span class="admin-stat-hint"><?php echo (int)$stats['active_users']; ?> active · <?php echo (int)$stats['admins']; ?> admin<?php echo (int)$stats['admins'] === 1 ? '' : 's'; ?></span>
                            </div>
                        </div>
                    </a>
                    <a href="#section-approvals" class="admin-stat-card admin-stat-card--link" data-admin-section="section-approvals">
                        <div class="admin-stat-card-inner">
                            <div class="admin-stat-icon admin-stat-icon--teachers" aria-hidden="true">T</div>
                            <div class="admin-stat-body">
                                <span class="admin-stat-label">Teachers</span>
                                <strong class="admin-stat-value"><?php echo (int)$stats['teachers']; ?></strong>
                                <span class="admin-stat-hint"><?php echo (int)$stats['approved_teachers']; ?> approved<?php if (count($pendingTeachers)): ?> · <span class="admin-stat-em"><?php echo count($pendingTeachers); ?> need review</span><?php else: ?> · queue clear<?php endif; ?></span>
                            </div>
                        </div>
                    </a>
                    <div class="admin-stat-card">
                        <div class="admin-stat-card-inner">
                            <div class="admin-stat-icon admin-stat-icon--students" aria-hidden="true">S</div>
                            <div class="admin-stat-body">
                                <span class="admin-stat-label">Students</span>
                                <strong class="admin-stat-value"><?php echo (int)$stats['students']; ?></strong>
                                <span class="admin-stat-hint"><?php echo (int)$stats['pending_join_requests']; ?> pending enrollment request<?php echo (int)$stats['pending_join_requests'] === 1 ? '' : 's'; ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="admin-stat-card">
                        <div class="admin-stat-card-inner">
                            <div class="admin-stat-icon admin-stat-icon--subjects" aria-hidden="true">C</div>
                            <div class="admin-stat-body">
                                <span class="admin-stat-label">Subjects</span>
                                <strong class="admin-stat-value"><?php echo (int)$stats['subjects']; ?></strong>
                                <span class="admin-stat-hint">Courses on the platform</span>
                            </div>
                        </div>
                    </div>
                    <div class="admin-stat-card">
                        <div class="admin-stat-card-inner">
                            <div class="admin-stat-icon admin-stat-icon--quizzes" aria-hidden="true">Q</div>
                            <div class="admin-stat-body">
                                <span class="admin-stat-label">Quizzes</span>
                                <strong class="admin-stat-value"><?php echo (int)$stats['quizzes']; ?></strong>
                                <span class="admin-stat-hint">Published assessments</span>
                            </div>
                        </div>
                    </div>
                    <div class="admin-stat-card">
                        <div class="admin-stat-card-inner">
                            <div class="admin-stat-icon admin-stat-icon--attempts" aria-hidden="true">A</div>
                            <div class="admin-stat-body">
                                <span class="admin-stat-label">Quiz attempts</span>
                                <strong class="admin-stat-value"><?php echo (int)$stats['quiz_attempts_finished']; ?></strong>
                                <span class="admin-stat-hint"><?php echo (int)$stats['quiz_attempts']; ?> total started · <?php echo (int)$stats['quiz_attempts'] > 0 ? (int)round(100 * (int)$stats['quiz_attempts_finished'] / max(1, (int)$stats['quiz_attempts'])) : 0; ?>% submitted</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="admin-overview-panels">
                    <?php if (count($pendingTeachers)): ?>
                    <div class="card admin-overview-priority">
                        <div class="admin-overview-priority-copy">
                            <span class="admin-overview-priority-kicker">Action needed</span>
                            <strong class="admin-overview-priority-title"><?php echo count($pendingTeachers); ?> teacher account<?php echo count($pendingTeachers) === 1 ? '' : 's'; ?> awaiting approval</strong>
                            <p class="admin-overview-priority-text">Verified emails are in the queue. Approve or reject from the teacher approvals workspace.</p>
                        </div>
                        <a href="#section-approvals" class="button success small admin-overview-priority-cta" data-admin-section="section-approvals">Open queue</a>
                    </div>
                    <?php endif; ?>

                    <div class="card admin-overview-quick-card">
                        <h4 class="admin-overview-quick-heading">Workspace shortcuts</h4>
                        <ul class="admin-overview-quick-list">
                            <li><a href="#section-approvals" data-admin-section="section-approvals">Teacher approvals<?php if (count($pendingTeachers)): ?> <span class="admin-quick-badge"><?php echo count($pendingTeachers); ?></span><?php endif; ?></a></li>
                            <li><a href="#section-users" data-admin-section="section-users">All users &amp; access</a></li>
                            <li><a href="#section-logs" data-admin-section="section-logs">Activity logs</a></li>
                        </ul>
                    </div>
                </div>
            </section>

            <section id="section-approvals" class="teacher-section">
                <div class="card">
                    <h3 class="teacher-card-title">Pending Teacher Approvals</h3>
                    <?php if (!$pendingTeachers): ?>
                        <p class="teacher-empty">No pending teacher approvals.</p>
                    <?php else: ?>
                        <table class="minimal-table">
                            <thead><tr><th>Name</th><th>Email</th><th>Requested</th><th>Actions</th></tr></thead>
                            <tbody>
                            <?php foreach ($pendingTeachers as $teacher): ?>
                                <tr>
                                    <td><?php echo e($teacher['name']); ?></td>
                                    <td><?php echo e($teacher['email']); ?></td>
                                    <td><?php echo e($teacher['created_at']); ?></td>
                                    <td style="display:flex;gap:.5rem;">
                                        <form method="post">
                                            <input type="hidden" name="action" value="approve_teacher">
                                            <input type="hidden" name="teacher_id" value="<?php echo (int)$teacher['id']; ?>">
                                            <button class="button success small" type="submit">Approve</button>
                                        </form>
                                        <form method="post">
                                            <input type="hidden" name="action" value="reject_teacher">
                                            <input type="hidden" name="teacher_id" value="<?php echo (int)$teacher['id']; ?>">
                                            <button class="button danger small" type="submit">Reject</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </section>

            <section id="section-users" class="teacher-section">
                <div class="admin-users-toolbar">
                    <div>
                        <h3 class="teacher-card-title admin-users-title">User directory</h3>
                        <p class="admin-users-lead">Showing the <?php echo count($allUsers); ?> most recently registered accounts. Use the table for verification, approval state, and access control.</p>
                    </div>
                    <button type="button" class="button success small" id="openAddTeacherModal">Add Teacher</button>
                </div>

                <div class="admin-users-kpi-grid">
                    <div class="admin-kpi-card">
                        <span class="admin-kpi-label">In this list</span>
                        <strong class="admin-kpi-value"><?php echo count($allUsers); ?></strong>
                        <span class="admin-kpi-hint">of <?php echo (int)$stats['users']; ?> total in system</span>
                    </div>
                    <div class="admin-kpi-card">
                        <span class="admin-kpi-label">Teachers</span>
                        <strong class="admin-kpi-value"><?php echo (int)$userListCounts['teacher']; ?></strong>
                        <span class="admin-kpi-hint"><?php echo (int)$stats['teachers']; ?> total teachers</span>
                    </div>
                    <div class="admin-kpi-card">
                        <span class="admin-kpi-label">Students</span>
                        <strong class="admin-kpi-value"><?php echo (int)$userListCounts['student']; ?></strong>
                        <span class="admin-kpi-hint"><?php echo (int)$stats['students']; ?> total students</span>
                    </div>
                    <div class="admin-kpi-card">
                        <span class="admin-kpi-label">Active / inactive</span>
                        <strong class="admin-kpi-value"><?php echo (int)$userListCounts['active']; ?> / <?php echo (int)$userListCounts['inactive']; ?></strong>
                        <span class="admin-kpi-hint">in current page</span>
                    </div>
                </div>

                <div class="card admin-users-table-card">
                    <div class="table-scroll">
                        <table class="minimal-table admin-users-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Contact</th>
                                    <th>Role</th>
                                    <th>Verification</th>
                                    <th>Teacher OK</th>
                                    <th>Account</th>
                                    <th>Joined</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($allUsers as $user): ?>
                                <?php
                                $role = $user['role'];
                                $status = $user['status'];
                                ?>
                                <tr>
                                    <td class="admin-users-id"><?php echo (int)$user['id']; ?></td>
                                    <td>
                                        <span class="admin-users-name"><?php echo e($user['name']); ?></span>
                                    </td>
                                    <td class="admin-users-contact">
                                        <span class="admin-contact-email"><?php echo e($user['email']); ?></span>
                                        <?php if (!empty($user['phone'])): ?>
                                            <span class="admin-contact-phone"><?php echo e($user['phone']); ?></span>
                                        <?php else: ?>
                                            <span class="admin-contact-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="admin-badge admin-role admin-role-<?php echo e($role); ?>"><?php echo e(ucfirst($role)); ?></span></td>
                                    <td>
                                        <?php if ((int)$user['is_email_verified']): ?>
                                            <span class="admin-badge admin-badge-ok">Verified</span>
                                            <?php if (!empty($user['email_verified_at'])): ?>
                                                <span class="admin-meta-line"><?php echo e($user['email_verified_at']); ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="admin-badge admin-badge-warn">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($role === 'teacher'): ?>
                                            <?php if ((int)$user['is_approved']): ?>
                                                <span class="admin-badge admin-badge-ok">Yes</span>
                                            <?php else: ?>
                                                <span class="admin-badge admin-badge-neutral">No</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="admin-meta-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="admin-users-account">
                                        <span class="admin-badge <?php echo (int)$user['is_active'] ? 'admin-badge-ok' : 'admin-badge-danger'; ?>">
                                            <?php echo (int)$user['is_active'] ? 'Active' : 'Disabled'; ?>
                                        </span>
                                        <span class="admin-badge admin-status admin-status-<?php echo e(str_replace('_', '-', $status)); ?>"><?php echo e($status); ?></span>
                                    </td>
                                    <td class="admin-users-date"><?php echo e($user['created_at']); ?></td>
                                    <td class="admin-users-actions">
                                        <?php if ($user['role'] !== 'admin'): ?>
                                            <form method="post">
                                                <input type="hidden" name="action" value="toggle_user_status">
                                                <input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">
                                                <input type="hidden" name="is_active" value="<?php echo (int)$user['is_active'] ? 0 : 1; ?>">
                                                <button class="button small <?php echo (int)$user['is_active'] ? 'warning' : 'success'; ?>" type="submit"><?php echo (int)$user['is_active'] ? 'Disable' : 'Enable'; ?></button>
                                            </form>
                                        <?php else: ?>
                                            <span class="admin-meta-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section id="section-logs" class="teacher-section">
                <div class="admin-logs-toolbar card">
                    <div class="admin-logs-toolbar-text">
                        <h3 class="teacher-card-title admin-logs-title">Activity logs</h3>
                        <p class="admin-logs-lead">Filter by event date (server time). With no dates, the latest 100 entries are listed. When you set a range, up to 500 matching rows are shown.</p>
                    </div>
                    <form method="get" class="admin-logs-filter teacher-form" action="admin_dashboard.php">
                        <div class="admin-logs-filter-fields">
                            <div>
                                <label for="log_from">From</label>
                                <input type="date" name="log_from" id="log_from" value="<?php echo e($logFrom ?? ''); ?>">
                            </div>
                            <div>
                                <label for="log_to">To</label>
                                <input type="date" name="log_to" id="log_to" value="<?php echo e($logTo ?? ''); ?>">
                            </div>
                        </div>
                        <div class="admin-logs-filter-actions">
                            <button type="submit" class="button small">Apply filter</button>
                            <a href="admin_dashboard.php#section-logs" class="button secondary small">Clear</a>
                        </div>
                    </form>
                </div>
                <div class="card">
                    <p class="admin-logs-meta"><?php echo count($recentLogs); ?> entr<?php echo count($recentLogs) === 1 ? 'y' : 'ies'; ?><?php if ($logFilterActive): ?> in range<?php if ($logFrom && $logTo): ?> <?php echo e($logFrom); ?> — <?php echo e($logTo); ?><?php elseif ($logFrom): ?> from <?php echo e($logFrom); ?><?php elseif ($logTo): ?> through <?php echo e($logTo); ?><?php endif; ?><?php endif; ?>.</p>
                    <?php if (!$recentLogs): ?>
                        <p class="teacher-empty">No activity logs match this filter.</p>
                    <?php else: ?>
                    <table class="minimal-table">
                        <thead><tr><th>Time</th><th>Actor</th><th>Action</th><th>Details</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentLogs as $log): ?>
                            <tr>
                                <td><?php echo e($log['created_at']); ?></td>
                                <td><?php echo e($log['actor_name'] ?? 'System'); ?></td>
                                <td><?php echo e($log['action']); ?></td>
                                <td><?php echo e($log['details']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </main>
</div>

<div class="modal-overlay" id="addTeacherModal">
    <div class="modal-card admin-add-teacher-modal">
        <div class="modal-header">
            <h3>Create Teacher Account</h3>
        </div>
        <div class="modal-body">
            <form method="post" class="teacher-form" id="addTeacherForm">
                <input type="hidden" name="action" value="create_teacher_account">
                <label>Full Name</label>
                <input type="text" name="name" required>
                <label>WMSU Email</label>
                <input type="email" name="email" placeholder="teacher@wmsu.edu.ph" required>
                <label>Temporary Password</label>
                <input type="text" name="password" minlength="8" required>
            </form>
        </div>
        <div class="modal-actions">
            <button type="button" class="button secondary small" id="closeAddTeacherModal">Cancel</button>
            <button class="button success small" type="submit" form="addTeacherForm">Create Teacher</button>
        </div>
    </div>
</div>

<script>
const navItems = document.querySelectorAll('.teacher-nav-item[data-target]');
const sections = document.querySelectorAll('.teacher-section');

function activateAdminSection(sectionId) {
    if (!sectionId || !document.getElementById(sectionId)) {
        return;
    }
    navItems.forEach(i => i.classList.remove('active'));
    sections.forEach(s => s.classList.remove('active'));
    const navFor = document.querySelector('.teacher-nav-item[data-target="' + sectionId + '"]');
    if (navFor) {
        navFor.classList.add('active');
    }
    document.getElementById(sectionId).classList.add('active');
}

navItems.forEach(item => {
    item.addEventListener('click', function(e) {
        const targetId = item.dataset.target;
        if (!targetId) {
            return;
        }
        e.preventDefault();
        activateAdminSection(targetId);
        history.replaceState(null, '', '#' + targetId);
    });
});

document.querySelectorAll('[data-admin-section]').forEach(function(el) {
    el.addEventListener('click', function(e) {
        const targetId = el.getAttribute('data-admin-section');
        if (!targetId) {
            return;
        }
        e.preventDefault();
        activateAdminSection(targetId);
        history.replaceState(null, '', '#' + targetId);
    });
});

window.addEventListener('DOMContentLoaded', function() {
    const params = new URLSearchParams(window.location.search);
    if (params.get('log_from') || params.get('log_to')) {
        activateAdminSection('section-logs');
        history.replaceState(null, '', window.location.pathname + window.location.search + '#section-logs');
        return;
    }
    const hash = (location.hash || '').replace(/^#/, '');
    if (hash && document.getElementById(hash)) {
        activateAdminSection(hash);
    }
});

const addTeacherModal = document.getElementById('addTeacherModal');
const openAddTeacherModal = document.getElementById('openAddTeacherModal');
const closeAddTeacherModal = document.getElementById('closeAddTeacherModal');
if (addTeacherModal && openAddTeacherModal && closeAddTeacherModal) {
    openAddTeacherModal.addEventListener('click', function() {
        addTeacherModal.classList.add('show');
    });
    closeAddTeacherModal.addEventListener('click', function() {
        addTeacherModal.classList.remove('show');
    });
    addTeacherModal.addEventListener('click', function(e) {
        if (e.target === addTeacherModal) {
            addTeacherModal.classList.remove('show');
        }
    });
}
</script>
</body>
</html>
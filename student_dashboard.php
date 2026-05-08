<?php
require_once 'config.php';
require_student();
$studentId = current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'join_by_code') {
        $code = trim(strtoupper($_POST['subject_code'] ?? ''));
        $subject = $pdo->prepare("SELECT id FROM subjects WHERE subject_code = ? AND is_active = 1");
        $subject->execute([$code]);
        $row = $subject->fetch();
        if ($row) {
            $subjectId = (int)$row['id'];
            $pdo->prepare("
                INSERT INTO subject_join_requests (subject_id, student_id, status)
                VALUES (?, ?, 'pending')
                ON DUPLICATE KEY UPDATE status = 'pending', requested_at = NOW()
            ")->execute([$subjectId, $studentId]);
            set_flash('success', 'Join request sent to teacher.');
        } else {
            set_flash('error', 'Invalid subject code.');
        }
    } elseif ($action === 'request_subject') {
        $subjectId = (int)($_POST['subject_id'] ?? 0);
        $pdo->prepare("
            INSERT INTO subject_join_requests (subject_id, student_id, status)
            VALUES (?, ?, 'pending')
            ON DUPLICATE KEY UPDATE status = 'pending', requested_at = NOW()
        ")->execute([$subjectId, $studentId]);
        set_flash('success', 'Request sent.');
    }
    redirect('student_dashboard.php');
}

$flash = get_flash();
$subjects = $pdo->prepare("
    SELECT s.id, s.name, s.description, s.subject_code, u.name AS teacher_name
    FROM subject_enrollments se
    INNER JOIN subjects s ON s.id = se.subject_id
    INNER JOIN users u ON u.id = s.teacher_id
    WHERE se.student_id = ? AND se.status = 'approved'
    ORDER BY s.name
");
$subjects->execute([$studentId]);
$enrolledSubjects = $subjects->fetchAll();

$availableSubjects = $pdo->prepare("
    SELECT s.id, s.name, s.description, s.subject_code, u.name AS teacher_name
    FROM subjects s
    INNER JOIN users u ON u.id = s.teacher_id
    WHERE s.is_active = 1
      AND s.id NOT IN (SELECT subject_id FROM subject_enrollments WHERE student_id = ? AND status = 'approved')
    ORDER BY s.name
");
$availableSubjects->execute([$studentId]);
$discoverSubjects = $availableSubjects->fetchAll();

$quizzes = $pdo->prepare("
    SELECT q.id, q.title, q.description, q.time_limit, q.attempt_limit, s.name AS subject_name
    FROM quizzes q
    INNER JOIN subject_enrollments se ON se.subject_id = q.subject_id AND se.student_id = ? AND se.status = 'approved'
    INNER JOIN subjects s ON s.id = q.subject_id
    WHERE q.is_active = 1
    ORDER BY q.created_at DESC
");
$quizzes->execute([$studentId]);
$quizRows = $quizzes->fetchAll();

$attempts = $pdo->prepare("
    SELECT qa.score, qa.total_points, qa.submitted_at, q.title AS quiz_title
    FROM quiz_attempts qa
    INNER JOIN quizzes q ON q.id = qa.quiz_id
    WHERE qa.student_id = ? AND qa.is_finished = 1
    ORDER BY qa.submitted_at DESC
");
$attempts->execute([$studentId]);
$attemptRows = $attempts->fetchAll();

$stmtPending = $pdo->prepare("SELECT COUNT(*) FROM subject_join_requests WHERE student_id = ? AND status = 'pending'");
$stmtPending->execute([$studentId]);
$studentPendingRequests = (int)$stmtPending->fetchColumn();

$studentAvgPct = null;
if ($attemptRows) {
    $acc = 0.0;
    $n = 0;
    foreach ($attemptRows as $a) {
        $tp = (int)$a['total_points'];
        if ($tp > 0) {
            $acc += ((float)$a['score'] / $tp) * 100;
            $n++;
        }
    }
    if ($n > 0) {
        $studentAvgPct = (int)round($acc / $n);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="teacher-page student-dashboard">
<div class="teacher-layout student-layout">
    <aside class="teacher-sidebar student-sidebar">
        <div class="teacher-sidebar-brand">Student Panel</div>
        <div class="student-nav-rail" role="navigation" aria-label="Student workspace">
            <a href="student_dashboard.php#section-overview" class="teacher-nav-item active" data-target="section-overview">Overview</a>
            <a href="student_dashboard.php#section-join" class="teacher-nav-item" data-target="section-join">Join by Code</a>
            <a href="student_dashboard.php#section-discover" class="teacher-nav-item" data-target="section-discover">Discover Subjects</a>
            <a href="student_dashboard.php#section-subjects" class="teacher-nav-item" data-target="section-subjects">My Subjects</a>
            <a href="student_dashboard.php#section-quizzes" class="teacher-nav-item" data-target="section-quizzes">Available Quizzes</a>
            <a href="student_dashboard.php#section-results" class="teacher-nav-item" data-target="section-results">My Results</a>
            <a href="student_profile.php" class="teacher-nav-item teacher-nav-profile">Profile</a>
        </div>
    </aside>

    <main class="teacher-main">
        <div class="header teacher-header">
            <div>
                <h2>Student Workspace</h2>
                <p class="teacher-subtitle">Join subjects, take quizzes, and monitor your results.</p>
            </div>
        </div>

        <div class="container wide teacher-wrap">
            <?php if ($flash): ?><div class="msg <?php echo e($flash['type']); ?>"><?php echo e($flash['message']); ?></div><?php endif; ?>

            <section id="section-overview" class="teacher-section active">
                <div class="admin-overview-head">
                    <h3 class="teacher-card-title admin-overview-title">Your overview</h3>
                    <p class="admin-overview-lead">See your enrollment, open subjects, quizzes, and results at a glance. Jump to any workspace with the cards or shortcuts below.</p>
                </div>

                <div class="admin-overview-stat-grid">
                    <a href="#section-subjects" class="admin-stat-card admin-stat-card--link" data-student-section="section-subjects">
                        <div class="admin-stat-card-inner">
                            <div class="admin-stat-icon admin-stat-icon--subjects" aria-hidden="true">C</div>
                            <div class="admin-stat-body">
                                <span class="admin-stat-label">Enrolled subjects</span>
                                <strong class="admin-stat-value"><?php echo count($enrolledSubjects); ?></strong>
                                <span class="admin-stat-hint">Courses you are approved for</span>
                            </div>
                        </div>
                    </a>
                    <a href="#section-discover" class="admin-stat-card admin-stat-card--link" data-student-section="section-discover">
                        <div class="admin-stat-card-inner">
                            <div class="admin-stat-icon admin-stat-icon--students" aria-hidden="true">D</div>
                            <div class="admin-stat-body">
                                <span class="admin-stat-label">Open to join</span>
                                <strong class="admin-stat-value"><?php echo count($discoverSubjects); ?></strong>
                                <span class="admin-stat-hint">Subjects you can request</span>
                            </div>
                        </div>
                    </a>
                    <a href="#section-quizzes" class="admin-stat-card admin-stat-card--link" data-student-section="section-quizzes">
                        <div class="admin-stat-card-inner">
                            <div class="admin-stat-icon admin-stat-icon--quizzes" aria-hidden="true">Q</div>
                            <div class="admin-stat-body">
                                <span class="admin-stat-label">Available quizzes</span>
                                <strong class="admin-stat-value"><?php echo count($quizRows); ?></strong>
                                <span class="admin-stat-hint">From your enrolled subjects</span>
                            </div>
                        </div>
                    </a>
                    <a href="#section-results" class="admin-stat-card admin-stat-card--link" data-student-section="section-results">
                        <div class="admin-stat-card-inner">
                            <div class="admin-stat-icon admin-stat-icon--attempts" aria-hidden="true">A</div>
                            <div class="admin-stat-body">
                                <span class="admin-stat-label">Completed attempts</span>
                                <strong class="admin-stat-value"><?php echo count($attemptRows); ?></strong>
                                <span class="admin-stat-hint">Finished &amp; graded runs</span>
                            </div>
                        </div>
                    </a>
                    <div class="admin-stat-card">
                        <div class="admin-stat-card-inner">
                            <div class="admin-stat-icon admin-stat-icon--teachers" aria-hidden="true">P</div>
                            <div class="admin-stat-body">
                                <span class="admin-stat-label">Pending requests</span>
                                <strong class="admin-stat-value"><?php echo $studentPendingRequests; ?></strong>
                                <span class="admin-stat-hint">Awaiting teacher review</span>
                            </div>
                        </div>
                    </div>
                    <div class="admin-stat-card">
                        <div class="admin-stat-card-inner">
                            <div class="admin-stat-icon admin-stat-icon--users" aria-hidden="true">%</div>
                            <div class="admin-stat-body">
                                <span class="admin-stat-label">Average score</span>
                                <strong class="admin-stat-value"><?php echo $studentAvgPct !== null ? $studentAvgPct . '%' : '—'; ?></strong>
                                <span class="admin-stat-hint"><?php echo $studentAvgPct !== null ? 'Across completed quizzes' : 'Complete a quiz to see this'; ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="admin-overview-panels">
                    <?php if ($studentPendingRequests > 0): ?>
                    <div class="card admin-overview-priority">
                        <div class="admin-overview-priority-copy">
                            <span class="admin-overview-priority-kicker">Waiting on teachers</span>
                            <strong class="admin-overview-priority-title"><?php echo $studentPendingRequests; ?> join request<?php echo $studentPendingRequests === 1 ? '' : 's'; ?> pending</strong>
                            <p class="admin-overview-priority-text">Teachers approve access per subject. You can browse discoverable subjects or enter a code while you wait.</p>
                        </div>
                        <a href="#section-discover" class="button success small admin-overview-priority-cta" data-student-section="section-discover">View subjects</a>
                    </div>
                    <?php endif; ?>

                    <div class="card admin-overview-quick-card">
                        <h4 class="admin-overview-quick-heading">Shortcuts</h4>
                        <ul class="admin-overview-quick-list">
                            <li><a href="#section-join" data-student-section="section-join">Join by code</a></li>
                            <li><a href="#section-discover" data-student-section="section-discover">Discover subjects<?php if ($studentPendingRequests > 0): ?> <span class="admin-quick-badge"><?php echo $studentPendingRequests; ?></span><?php endif; ?></a></li>
                            <li><a href="#section-subjects" data-student-section="section-subjects">My subjects</a></li>
                            <li><a href="#section-quizzes" data-student-section="section-quizzes">Take a quiz</a></li>
                            <li><a href="#section-results" data-student-section="section-results">My results</a></li>
                        </ul>
                    </div>
                </div>
            </section>

            <section id="section-join" class="teacher-section">
                <div class="card">
                    <h3 class="teacher-card-title">Join Subject by Code</h3>
                    <form method="post" class="teacher-form">
                        <input type="hidden" name="action" value="join_by_code">
                        <label>Subject Code</label>
                        <input type="text" name="subject_code" required>
                        <button class="button success" type="submit">Send Join Request</button>
                    </form>
                </div>
            </section>

            <section id="section-discover" class="teacher-section">
                <div class="card">
                    <h3 class="teacher-card-title">Discover Subjects</h3>
                    <?php if (!$discoverSubjects): ?><p class="teacher-empty">No available subjects at the moment.</p><?php endif; ?>
                    <?php foreach ($discoverSubjects as $subject): ?>
                        <div class="request-item">
                            <div>
                                <p class="request-main"><strong><?php echo e($subject['name']); ?></strong> (<?php echo e($subject['subject_code']); ?>)</p>
                                <p class="request-sub">Teacher: <?php echo e($subject['teacher_name']); ?></p>
                            </div>
                            <form method="post">
                                <input type="hidden" name="action" value="request_subject">
                                <input type="hidden" name="subject_id" value="<?php echo (int)$subject['id']; ?>">
                                <button class="button small" type="submit">Request Access</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section id="section-subjects" class="teacher-section">
                <div class="card">
                    <h3 class="teacher-card-title">My Enrolled Subjects</h3>
                    <?php if (!$enrolledSubjects): ?><p class="teacher-empty">No enrolled subjects yet.</p><?php endif; ?>
                    <?php foreach ($enrolledSubjects as $subject): ?>
                        <div class="request-item">
                            <div>
                                <p class="request-main"><strong><?php echo e($subject['name']); ?></strong></p>
                                <p class="request-sub">Teacher: <?php echo e($subject['teacher_name']); ?> · Code: <?php echo e($subject['subject_code']); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section id="section-quizzes" class="teacher-section">
                <div class="card">
                    <h3 class="teacher-card-title">Available Quizzes</h3>
                    <?php if (!$quizRows): ?><p class="teacher-empty">No quizzes available yet.</p><?php endif; ?>
                    <?php foreach ($quizRows as $quiz): ?>
                        <div class="quiz-item">
                            <p class="quiz-title"><?php echo e($quiz['title']); ?></p>
                            <p class="quiz-meta"><?php echo e($quiz['subject_name']); ?></p>
                            <p class="request-sub"><?php echo e($quiz['description'] ?: 'No description provided.'); ?></p>
                            <p class="request-sub">Time limit: <?php echo (int)$quiz['time_limit']; ?> mins · Attempt limit: <?php echo (int)$quiz['attempt_limit']; ?></p>
                            <a class="button primary small" href="quiz.php?quiz_id=<?php echo (int)$quiz['id']; ?>">Take Quiz</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section id="section-results" class="teacher-section">
                <div class="card">
                    <h3 class="teacher-card-title">My Results</h3>
                    <div class="table-scroll student-results-scroll">
                        <table class="minimal-table student-results-table">
                            <thead><tr><th>Quiz</th><th>Score</th><th>Submitted</th></tr></thead>
                            <tbody>
                            <?php foreach ($attemptRows as $attempt): ?>
                                <tr>
                                    <td><?php echo e($attempt['quiz_title']); ?></td>
                                    <td><?php echo e($attempt['score']); ?> / <?php echo e($attempt['total_points']); ?></td>
                                    <td><?php echo e($attempt['submitted_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </main>
</div>

<script>
const navItems = document.querySelectorAll('.teacher-nav-item[data-target]');
const sections = document.querySelectorAll('.teacher-section');

function activateStudentSection(sectionId) {
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
        activateStudentSection(targetId);
        history.replaceState(null, '', '#' + targetId);
    });
});

document.querySelectorAll('[data-student-section]').forEach(function(el) {
    el.addEventListener('click', function(e) {
        const targetId = el.getAttribute('data-student-section');
        if (!targetId) {
            return;
        }
        e.preventDefault();
        activateStudentSection(targetId);
        history.replaceState(null, '', '#' + targetId);
    });
});

window.addEventListener('DOMContentLoaded', function() {
    const hash = (location.hash || '').replace(/^#/, '');
    if (hash && document.getElementById(hash)) {
        activateStudentSection(hash);
    }
});
</script>
</body>
</html>

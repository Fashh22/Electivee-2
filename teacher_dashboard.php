<?php
require_once 'config.php';
require_teacher();
$teacherId = current_user_id();

function parse_bulk_questions(string $raw): array
{
    $raw = trim(str_replace("\r\n", "\n", $raw));
    if ($raw === '') {
        return [];
    }

    // Allow compact format with pipe separators:
    // 1. Question ... | A) ... | B) ... | C) ... | D) ... | Answer: B
    $raw = preg_replace('/\s*\|\s*/', "\n", $raw) ?? $raw;
    $blocks = preg_split("/\n\s*\n/", $raw) ?: [];
    $results = [];
    foreach ($blocks as $block) {
        $lines = array_values(array_filter(array_map('trim', explode("\n", trim($block))), static fn($l) => $l !== ''));
        if (count($lines) < 2) {
            continue;
        }

        $questionLine = $lines[0];
        $questionLine = preg_replace('/^\d+\s*[\.\)]\s*/', '', $questionLine) ?? $questionLine;
        $questionLine = preg_replace('/^(q(?:uestion)?\s*[:.\-]?\s*)/i', '', $questionLine) ?? $questionLine;
        $choices = [];
        $correctLabel = null;
        $explicitAnswerText = null;

        foreach (array_slice($lines, 1) as $line) {
            if (preg_match('/^(?:answer|ans)\s*[:\-]\s*(.+)$/i', $line, $m)) {
                $ans = trim($m[1]);
                if (preg_match('/^[A-D]$/i', $ans)) {
                    $correctLabel = strtoupper($ans);
                } else {
                    $explicitAnswerText = $ans;
                }
                continue;
            }

            if (preg_match('/^([A-D])[\)\.\:\-]\s*(.+)$/i', $line, $m)) {
                $choices[strtoupper($m[1])] = trim($m[2]);
            }
        }

        if (count($choices) >= 2) {
            $ordered = [];
            foreach (['A', 'B', 'C', 'D'] as $label) {
                if (isset($choices[$label])) {
                    $ordered[] = ['label' => $label, 'text' => $choices[$label]];
                }
            }

            $correctIndex = 0;
            if ($correctLabel !== null) {
                foreach ($ordered as $idx => $choice) {
                    if ($choice['label'] === $correctLabel) {
                        $correctIndex = $idx;
                        break;
                    }
                }
            } elseif ($explicitAnswerText !== null) {
                foreach ($ordered as $idx => $choice) {
                    if (strcasecmp($choice['text'], $explicitAnswerText) === 0) {
                        $correctIndex = $idx;
                        break;
                    }
                }
            }

            $results[] = [
                'type' => 'multiple_choice',
                'text' => $questionLine,
                'choices' => array_column($ordered, 'text'),
                'correct' => $correctIndex,
            ];
            continue;
        }

        if ($explicitAnswerText !== null && preg_match('/^(true|false)$/i', $explicitAnswerText, $m)) {
            $results[] = [
                'type' => 'true_false',
                'text' => $questionLine,
                'answer' => strtolower($m[1]) === 'true',
            ];
        }
    }

    return $results;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create_subject') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if ($name === '') {
            set_flash('error', 'Subject name is required.');
        } else {
            $code = random_code(8);
            $pdo->prepare("
                INSERT INTO subjects (teacher_id, name, description, subject_code)
                VALUES (?, ?, ?, ?)
            ")->execute([$teacherId, $name, $description, $code]);
            set_flash('success', 'Subject created with code: ' . $code);
        }
    } elseif ($action === 'create_quiz') {
        $subjectId = (int)($_POST['subject_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $type = $_POST['quiz_type'] ?? 'multiple_choice';
        $timeLimit = max(0, (int)($_POST['time_limit'] ?? 0));
        $attemptLimit = max(1, (int)($_POST['attempt_limit'] ?? 1));
        $check = $pdo->prepare("SELECT id FROM subjects WHERE id = ? AND teacher_id = ?");
        $check->execute([$subjectId, $teacherId]);
        if (!$check->fetch()) {
            set_flash('error', 'Invalid subject.');
        } else {
            $pdo->prepare("
                INSERT INTO quizzes (subject_id, teacher_id, title, description, quiz_type, time_limit, attempt_limit, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)
            ")->execute([$subjectId, $teacherId, $title, $desc, $type, $timeLimit, $attemptLimit]);
            set_flash('success', 'Quiz created.');
        }
    } elseif ($action === 'add_question') {
        $quizId = (int)($_POST['quiz_id'] ?? 0);
        $qText = trim($_POST['question_text'] ?? '');
        $qType = $_POST['question_type'] ?? 'multiple_choice';
        $points = max(1, (int)($_POST['points'] ?? 1));
        $owned = $pdo->prepare("SELECT id FROM quizzes WHERE id = ? AND teacher_id = ?");
        $owned->execute([$quizId, $teacherId]);
        if ($owned->fetch() && $qText !== '') {
            $pdo->prepare("INSERT INTO questions (quiz_id, question_text, question_type, points) VALUES (?, ?, ?, ?)")
                ->execute([$quizId, $qText, $qType, $points]);
            $questionId = (int)$pdo->lastInsertId();
            if ($qType === 'multiple_choice') {
                $choices = $_POST['choices'] ?? [];
                $correct = (int)($_POST['correct_choice'] ?? 0);
                foreach ($choices as $i => $choice) {
                    $choice = trim($choice);
                    if ($choice === '') {
                        continue;
                    }
                    $pdo->prepare("INSERT INTO answer_choices (question_id, choice_text, is_correct) VALUES (?, ?, ?)")
                        ->execute([$questionId, $choice, $i === $correct ? 1 : 0]);
                }
            } elseif ($qType === 'true_false') {
                $correct = $_POST['tf_answer'] ?? 'true';
                $pdo->prepare("INSERT INTO answer_choices (question_id, choice_text, is_correct) VALUES (?, 'True', ?)")
                    ->execute([$questionId, $correct === 'true' ? 1 : 0]);
                $pdo->prepare("INSERT INTO answer_choices (question_id, choice_text, is_correct) VALUES (?, 'False', ?)")
                    ->execute([$questionId, $correct === 'false' ? 1 : 0]);
            } else {
                $answer = trim($_POST['short_answer_key'] ?? '');
                $pdo->prepare("INSERT INTO answer_choices (question_id, choice_text, is_correct) VALUES (?, ?, 1)")
                    ->execute([$questionId, $answer]);
            }
            $pdo->prepare("UPDATE quizzes SET total_points = (SELECT COALESCE(SUM(points), 0) FROM questions WHERE quiz_id = ?) WHERE id = ?")
                ->execute([$quizId, $quizId]);
            set_flash('success', 'Question added.');
        } else {
            set_flash('error', 'Cannot add question.');
        }
    } elseif ($action === 'bulk_add_questions') {
        $quizId = (int)($_POST['quiz_id'] ?? 0);
        $bulkText = trim((string)($_POST['bulk_questions'] ?? ''));
        $owned = $pdo->prepare("SELECT id FROM quizzes WHERE id = ? AND teacher_id = ?");
        $owned->execute([$quizId, $teacherId]);
        if (!$owned->fetch()) {
            set_flash('error', 'Invalid quiz selected.');
        } else {
            $parsed = parse_bulk_questions($bulkText);
            if (!$parsed) {
                set_flash('error', 'No valid questions detected. Follow the sample format and include Answer lines.');
            } else {
                $pdo->beginTransaction();
                try {
                    $added = 0;
                    foreach ($parsed as $item) {
                        $pdo->prepare("INSERT INTO questions (quiz_id, question_text, question_type, points) VALUES (?, ?, ?, 1)")
                            ->execute([$quizId, $item['text'], $item['type']]);
                        $questionId = (int)$pdo->lastInsertId();

                        if ($item['type'] === 'true_false') {
                            $answer = !empty($item['answer']);
                            $pdo->prepare("INSERT INTO answer_choices (question_id, choice_text, is_correct) VALUES (?, 'True', ?)")
                                ->execute([$questionId, $answer ? 1 : 0]);
                            $pdo->prepare("INSERT INTO answer_choices (question_id, choice_text, is_correct) VALUES (?, 'False', ?)")
                                ->execute([$questionId, $answer ? 0 : 1]);
                        } else {
                            $correct = (int)($item['correct'] ?? 0);
                            foreach (($item['choices'] ?? []) as $i => $choiceText) {
                                $choiceText = trim((string)$choiceText);
                                if ($choiceText === '') {
                                    continue;
                                }
                                $pdo->prepare("INSERT INTO answer_choices (question_id, choice_text, is_correct) VALUES (?, ?, ?)")
                                    ->execute([$questionId, $choiceText, $i === $correct ? 1 : 0]);
                            }
                        }
                        $added++;
                    }

                    $pdo->prepare("UPDATE quizzes SET total_points = (SELECT COALESCE(SUM(points), 0) FROM questions WHERE quiz_id = ?) WHERE id = ?")
                        ->execute([$quizId, $quizId]);
                    $pdo->commit();
                    set_flash('success', 'Bulk import complete. Added ' . $added . ' question(s).');
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    set_flash('error', 'Bulk import failed. Please check your format and try again.');
                }
            }
        }
    } elseif ($action === 'handle_request') {
        $requestId = (int)($_POST['request_id'] ?? 0);
        $decision = $_POST['decision'] ?? 'reject';
        $request = $pdo->prepare("
            SELECT sjr.id, sjr.subject_id, sjr.student_id
            FROM subject_join_requests sjr
            INNER JOIN subjects s ON s.id = sjr.subject_id
            WHERE sjr.id = ? AND sjr.status = 'pending' AND s.teacher_id = ?
        ");
        $request->execute([$requestId, $teacherId]);
        $row = $request->fetch();
        if ($row) {
            $status = $decision === 'approve' ? 'approved' : 'rejected';
            $pdo->prepare("UPDATE subject_join_requests SET status = ?, reviewed_at = NOW(), reviewed_by = ? WHERE id = ?")
                ->execute([$status, $teacherId, $requestId]);
            if ($status === 'approved') {
                $pdo->prepare("
                    INSERT INTO subject_enrollments (subject_id, student_id, status, approved_by)
                    VALUES (?, ?, 'approved', ?)
                    ON DUPLICATE KEY UPDATE status = 'approved', approved_by = VALUES(approved_by), enrolled_at = NOW()
                ")->execute([(int)$row['subject_id'], (int)$row['student_id'], $teacherId]);
            }
            set_flash('success', 'Request updated.');
        }
    } elseif ($action === 'toggle_quiz') {
        $quizId = (int)($_POST['quiz_id'] ?? 0);
        $newStatus = (int)($_POST['new_status'] ?? 0);
        $pdo->prepare("UPDATE quizzes SET is_active = ? WHERE id = ? AND teacher_id = ?")
            ->execute([$newStatus, $quizId, $teacherId]);
        set_flash('success', 'Quiz status updated.');
    } elseif ($action === 'delete_quiz') {
        $quizId = (int)($_POST['quiz_id'] ?? 0);
        $deleted = $pdo->prepare("DELETE FROM quizzes WHERE id = ? AND teacher_id = ?");
        $deleted->execute([$quizId, $teacherId]);
        if ($deleted->rowCount() > 0) {
            set_flash('success', 'Quiz removed successfully.');
        } else {
            set_flash('error', 'Quiz not found or already removed.');
        }
    }
    redirect('teacher_dashboard.php');
}

$flash = get_flash();
$subjects = $pdo->prepare("SELECT * FROM subjects WHERE teacher_id = ? ORDER BY created_at DESC");
$subjects->execute([$teacherId]);
$subjectRows = $subjects->fetchAll();

$quizzes = $pdo->prepare("
    SELECT q.*, s.name AS subject_name
    FROM quizzes q
    INNER JOIN subjects s ON s.id = q.subject_id
    WHERE q.teacher_id = ?
    ORDER BY q.created_at DESC
");
$quizzes->execute([$teacherId]);
$quizRows = $quizzes->fetchAll();

$pendingRequests = $pdo->prepare("
    SELECT sjr.id, sjr.requested_at, s.name AS subject_name, u.name AS student_name, u.email AS student_email
    FROM subject_join_requests sjr
    INNER JOIN subjects s ON s.id = sjr.subject_id
    INNER JOIN users u ON u.id = sjr.student_id
    WHERE s.teacher_id = ? AND sjr.status = 'pending'
    ORDER BY sjr.requested_at ASC
");
$pendingRequests->execute([$teacherId]);
$requestRows = $pendingRequests->fetchAll();

$attempts = $pdo->prepare("
    SELECT qa.score, qa.total_points, qa.submitted_at, u.name AS student_name, q.title AS quiz_title
    FROM quiz_attempts qa
    INNER JOIN quizzes q ON q.id = qa.quiz_id
    INNER JOIN users u ON u.id = qa.student_id
    WHERE q.teacher_id = ? AND qa.is_finished = 1
    ORDER BY qa.submitted_at DESC
    LIMIT 100
");
$attempts->execute([$teacherId]);
$attemptRows = $attempts->fetchAll();

$stmtEnrolled = $pdo->prepare("
    SELECT COUNT(DISTINCT se.student_id) FROM subject_enrollments se
    INNER JOIN subjects s ON s.id = se.subject_id
    WHERE s.teacher_id = ? AND se.status = 'approved'
");
$stmtEnrolled->execute([$teacherId]);
$teacherEnrolledStudents = (int)$stmtEnrolled->fetchColumn();

$stmtFinishedAttempts = $pdo->prepare("
    SELECT COUNT(*) FROM quiz_attempts qa
    INNER JOIN quizzes q ON q.id = qa.quiz_id
    WHERE q.teacher_id = ? AND qa.is_finished = 1
");
$stmtFinishedAttempts->execute([$teacherId]);
$teacherFinishedAttempts = (int)$stmtFinishedAttempts->fetchColumn();

$stmtQuestionCount = $pdo->prepare("
    SELECT COUNT(*) FROM questions qn
    INNER JOIN quizzes q ON q.id = qn.quiz_id
    WHERE q.teacher_id = ?
");
$stmtQuestionCount->execute([$teacherId]);
$teacherQuestionCount = (int)$stmtQuestionCount->fetchColumn();

$teacherActiveQuizzes = 0;
foreach ($quizRows as $qz) {
    if ((int)$qz['is_active']) {
        $teacherActiveQuizzes++;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Teacher Dashboard</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="teacher-page">
<div class="teacher-layout">
    <aside class="teacher-sidebar">
        <div class="teacher-sidebar-brand">Teacher Panel</div>
        <a href="teacher_dashboard.php#section-overview" class="teacher-nav-item active" data-target="section-overview">Overview</a>
        <a href="teacher_dashboard.php#section-subjects" class="teacher-nav-item" data-target="section-subjects">Subjects</a>
        <a href="teacher_dashboard.php#section-quizzes" class="teacher-nav-item" data-target="section-quizzes">Quizzes</a>
        <a href="teacher_dashboard.php#section-requests" class="teacher-nav-item" data-target="section-requests">Join Requests</a>
        <a href="teacher_dashboard.php#section-results" class="teacher-nav-item" data-target="section-results">Results</a>
        <a href="teacher_profile.php" class="teacher-nav-item teacher-nav-profile">Profile</a>
    </aside>

    <main class="teacher-main">
        <div class="header teacher-header">
            <div>
                <h2>Teacher Workspace</h2>
                <p class="teacher-subtitle">Manage subjects, quizzes, and student progress.</p>
            </div>
        </div>

        <div class="container wide teacher-wrap">
            <?php if ($flash): ?><div class="msg <?php echo e($flash['type']); ?>"><?php echo e($flash['message']); ?></div><?php endif; ?>

            <section id="section-overview" class="teacher-section active">
                <div class="admin-overview-head">
                    <h3 class="teacher-card-title admin-overview-title">Teaching overview</h3>
                    <p class="admin-overview-lead">Monitor subjects, assessments, enrollments, and student submissions. Cards link into the areas where you manage day-to-day work.</p>
                </div>

                <div class="admin-overview-stat-grid">
                    <a href="#section-subjects" class="admin-stat-card admin-stat-card--link" data-teacher-section="section-subjects">
                        <div class="admin-stat-card-inner">
                            <div class="admin-stat-icon admin-stat-icon--subjects" aria-hidden="true">C</div>
                            <div class="admin-stat-body">
                                <span class="admin-stat-label">Subjects</span>
                                <strong class="admin-stat-value"><?php echo count($subjectRows); ?></strong>
                                <span class="admin-stat-hint"><?php echo $teacherEnrolledStudents; ?> enrolled student<?php echo $teacherEnrolledStudents === 1 ? '' : 's'; ?> total</span>
                            </div>
                        </div>
                    </a>
                    <a href="#section-quizzes" class="admin-stat-card admin-stat-card--link" data-teacher-section="section-quizzes">
                        <div class="admin-stat-card-inner">
                            <div class="admin-stat-icon admin-stat-icon--quizzes" aria-hidden="true">Q</div>
                            <div class="admin-stat-body">
                                <span class="admin-stat-label">Quizzes</span>
                                <strong class="admin-stat-value"><?php echo count($quizRows); ?></strong>
                                <span class="admin-stat-hint"><?php echo $teacherActiveQuizzes; ?> active · <?php echo $teacherQuestionCount; ?> question<?php echo $teacherQuestionCount === 1 ? '' : 's'; ?> total</span>
                            </div>
                        </div>
                    </a>
                    <div class="admin-stat-card">
                        <div class="admin-stat-card-inner">
                            <div class="admin-stat-icon admin-stat-icon--students" aria-hidden="true">S</div>
                            <div class="admin-stat-body">
                                <span class="admin-stat-label">Roster reach</span>
                                <strong class="admin-stat-value"><?php echo $teacherEnrolledStudents; ?></strong>
                                <span class="admin-stat-hint">Unique students across subjects</span>
                            </div>
                        </div>
                    </div>
                    <a href="#section-requests" class="admin-stat-card admin-stat-card--link" data-teacher-section="section-requests">
                        <div class="admin-stat-card-inner">
                            <div class="admin-stat-icon admin-stat-icon--teachers" aria-hidden="true">R</div>
                            <div class="admin-stat-body">
                                <span class="admin-stat-label">Join requests</span>
                                <strong class="admin-stat-value"><?php echo count($requestRows); ?></strong>
                                <span class="admin-stat-hint"><?php echo count($requestRows) ? 'Needs your review' : 'Inbox clear'; ?></span>
                            </div>
                        </div>
                    </a>
                    <a href="#section-results" class="admin-stat-card admin-stat-card--link" data-teacher-section="section-results">
                        <div class="admin-stat-card-inner">
                            <div class="admin-stat-icon admin-stat-icon--attempts" aria-hidden="true">A</div>
                            <div class="admin-stat-body">
                                <span class="admin-stat-label">Finished attempts</span>
                                <strong class="admin-stat-value"><?php echo $teacherFinishedAttempts; ?></strong>
                                <span class="admin-stat-hint">Submitted quiz runs (all time)</span>
                            </div>
                        </div>
                    </a>
                    <div class="admin-stat-card">
                        <div class="admin-stat-card-inner">
                            <div class="admin-stat-icon admin-stat-icon--users" aria-hidden="true">L</div>
                            <div class="admin-stat-body">
                                <span class="admin-stat-label">Recent log</span>
                                <strong class="admin-stat-value"><?php echo count($attemptRows); ?></strong>
                                <span class="admin-stat-hint">Latest rows in Results (up to 100)</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="admin-overview-panels">
                    <?php if (count($requestRows) > 0): ?>
                    <div class="card admin-overview-priority">
                        <div class="admin-overview-priority-copy">
                            <span class="admin-overview-priority-kicker">Inbox</span>
                            <strong class="admin-overview-priority-title"><?php echo count($requestRows); ?> student join request<?php echo count($requestRows) === 1 ? '' : 's'; ?> waiting</strong>
                            <p class="admin-overview-priority-text">Approve or reject from the join requests workspace so students can access your subjects.</p>
                        </div>
                        <a href="#section-requests" class="button success small admin-overview-priority-cta" data-teacher-section="section-requests">Review queue</a>
                    </div>
                    <?php endif; ?>

                    <div class="card admin-overview-quick-card">
                        <h4 class="admin-overview-quick-heading">Shortcuts</h4>
                        <ul class="admin-overview-quick-list">
                            <li><a href="#section-subjects" data-teacher-section="section-subjects">Subjects &amp; codes</a></li>
                            <li><a href="#section-quizzes" data-teacher-section="section-quizzes">Create &amp; edit quizzes</a></li>
                            <li><a href="#section-requests" data-teacher-section="section-requests">Join requests<?php if (count($requestRows) > 0): ?> <span class="admin-quick-badge"><?php echo count($requestRows); ?></span><?php endif; ?></a></li>
                            <li><a href="#section-results" data-teacher-section="section-results">Student results</a></li>
                        </ul>
                    </div>
                </div>
            </section>

            <section id="section-subjects" class="teacher-section">
                <div class="teacher-grid">
                    <div class="card">
                        <h3 class="teacher-card-title">Create Subject</h3>
                        <form method="post" class="teacher-form">
                            <input type="hidden" name="action" value="create_subject">
                            <label>Subject Name</label>
                            <input type="text" name="name" required>
                            <label>Description</label>
                            <textarea name="description"></textarea>
                            <button class="button success" type="submit">Create Subject</button>
                        </form>
                    </div>
                    <div class="card">
                        <h3 class="teacher-card-title">My Subjects</h3>
                        <?php if (!$subjectRows): ?><p class="teacher-empty">No subjects created yet.</p><?php endif; ?>
                        <?php foreach ($subjectRows as $subject): ?>
                            <div class="request-item">
                                <div>
                                    <p class="request-main"><strong><?php echo e($subject['name']); ?></strong></p>
                                    <p class="request-sub">Code: <?php echo e($subject['subject_code']); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <section id="section-quizzes" class="teacher-section">
                <div class="teacher-grid teacher-quizzes-grid">
                <div class="card">
                    <h3 class="teacher-card-title">Create Quiz</h3>
                    <form method="post" class="teacher-form">
                        <input type="hidden" name="action" value="create_quiz">
                        <label>Subject</label>
                        <select name="subject_id" required>
                            <?php foreach ($subjectRows as $subject): ?>
                                <option value="<?php echo (int)$subject['id']; ?>"><?php echo e($subject['name']); ?> (<?php echo e($subject['subject_code']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <label>Title</label>
                        <input type="text" name="title" required>
                        <label>Description</label>
                        <textarea name="description"></textarea>
                        <label>Quiz Type</label>
                        <select name="quiz_type">
                            <option value="multiple_choice">Multiple Choice</option>
                            <option value="true_false">True/False</option>
                            <option value="short_answer">Short Answer</option>
                            <option value="mixed">Mixed</option>
                        </select>
                        <div class="teacher-form-row">
                            <div>
                                <label>Time Limit (minutes)</label>
                                <input type="number" name="time_limit" value="0" min="0">
                            </div>
                            <div>
                                <label>Attempt Limit</label>
                                <input type="number" name="attempt_limit" value="1" min="1">
                            </div>
                        </div>
                        <button class="button success" type="submit">Create Quiz</button>
                    </form>

                </div>

                <div class="card">
                    <h3 class="teacher-card-title">My Quizzes</h3>
                    <?php foreach ($quizRows as $quiz): ?>
                        <div class="quiz-item">
                            <div class="quiz-head">
                                <div>
                                    <p class="quiz-title"><?php echo e($quiz['title']); ?></p>
                                    <p class="quiz-meta"><?php echo e($quiz['subject_name']); ?> · <?php echo (int)$quiz['is_active'] ? 'Active' : 'Inactive'; ?></p>
                                </div>
                                <form method="post">
                                    <input type="hidden" name="action" value="toggle_quiz">
                                    <input type="hidden" name="quiz_id" value="<?php echo (int)$quiz['id']; ?>">
                                    <input type="hidden" name="new_status" value="<?php echo (int)$quiz['is_active'] ? 0 : 1; ?>">
                                    <button class="button small <?php echo (int)$quiz['is_active'] ? 'warning' : 'success'; ?>" type="submit"><?php echo (int)$quiz['is_active'] ? 'Deactivate' : 'Activate'; ?></button>
                                </form>
                                <form method="post" onsubmit="return confirm('Remove this quiz and all its questions?');">
                                    <input type="hidden" name="action" value="delete_quiz">
                                    <input type="hidden" name="quiz_id" value="<?php echo (int)$quiz['id']; ?>">
                                    <button class="button small danger" type="submit">Remove</button>
                                </form>
                            </div>
                            <details class="quiz-details">
                                <summary>Add Question</summary>
                                <form method="post" class="teacher-form">
                                    <input type="hidden" name="action" value="add_question">
                                    <input type="hidden" name="quiz_id" value="<?php echo (int)$quiz['id']; ?>">
                                    <label>Question text</label>
                                    <input type="text" name="question_text" required>
                                    <label>Type</label>
                                    <select name="question_type">
                                        <option value="multiple_choice">Multiple Choice</option>
                                        <option value="true_false">True/False</option>
                                        <option value="short_answer">Short Answer</option>
                                    </select>
                                    <label>Points</label>
                                    <input type="number" name="points" value="1" min="1">
                                    <label>MC Choice A</label>
                                    <input type="text" name="choices[]" placeholder="Choice A">
                                    <label>MC Choice B</label>
                                    <input type="text" name="choices[]" placeholder="Choice B">
                                    <label>MC Choice C</label>
                                    <input type="text" name="choices[]" placeholder="Choice C">
                                    <label>MC Choice D</label>
                                    <input type="text" name="choices[]" placeholder="Choice D">
                                    <label>Correct MC index (0-3)</label>
                                    <input type="number" name="correct_choice" value="0" min="0" max="3">
                                    <label>True/False answer</label>
                                    <select name="tf_answer"><option value="true">True</option><option value="false">False</option></select>
                                    <label>Short answer key</label>
                                    <input type="text" name="short_answer_key">
                                    <button class="button success" type="submit">Save Question</button>
                                </form>
                            </details>
                            <details class="quiz-details" style="margin-top: 8px;">
                                <summary>Bulk Paste Questions & Answers</summary>
                                <form method="post" class="teacher-form">
                                    <input type="hidden" name="action" value="bulk_add_questions">
                                    <input type="hidden" name="quiz_id" value="<?php echo (int)$quiz['id']; ?>">
                                    <label>Paste Q&A list</label>
                                    <textarea name="bulk_questions" rows="10" placeholder="Question: What is 2 + 2?
A) 3
B) 4
C) 5
D) 6
Answer: B

Question: The sun rises in the east.
Answer: True"></textarea>
                                    <p class="request-sub">
                                        Format per question block: question line, options (A-D) for multiple choice, and Answer line.
                                        Use blank lines between questions. Answer can be letter (A/B/C/D), option text, or True/False.
                                    </p>
                                    <button class="button success" type="submit">Import Questions</button>
                                </form>
                            </details>
                        </div>
                    <?php endforeach; ?>
                </div>
                </div>
            </section>

            <section id="section-requests" class="teacher-section">
                <div class="card">
                    <h3 class="teacher-card-title">Pending Join Requests</h3>
                    <?php if (!$requestRows): ?><p class="teacher-empty">No pending requests.</p><?php endif; ?>
                    <?php foreach ($requestRows as $row): ?>
                        <div class="request-item">
                            <div>
                                <p class="request-main"><strong><?php echo e($row['student_name']); ?></strong> (<?php echo e($row['student_email']); ?>)</p>
                                <p class="request-sub">Requested subject: <strong><?php echo e($row['subject_name']); ?></strong></p>
                            </div>
                            <div class="request-actions">
                                <form method="post">
                                    <input type="hidden" name="action" value="handle_request">
                                    <input type="hidden" name="request_id" value="<?php echo (int)$row['id']; ?>">
                                    <input type="hidden" name="decision" value="approve">
                                    <button class="button success small" type="submit">Approve</button>
                                </form>
                                <form method="post">
                                    <input type="hidden" name="action" value="handle_request">
                                    <input type="hidden" name="request_id" value="<?php echo (int)$row['id']; ?>">
                                    <input type="hidden" name="decision" value="reject">
                                    <button class="button danger small" type="submit">Reject</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section id="section-results" class="teacher-section">
                <div class="card">
                    <h3 class="teacher-card-title">Student Results</h3>
                    <table class="minimal-table">
                        <thead><tr><th>Student</th><th>Quiz</th><th>Score</th><th>Submitted</th></tr></thead>
                        <tbody>
                        <?php foreach ($attemptRows as $attempt): ?>
                            <tr>
                                <td><?php echo e($attempt['student_name']); ?></td>
                                <td><?php echo e($attempt['quiz_title']); ?></td>
                                <td><?php echo e($attempt['score']); ?> / <?php echo e($attempt['total_points']); ?></td>
                                <td><?php echo e($attempt['submitted_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>
</div>

<script>
const navItems = document.querySelectorAll('.teacher-nav-item[data-target]');
const sections = document.querySelectorAll('.teacher-section');

function activateTeacherSection(sectionId) {
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
        activateTeacherSection(targetId);
        history.replaceState(null, '', '#' + targetId);
    });
});

document.querySelectorAll('[data-teacher-section]').forEach(function(el) {
    el.addEventListener('click', function(e) {
        const targetId = el.getAttribute('data-teacher-section');
        if (!targetId) {
            return;
        }
        e.preventDefault();
        activateTeacherSection(targetId);
        history.replaceState(null, '', '#' + targetId);
    });
});

window.addEventListener('DOMContentLoaded', function() {
    const hash = (location.hash || '').replace(/^#/, '');
    if (hash && document.getElementById(hash)) {
        activateTeacherSection(hash);
    }
});
</script>
</body>
</html>
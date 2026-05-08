<?php
require_once 'config.php';
require_student();
$studentId = current_user_id();
$quizId = (int)($_GET['quiz_id'] ?? 0);
$error = '';

$quiz = $pdo->prepare("
    SELECT q.*, s.id AS subject_id
    FROM quizzes q
    INNER JOIN subjects s ON s.id = q.subject_id
    WHERE q.id = ? AND q.is_active = 1
");
$quiz->execute([$quizId]);
$quizData = $quiz->fetch();
if (!$quizData) {
    redirect('student_dashboard.php');
}

$enrolled = $pdo->prepare("
    SELECT id FROM subject_enrollments
    WHERE subject_id = ? AND student_id = ? AND status = 'approved'
");
$enrolled->execute([(int)$quizData['subject_id'], $studentId]);
if (!$enrolled->fetch()) {
    set_flash('error', 'You are not enrolled in this subject.');
    redirect('student_dashboard.php');
}

$attemptCountStmt = $pdo->prepare("
    SELECT COUNT(*) FROM quiz_attempts
    WHERE quiz_id = ? AND student_id = ? AND is_finished = 1
");
$attemptCountStmt->execute([$quizId, $studentId]);
$attemptCount = (int)$attemptCountStmt->fetchColumn();
if ($attemptCount >= (int)$quizData['attempt_limit']) {
    set_flash('error', 'Attempt limit reached for this quiz.');
    redirect('student_dashboard.php');
}

$questionsStmt = $pdo->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY id");
$questionsStmt->execute([$quizId]);
$questions = $questionsStmt->fetchAll();

$choicesStmt = $pdo->prepare("SELECT * FROM answer_choices WHERE question_id = ? ORDER BY id");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'finish') {
    $attemptNo = $attemptCount + 1;
    $pdo->beginTransaction();
    try {
        $pdo->prepare("
            INSERT INTO quiz_attempts (quiz_id, student_id, attempt_no, started_at, is_finished)
            VALUES (?, ?, ?, NOW(), 0)
        ")->execute([$quizId, $studentId, $attemptNo]);
        $attemptId = (int)$pdo->lastInsertId();

        $score = 0.0;
        $total = 0;

        foreach ($questions as $question) {
            $qId = (int)$question['id'];
            $qType = $question['question_type'];
            $points = (int)$question['points'];
            $total += $points;
            $isCorrect = 0;
            $awarded = 0;
            $choiceId = null;
            $shortAnswer = null;

            $choicesStmt->execute([$qId]);
            $choices = $choicesStmt->fetchAll();
            $correctChoices = array_values(array_filter($choices, fn($c) => (int)$c['is_correct'] === 1));

            if ($qType === 'short_answer') {
                $shortAnswer = trim((string)($_POST['q_' . $qId] ?? ''));
                $answerKey = strtolower(trim((string)($correctChoices[0]['choice_text'] ?? '')));
                if ($shortAnswer !== '' && strtolower($shortAnswer) === $answerKey) {
                    $isCorrect = 1;
                    $awarded = $points;
                }
            } else {
                $choiceId = (int)($_POST['q_' . $qId] ?? 0);
                foreach ($correctChoices as $correct) {
                    if ((int)$correct['id'] === $choiceId) {
                        $isCorrect = 1;
                        $awarded = $points;
                        break;
                    }
                }
            }

            $score += $awarded;
            $pdo->prepare("
                INSERT INTO quiz_attempt_answers (attempt_id, question_id, choice_id, short_answer, is_correct, awarded_points)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([$attemptId, $qId, $choiceId ?: null, $shortAnswer, $isCorrect, $awarded]);
        }

        $pdo->prepare("
            UPDATE quiz_attempts
            SET score = ?, total_points = ?, submitted_at = NOW(), is_finished = 1
            WHERE id = ?
        ")->execute([$score, $total, $attemptId]);

        $pdo->commit();
        set_flash('success', "Quiz submitted. Score: {$score} / {$total}");
        redirect('student_dashboard.php');
    } catch (Throwable $e) {
        $pdo->rollBack();
        $error = 'Failed to submit quiz.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo e($quizData['title']); ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="teacher-page app-quiz-page">
<div class="header quiz-take-header">
    <div class="quiz-take-header-inner">
        <h2><?php echo e($quizData['title']); ?></h2>
        <a href="student_dashboard.php" class="button secondary small">← Back</a>
    </div>
</div>
<div class="container wide teacher-wrap quiz-take-wrap">
    <?php if ($error): ?><div class="msg err"><?php echo e($error); ?></div><?php endif; ?>
    <?php if (trim((string)$quizData['description']) !== ''): ?>
        <p class="quiz-take-intro"><?php echo e($quizData['description']); ?></p>
    <?php endif; ?>
    <form method="post" class="quiz-take-form">
        <input type="hidden" name="action" value="finish">
        <?php foreach ($questions as $index => $question): ?>
            <?php
            $choicesStmt->execute([(int)$question['id']]);
            $choices = $choicesStmt->fetchAll();
            ?>
            <div class="card quiz-question-card">
                <h3 class="quiz-question-title">Q<?php echo $index + 1; ?>. <?php echo e($question['question_text']); ?> <span class="quiz-question-points"><?php echo (int)$question['points']; ?> pt</span></h3>
                <?php if ($question['question_type'] === 'short_answer'): ?>
                    <input type="text" class="quiz-text-answer" name="q_<?php echo (int)$question['id']; ?>" required placeholder="Your answer">
                <?php else: ?>
                    <div class="quiz-choice-list">
                    <?php foreach ($choices as $choice): ?>
                        <label class="quiz-choice">
                            <input type="radio" name="q_<?php echo (int)$question['id']; ?>" value="<?php echo (int)$choice['id']; ?>" required>
                            <span><?php echo e($choice['choice_text']); ?></span>
                        </label>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <div class="quiz-submit-row">
            <button class="button success" type="submit">Submit Quiz</button>
        </div>
    </form>
</div>
</body>
</html>

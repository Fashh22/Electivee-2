<?php
require_once 'config.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_role('admin');

$user_id = $_SESSION['user_id'];

// =================================================================
// POST ACTION HANDLER (FINAL FIX)
// =================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // 1. Create Quiz Logic
    if ($action === 'create_quiz') {
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if (!empty($title)) {
            $stmt = $pdo->prepare("INSERT INTO quizzes (title, description, created_by, is_active) VALUES (?, ?, ?, 1)");
            $stmt->execute([$title, $desc, $user_id]);
        }
    }

    // 2. Delete Teacher Activity (The Quiz itself)
    elseif ($action === 'delete_teacher_activity') {
        $quiz_id = (int)$_POST['quiz_id'];
        try {
            $pdo->beginTransaction();
            // CRITICAL: Delete results associated with this quiz first
            $pdo->prepare("DELETE FROM results WHERE quiz_id = ?")->execute([$quiz_id]);
            // Now delete the quiz
            $pdo->prepare("DELETE FROM quizzes WHERE id = ?")->execute([$quiz_id]);
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            // Log error: $e->getMessage();
        }
    }

    // 3. Delete Student Activity (Single Attempt)
    elseif ($action === 'delete_student_activity') {
        $result_id = (int)$_POST['result_id'];
        $pdo->prepare("DELETE FROM results WHERE id = ?")->execute([$result_id]);
    }

    // 4. Global Performance Purge
    elseif ($action === 'delete_all_results') {
        $pdo->query("DELETE FROM results");
    }

    // Always redirect after a POST to prevent "Form Resubmission" popups
    header('Location: admin_dashboard.php');
    exit;
}

// --- DATA RETRIEVAL (Fetch real data from your DB) ---
// Note: Replace these queries with your actual table column names if different
$all_quizzes = $pdo->query("SELECT * FROM quizzes ORDER BY id DESC LIMIT 5")->fetchAll();
$all_results = $pdo->query("SELECT r.*, u.name as student_name, q.title as quiz_title FROM results r JOIN users u ON r.user_id = u.id JOIN quizzes q ON r.quiz_id = q.id ORDER BY r.id DESC LIMIT 5")->fetchAll();

$enrolled = 856;
$activity_data = ['MON' => 320, 'TUE' => 510, 'WED' => 810, 'THU' => 420, 'FRI' => 680];

if (!function_exists('e')) {
    function e($value) { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Oversight</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="modal.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.css">
</head>
<body class="admin-dashboard-layout">

    <aside class="admin-sidebar">
        <div class="sidebar-brand">
            <i class="fas fa-shield-alt gold-text"></i>
            <span>CodeQuest ADMIN</span>
        </div>
        
        <nav class="sidebar-menu">
            <a href="javascript:void(0)" class="menu-item active" onclick="showSection('overview', this)">
                <i class="fas fa-th-large"></i> Dashboard Overview
            </a>
            <a href="javascript:void(0)" class="menu-item" onclick="showSection('teacher-section', this)">
                <i class="fas fa-chalkboard-teacher"></i> Teacher Monitoring
            </a>
            <a href="javascript:void(0)" class="menu-item" onclick="showSection('student-section', this)">
                <i class="fas fa-user-graduate"></i> Student Activities
            </a>
            <div class="menu-divider"></div>
            <a href="javascript:void(0)" class="menu-item" onclick="showSection('settings-section', this)">
                <i class="fas fa-cog"></i> System Settings
            </a>
            <a href="logout.php" class="menu-item logout-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </aside>

    <main class="dashboard-main">
        <header class="main-header">
            <div class="header-title">
                <h1 id="page-title">Dashboard Overview</h1>
                <p>Real-time system metrics and oversight.</p>
            </div>
            <div class="admin-profile-pill">
                <div class="admin-info">
                    <span class="user-name">Angel Nicole Rojas</span>
                    <span class="user-role">System Administrator</span>
                </div>
                <div class="avatar-circle">AR</div>
            </div>
        </header>

        <div class="dashboard-content">
            
            <section id="overview" class="content-section">
                <div class="kpi-grid-emphasized">
                    <div class="kpi-card large">
                        <div class="kpi-icon-wrap blue-gradient"><i class="fas fa-user-graduate"></i></div>
                        <div class="kpi-data">
                            <span class="kpi-value">1,240</span>
                            <span class="kpi-label">Total Students</span>
                        </div>
                    </div>
                    <div class="kpi-card large">
                        <div class="kpi-icon-wrap purple-gradient"><i class="fas fa-chalkboard-teacher"></i></div>
                        <div class="kpi-data">
                            <span class="kpi-value">42</span>
                            <span class="kpi-label">Total Teachers</span>
                        </div>
                    </div>
                    <div class="kpi-card large">
                        <div class="kpi-icon-wrap gold-gradient"><i class="fas fa-id-card"></i></div>
                        <div class="kpi-data">
                            <span class="kpi-value">856</span>
                            <span class="kpi-label">Enrolled Students</span>
                        </div>
                    </div>
                </div>

                <div class="card visual-section full-width">
                    <div class="card-header"><h3><i class="fas fa-chart-line"></i> Global System Activity</h3></div>
                    <div class="graph-container">
                        <div class="bar-chart">
                            <div class="bar-group"><div class="bar" style="height: 40%;"></div><span>Mon</span></div>
                            <div class="bar-group"><div class="bar" style="height: 65%;"></div><span>Tue</span></div>
                            <div class="bar-group"><div class="bar active" style="height: 95%;"></div><span>Wed</span></div>
                            <div class="bar-group"><div class="bar" style="height: 50%;"></div><span>Thu</span></div>
                            <div class="bar-group"><div class="bar" style="height: 80%;"></div><span>Fri</span></div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="teacher-section" class="content-section" style="display:none;">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-history"></i> Faculty Oversight</h3>
                        <span class="badge">Monitoring 42 Teachers</span>
                    </div>
                    <div class="table-container">
                        <table class="minimal-table">
                            <thead>
                                <tr>
                                    <th>Faculty Member</th>
                                    <th>Department</th>
                                    <th>Quizzes Uploaded</th>
                                    <th>Latest Action</th>
                                    <th>Status</th>
                                    <th>Delete</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <div class="user-cell">
                                            <div class="mini-avatar">PJ</div>
                                            <div><strong>Prof. Jameson</strong><br><small>pjameson@wmsu.edu.ph</small></div>
                                        </div>
                                    </td>
                                    <td>College of Computing Studies</td>
                                    <td class="text-center">15</td>
                                    <td>Updated "Data Structures"</td>
                                    <td><span class="status-pill success">Active</span></td>
                                    <td><button class="remove-btn"><i class="fas fa-trash"></i></button></td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="user-cell">
                                            <div class="mini-avatar">AR</div>
                                            <div><strong>Instructor Reyes</strong><br><small>mreyes@wmsu.edu.ph</small></div>
                                        </div>
                                    </td>
                                    <td>Information Technology</td>
                                    <td class="text-center">08</td>
                                    <td>Modified Quiz #12</td>
                                    <td><span class="status-pill success">Active</span></td>
                                    <td><button class="remove-btn"><i class="fas fa-trash"></i></button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section id="student-section" class="content-section" style="display:none;">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-running"></i> Student Engagement Detailed</h3>
                        <span class="badge">856 Active Sessions</span>
                    </div>
                    <div class="table-container">
                        <table class="minimal-table">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Year & Section</th>
                                    <th>Recent Quiz Taken</th>
                                    <th>Score</th>
                                    <th>Time Spent</th>
                                    <th>Delete</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <div class="user-cell">
                                            <div class="mini-avatar">JD</div>
                                            <div><strong>Juan Dela Cruz</strong><br><small>jd.cruz@student.wmsu.edu</small></div>
                                        </div>
                                    </td>
                                    <td>BSIT - 3B</td>
                                    <td>PHP Fundamentals</td>
                                    <td><span class="score-text highlight">92/100</span></td>
                                    <td>14m 20s</td>
                                    <td><button class="remove-btn"><i class="fas fa-trash-alt"></i></button></td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="user-cell">
                                            <div class="mini-avatar">AS</div>
                                            <div><strong>Alice Smith</strong><br><small>a.smith@student.wmsu.edu</small></div>
                                        </div>
                                    </td>
                                    <td>BSCS - 2A</td>
                                    <td>Intro to SQL</td>
                                    <td><span class="score-text">76/100</span></td>
                                    <td>22m 05s</td>
                                    <td><button class="remove-btn"><i class="fas fa-trash-alt"></i></button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section id="settings-section" class="content-section" style="display:none;">
                <div class="card settings-card">
                    <div class="card-header"><h3><i class="fas fa-sliders-h"></i> System Controls</h3></div>
                    <div class="setting-item">
                        <div class="setting-text">
                            <strong>Maintenance Mode</strong>
                            <span>Disable student login while updates are being performed.</span>
                        </div>
                        <button class="btn-toggle">Enable</button>
                    </div>
                    <div class="setting-item danger">
                        <div class="setting-text">
                            <strong>Global Performance Purge</strong>
                            <span>Permanently delete all previous quiz results.</span>
                        </div>
                        <button class="btn-danger-outline">Reset Records</button>
                    </div>
                </div>
            </section>

        </div>
    </main>

    <script>
        function showSection(sectionId, element) {
            // Hide all sections
            document.querySelectorAll('.content-section').forEach(section => {
                section.style.display = 'none';
            });
            // Show selected section
            document.getElementById(sectionId).style.display = 'block';
            
            // Sidebar Active State
            document.querySelectorAll('.menu-item').forEach(item => item.classList.remove('active'));
            element.classList.add('active');

            // Header Title Update
            const titles = {
                'overview': 'Dashboard Overview',
                'teacher-section': 'Teacher Monitoring',
                'student-section': 'Student Activities',
                'settings-section': 'System Settings'
            };
            document.getElementById('page-title').innerText = titles[sectionId];
        }
    </script>
</body>
</html>
<?php
// ══════════════════════════════════════
//  WMSU OESCD — Student Enrollment
// ══════════════════════════════════════
require_once __DIR__ . '/../config/database.php';
session_start();

// Auth Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../auth/login.php?role=student');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';

// 1. Fetch accurate student data and ID
$stmt = $pdo->prepare("SELECT student_id, first_name FROM students WHERE user_id = ?");
$stmt->execute([$user_id]);
$student = $stmt->fetch();

if (!$student) {
    die("Student profile not found. Please contact an administrator.");
}

$student_id = $student['student_id'];

/**
 * 2. HANDLE ENROLLMENT SUBMISSION
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_enrollment'])) {
    $course_id = $_POST['course_id'];
    $semester = "2nd Sem 2024-2025"; 

    // Prevent duplicate active enrollments
    $check = $pdo->prepare("SELECT enrollment_id FROM enrollment_forms WHERE student_id = ? AND course_id = ? AND status != 'rejected'");
    $check->execute([$student_id, $course_id]);

    if ($check->rowCount() > 0) {
        $message = "You already have a pending or approved enrollment for this course.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO enrollment_forms (student_id, course_id, semester, status, enrollment_method) VALUES (?, ?, ?, 'pending', 'online')");
        if ($stmt->execute([$student_id, $course_id, $semester])) {
            $message = "Application submitted successfully!";
        }
    }
}

/**
 * 3. FETCH AVAILABLE COURSES
 */
$courses = $pdo->query("SELECT * FROM courses WHERE is_active = 1 ORDER BY course_name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Enroll in Course | WMSU DESCD</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Syne:wght@700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-body: #0a0c10;
            --sidebar-bg: #111419;
            --card-bg: #14171c;
            --gold: #f1b933;
            --border: #1e2229;
            --text-muted: #64748b;
        }

        body {
            background-color: var(--bg-body);
            color: #ffffff;
            font-family: 'Inter', sans-serif;
            margin: 0;
            display: flex;
        }

        /* Sidebar Navigation - Exact Match to Dashboard */
        .sidebar {
            width: 240px;
            height: 100vh;
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border);
            padding: 20px;
            position: fixed;
            z-index: 1000;
        }

        .sidebar-brand {
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            color: var(--gold);
            margin-bottom: 40px;
            font-size: 1.2rem;
            display: block;
            text-decoration: none;
        }

        .nav-link {
            color: var(--text-muted);
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: 0.2s;
        }

        .nav-link.active {
            background: rgba(241, 185, 51, 0.1);
            color: var(--gold);
            font-weight: 600;
        }

        .nav-link:hover:not(.active) {
            color: white;
            background: rgba(255, 255, 255, 0.05);
        }

        /* Content Layout */
        .main-wrapper {
            margin-left: 240px;
            width: calc(100% - 240px);
        }

        .top-nav {
            height: 70px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding: 0 40px;
            gap: 20px;
        }

        .content-area {
            padding: 40px;
            max-width: 1200px;
        }

        .portal-badge {
            display: inline-block;
            background: rgba(241, 185, 51, 0.1);
            color: var(--gold);
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1px;
            margin-bottom: 15px;
            border-left: 3px solid var(--gold);
        }

        h1.page-title {
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: 2.8rem;
            letter-spacing: -1px;
            margin-bottom: 10px;
        }

        .card-course {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 30px;
            height: 100%;
            display: flex;
            flex-direction: column;
            transition: 0.3s;
        }

        .card-course:hover {
            border-color: var(--gold);
        }

        .course-title {
            font-family: 'Syne', sans-serif;
            font-weight: 700;
            font-size: 1.25rem;
            margin-bottom: 15px;
        }

        .course-desc {
            color: var(--text-muted);
            font-size: 0.9rem;
            line-height: 1.6;
            margin-bottom: 25px;
            flex-grow: 1;
        }

        .btn-gold {
            background: var(--gold);
            color: black;
            font-weight: 700;
            border: none;
            padding: 12px;
            border-radius: 8px;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }
        
        .btn-gold:hover { background: #d9a62e; }
    </style>
</head>
<body>

    <aside class="sidebar">
        <a href="dashboard.php" class="sidebar-brand">WMSU DESCD</a>
        <nav>
            <a href="dashboard.php" class="nav-link">🏠 Dashboard</a>
            <a href="enroll.php" class="nav-link active">📝 Enroll Now</a>
            <a href="my-enrollments.php" class="nav-link">📄 My Enrollments</a>
            <a href="community-profile.php" class="nav-link">📊 Community Profile</a>
            <a href="settings.php" class="nav-link">⚙️ Profile Settings</a>
        </nav>
    </aside>

    <div class="main-wrapper">
        <header class="top-nav">
            <span style="font-size: 0.9rem; margin-right: 15px; color: var(--text-muted);">
                <?= htmlspecialchars($student['first_name'] ?? 'User') ?>
            </span>
            <a href="#" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#logoutConfirmModal" data-logout-url="../auth/logout.php">Logout</a>
        </header>

        <main class="content-area">
            <span class="portal-badge">✦ ENROLLMENT CENTER</span>
            <h1 class="page-title">Available Courses</h1>
            <p class="text-white mb-5">Browse and apply for our active extension service programs.</p>

            <?php if($message): ?>
                <div class="alert alert-info bg-dark text-white border-secondary py-3 mb-4">
                    <span class="text-gold fw-bold">Notice:</span> <?= $message ?>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <?php if (empty($courses)): ?>
                    <div class="col-12 text-center py-5">
                        <p class="text-muted">No courses are currently open for enrollment.</p>
                    </div>
                <?php else: ?>
                    <?php foreach($courses as $c): ?>
                    <div class="col-md-6">
                        <div class="card-course">
                            <div class="course-title"><?= htmlspecialchars($c['course_name']) ?></div>
                            <div class="course-desc">
                                <?= htmlspecialchars($c['description'] ?: 'Take the next step in your professional development with this specialized extension program.') ?>
                            </div>
                            <form method="POST" onsubmit="return confirm('Confirm application for <?= htmlspecialchars($c['course_name']) ?>?')">
                                <input type="hidden" name="course_id" value="<?= $c['course_id'] ?>">
                                <button type="submit" name="submit_enrollment" class="btn btn-gold w-100">Apply for Enrollment</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <div class="modal fade" id="logoutConfirmModal" tabindex="-1" aria-labelledby="logoutConfirmLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark text-white">
                <div class="modal-header border-0">
                    <h5 class="modal-title" id="logoutConfirmLabel">Confirm Logout</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to log out?
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-danger rounded-pill" data-bs-dismiss="modal">No</button>
                    <button type="button" id="logoutConfirmYes" class="btn btn-warning rounded-pill" data-logout-url="../auth/logout.php">Yes</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('logoutConfirmYes')?.addEventListener('click', function() {
            const logoutUrl = this.getAttribute('data-logout-url');
            if (logoutUrl) window.location.href = logoutUrl;
        });
    </script>
</body>
</html>
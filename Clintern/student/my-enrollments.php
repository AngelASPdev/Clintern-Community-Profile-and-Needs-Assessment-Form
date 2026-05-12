<?php
// ══════════════════════════════════════
//  WMSU OESCD — My Enrollments History
// ══════════════════════════════════════
require_once __DIR__ . '/../config/database.php';
session_start();

// Auth Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../auth/login.php?role=student');
    exit;
}

$user_id = $_SESSION['user_id'];

// 1. Fetch Student Details
$stmt = $pdo->prepare("SELECT student_id, first_name FROM students WHERE user_id = ?");
$stmt->execute([$user_id]);
$student = $stmt->fetch();

if (!$student) {
    die("Student profile not found.");
}

$student_id = $student['student_id'];

// 2. Fetch Complete Enrollment History
$stmt2 = $pdo->prepare("
    SELECT e.*, c.course_name 
    FROM enrollment_forms e
    JOIN courses c ON c.course_id = e.course_id
    WHERE e.student_id = ? 
    ORDER BY e.submitted_at DESC
");
$stmt2->execute([$student_id]);
$enrollments = $stmt2->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Enrollments | WMSU DESCD</title>
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

        /* History Table Styling matching Dashboard */
        .history-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 30px;
        }

        .custom-table {
            width: 100%;
            border-collapse: collapse;
        }

        .custom-table th {
            color: #475569;
            font-size: 0.75rem;
            text-transform: uppercase;
            padding-bottom: 20px;
            font-weight: 600;
        }

        .custom-table td {
            padding: 18px 0;
            border-bottom: 1px solid #1e2229;
            font-size: 0.9rem;
        }

        .status-badge {
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
        }
        
        .status-pending { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .status-approved { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .status-rejected { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
    </style>
</head>
<body>

    <aside class="sidebar">
        <a href="dashboard.php" class="sidebar-brand">WMSU DESCD</a>
        <nav>
            <a href="dashboard.php" class="nav-link">🏠 Dashboard</a>
            <a href="enroll.php" class="nav-link">📝 Enroll Now</a>
            <a href="my-enrollments.php" class="nav-link active">📄 My Enrollments</a>
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
            <span class="portal-badge">✦ ENROLLMENT RECORDS</span>
            <h1 class="page-title">My Enrollments</h1>
            <p class="text-white mb-5">View and track the status of all your course applications.</p>

            <div class="history-card">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>Course Name</th>
                            <th>Semester</th>
                            <th>OR Number</th>
                            <th>Date Submitted</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($enrollments)): ?>
                            <tr><td colspan="5" class="text-center py-5 text-white">You have not enrolled in any courses yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($enrollments as $e): ?>
                            <tr>
                                <td class="fw-semibold"><?= htmlspecialchars($e['course_name']) ?></td>
                                <td class="text-white"><?= htmlspecialchars($e['semester'] ?? 'N/A') ?></td>
                                <td style="color: var(--gold); font-family: monospace; font-weight: 600;">
                                    <?= htmlspecialchars($e['or_number'] ?? '---') ?>
                                </td>
                                <td class="text-white">
                                    <?= date('M d, Y', strtotime($e['submitted_at'])) ?>
                                </td>
                                <td>
                                    <?php 
                                        $statusClass = 'status-' . strtolower($e['status']);
                                    ?>
                                    <span class="status-badge <?= $statusClass ?>">
                                        <?= htmlspecialchars($e['status']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
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
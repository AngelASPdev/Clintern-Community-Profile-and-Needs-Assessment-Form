<?php
// ══════════════════════════════════════
//  WMSU OESCD — Student Dashboard (Visual Match)
// ══════════════════════════════════════
require_once __DIR__ . '/../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../auth/login.php?role=student');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch accurate counts
$stmt = $pdo->prepare("
    SELECT 
        s.*, 
        COUNT(e.enrollment_id) AS total_enrolled,
        SUM(CASE WHEN e.status = 'approved' THEN 1 ELSE 0 END) AS approved,
        SUM(CASE WHEN e.status = 'pending' THEN 1 ELSE 0 END) AS pending
    FROM students s
    LEFT JOIN enrollment_forms e ON e.student_id = s.student_id
    WHERE s.user_id = ?
    GROUP BY s.student_id
");
$stmt->execute([$user_id]);
$student = $stmt->fetch() ?: ['first_name' => $_SESSION['name'], 'total_enrolled' => 0, 'approved' => 0, 'pending' => 0];

// Recent History
$stmt2 = $pdo->prepare("
    SELECT e.*, c.course_name FROM enrollment_forms e
    JOIN courses c ON c.course_id = e.course_id
    JOIN students s ON s.student_id = e.student_id
    WHERE s.user_id = ? ORDER BY e.submitted_at DESC LIMIT 5
");
$stmt2->execute([$user_id]);
$recent = $stmt2->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard | WMSU OESCD</title>
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

        /* Sidebar Navigation */
        .sidebar {
            width: 240px;
            height: 100vh;
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border);
            padding: 20px;
            position: fixed;
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

        /* Main Layout */
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

        /* Dashboard Styles */
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

        h1.welcome-text {
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: 2.8rem;
            letter-spacing: -1px;
            margin-bottom: 10px;
        }

        .sub-text {
            color: #475569;
            font-size: 1rem;
            margin-bottom: 40px;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 30px;
            position: relative;
            overflow: hidden;
        }

        .stat-card::after {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 4px; height: 100%;
        }
        .stat-card.gold::after { background: var(--gold); }
        .stat-card.green::after { background: #10b981; }
        .stat-card.blue::after { background: #3b82f6; }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 5px;
            font-family: 'Syne', sans-serif;
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 0.8rem;
            text-transform: uppercase;
            font-weight: 600;
        }

        /* Action Buttons */
        .section-title {
            font-family: 'Syne', sans-serif;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 1px;
            color: var(--text-muted);
            margin-bottom: 20px;
        }

        .action-bar {
            background: var(--card-bg);
            border: 1px solid var(--border);
            padding: 25px;
            border-radius: 12px;
            display: flex;
            gap: 15px;
            margin-bottom: 40px;
        }

        .btn-action {
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
        }

        .btn-enroll { background: var(--gold); color: black; }
        .btn-profile { background: #3b82f6; color: white; }
        .btn-view { background: #1e293b; color: white; border: 1px solid var(--border); }

        /* Table */
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
            padding: 15px 0;
            border-bottom: 1px solid #1e2229;
            font-size: 0.9rem;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }
    </style>
</head>
<body>

    <aside class="sidebar">
        <a href="#" class="sidebar-brand">WMSU DESCD</a>
        <nav>
            <a href="dashboard.php" class="nav-link active">🏠 Dashboard</a>
            <a href="enroll.php" class="nav-link">📝 Enroll Now</a>
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
            <span class="portal-badge">✦ STUDENT PORTAL</span>
            <h1 class="welcome-text">Welcome back, <?= htmlspecialchars($student['first_name'] ?? 'Student') ?>! 👋</h1>
            <p class="sub-text">Here's your enrollment overview and recent activity.</p>

            <div class="stats-grid">
                <div class="stat-card gold">
                    <div class="stat-value"><?= number_format($student['total_enrolled']) ?></div>
                    <div class="stat-label">Total Enrollments</div>
                </div>
                <div class="stat-card green">
                    <div class="stat-value"><?= number_format($student['approved']) ?></div>
                    <div class="stat-label">Approved</div>
                </div>
                <div class="stat-card blue">
                    <div class="stat-value"><?= number_format($student['pending']) ?></div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>

            <h2 class="section-title">Quick Actions</h2>
            <div class="action-bar">
                <a href="enroll.php" class="btn-action btn-enroll">📋 Enroll in a Course</a>
                <a href="community-profile.php" class="btn-action btn-profile">📊 Submit Community Profile</a>
                <a href="my-enrollments.php" class="btn-action btn-view">📄 View My Enrollments</a>
            </div>

            <h2 class="section-title">Recent Enrollments</h2>
            <div class="history-card">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>Course</th>
                            <th>Semester</th>
                            <th>OR Number</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent)): ?>
                            <tr><td colspan="4" class="text-center py-4 text-muted">No records found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recent as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['course_name']) ?></td>
                                <td><?= htmlspecialchars($r['semester'] ?? '1st Sem 2024-2025') ?></td>
                                <td style="color: var(--gold); font-family: monospace;">
                                    <?= htmlspecialchars($r['or_number'] ?? 'PENDING') ?>
                                </td>
                                <td><span class="status-badge"><?= strtoupper($r['status']) ?></span></td>
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
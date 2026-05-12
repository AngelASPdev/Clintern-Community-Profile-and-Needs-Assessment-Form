<?php
// ══════════════════════════════════════
//  WMSU OESCD — Admin Dashboard (Fixed Visuals)
// ══════════════════════════════════════
require_once __DIR__ . '/../config/database.php';
session_start();

// Auth Check: Admin/Superadmin only
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header('Location: ../auth/login.php?role=admin');
    exit;
}

/**
 * 1. KPI FETCHING
 */
try {
    $kpis = $pdo->query("
        SELECT
          (SELECT COUNT(*) FROM students) AS total_students,
          (SELECT COUNT(*) FROM enrollment_forms) AS total_enrollments,
          (SELECT COUNT(*) FROM enrollment_forms WHERE status = 'pending') AS pending,
          (SELECT COUNT(*) FROM enrollment_forms WHERE status = 'approved') AS approved,
          (SELECT COUNT(*) FROM courses WHERE is_active = 1) AS total_courses,
          (SELECT COUNT(*) FROM community_profiles) AS total_profiles
    ")->fetch();
} catch (PDOException $e) {
    $kpis = array_fill_keys(['total_students','total_enrollments','pending','approved','total_courses','total_profiles'], 0);
}

/**
 * 2. RECENT ENROLLMENTS
 */
try {
    $recent = $pdo->query("
        SELECT e.*, s.first_name, s.surname, c.course_name 
        FROM enrollment_forms e
        INNER JOIN students s ON s.student_id = e.student_id
        INNER JOIN courses c ON c.course_id = e.course_id
        ORDER BY e.submitted_at DESC LIMIT 5
    ")->fetchAll();
} catch (PDOException $e) { $recent = []; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard | WMSU OESCD</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Syne:wght@700;800&display=swap" rel="stylesheet">
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

        body { background-color: var(--bg-body); color: white; font-family: 'Inter', sans-serif; display: flex; margin: 0; }

        /* Sidebar Navigation */
        .sidebar { width: 240px; height: 100vh; background: var(--sidebar-bg); border-right: 1px solid var(--border); padding: 20px; position: fixed; }
        .sidebar-brand { font-family: 'Syne', sans-serif; font-weight: 800; color: var(--gold); font-size: 1.1rem; text-decoration: none; display: block; margin-bottom: 40px; }
        .nav-link { color: var(--text-muted); padding: 10px 15px; border-radius: 6px; margin-bottom: 4px; display: flex; align-items: center; gap: 10px; text-decoration: none; font-size: 0.85rem; }
        .nav-link.active { background: rgba(241, 185, 51, 0.1); color: var(--gold); font-weight: 600; }
        .nav-link:hover:not(.active) { color: white; background: rgba(255, 255, 255, 0.05); }

        .main-wrapper { margin-left: 240px; width: calc(100% - 240px); }
        .top-nav { height: 60px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: flex-end; padding: 0 40px; }

        .content-area { padding: 40px; max-width: 1300px; }
        .portal-badge { display: inline-block; background: rgba(241, 185, 51, 0.1); color: var(--gold); padding: 3px 10px; border-radius: 4px; font-size: 10px; font-weight: 800; border-left: 3px solid var(--gold); margin-bottom: 15px; }
        
        h1.page-title { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 2.5rem; letter-spacing: -1px; margin-bottom: 10px; }
        .sub-text { color: var(--text-muted); font-size: 0.95rem; margin-bottom: 40px; }

        /* KPI Grid (6 Columns) */
        .kpi-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 15px; margin-bottom: 40px; }
        .kpi-card { background: var(--card-bg); border: 1px solid var(--border); padding: 20px; border-radius: 10px; position: relative; }
        .kpi-card::after { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 3px; border-radius: 10px 10px 0 0; }
        
        .kpi-card.purple::after { background: #8b5cf6; }
        .kpi-card.gold::after { background: var(--gold); }
        .kpi-card.blue::after { background: #3b82f6; }
        .kpi-card.green::after { background: #10b981; }
        .kpi-card.cyan::after { background: #06b6d4; }
        .kpi-card.pink::after { background: #ec4899; }

        .kpi-value { font-family: 'Syne', sans-serif; font-size: 2rem; font-weight: 800; }
        .kpi-label { font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; margin-top: 5px; }

        /* Quick Actions */
        .card-main { background: var(--card-bg); border: 1px solid var(--border); padding: 25px; border-radius: 12px; margin-bottom: 30px; }
        .section-header { font-family: 'Syne', sans-serif; text-transform: uppercase; font-size: 0.8rem; color: var(--text-muted); letter-spacing: 1px; margin-bottom: 20px; }
        
        .action-flex { display: flex; gap: 12px; flex-wrap: wrap; }
        .btn-act { padding: 10px 20px; border-radius: 8px; font-weight: 600; font-size: 0.85rem; text-decoration: none; display: flex; align-items: center; gap: 8px; border: none; }
        
        .btn-manage-e { background: #f1b933; color: black; }
        .btn-walkin { background: #22c55e; color: white; }
        .btn-manage-c { background: #3b82f6; color: white; }
        .btn-ghost { background: #1e293b; color: white; border: 1px solid var(--border); }

        /* Table */
        .custom-table { width: 100%; color: white; font-size: 0.85rem; }
        .custom-table th { color: var(--text-muted); text-transform: uppercase; font-size: 0.7rem; padding-bottom: 15px; border-bottom: 1px solid var(--border); }
        .custom-table td { padding: 15px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
        
        .status-rejected { color: #ef4444; background: rgba(239, 68, 68, 0.1); padding: 2px 10px; border-radius: 20px; border: 1px solid rgba(239, 68, 68, 0.2); font-size: 10px; font-weight: 700; }
    </style>
</head>
<body>

    <aside class="sidebar">
        <a href="#" class="sidebar-brand">WMSU <span style="font-weight:400; font-size:0.8rem;">ADMIN</span></a>
        <nav>
            <a href="dashboard.php" class="nav-link active">📊 Dashboard</a>
            <a href="enrollments.php" class="nav-link">📋 Enrollments</a>
            <a href="community.php" class="nav-link">👥 Community Profiles</a>
            <a href="courses.php" class="nav-link">📚 Courses</a>
            <a href="schedules.php" class="nav-link">📅 Schedules</a>
            <a href="students.php" class="nav-link">📄 Student Records</a>
            <a href="reports.php" class="nav-link">📉 Reports</a>
            <a href="users.php" class="nav-link">👥 User Management</a>
            <a href="audit.php" class="nav-link">📜 Audit Logs</a>
            <a href="#" class="nav-link text-danger" data-bs-toggle="modal" data-bs-target="#logoutModal">🚪 Logout</a>
        </nav>
    </aside>

    <div class="main-wrapper">
        <header class="top-nav">
            <div class="d-flex align-items-center bg-dark p-1 px-3 rounded-pill border border-secondary">
                <span class="me-2">🔒</span>
                <small class="me-3"><?= htmlspecialchars($_SESSION['name']) ?></small>
                <a href="#" class="btn btn-sm btn-dark py-0 text-danger" data-bs-toggle="modal" data-bs-target="#logoutModal">Logout</a>
            </div>
        </header>

        <main class="content-area">
            <div class="portal-badge">✦ ADMIN PORTAL</div>
            <h1 class="page-title">Admin Dashboard</h1>
            <p class="sub-text">Real-time overview of enrollments, courses, and community data.</p>

            <div class="kpi-grid">
                <div class="kpi-card purple">
                    <div class="kpi-value"><?= number_format($kpis['total_students']) ?></div>
                    <div class="kpi-label">Total Students</div>
                </div>
                <div class="kpi-card gold">
                    <div class="kpi-value"><?= number_format($kpis['total_enrollments']) ?></div>
                    <div class="kpi-label">Total Enrollments</div>
                </div>
                <div class="kpi-card blue">
                    <div class="kpi-value"><?= number_format($kpis['pending']) ?></div>
                    <div class="kpi-label">Pending Review</div>
                </div>
                <div class="kpi-card green">
                    <div class="kpi-value"><?= number_format($kpis['approved']) ?></div>
                    <div class="kpi-label">Approved</div>
                </div>
                <div class="kpi-card cyan">
                    <div class="kpi-value"><?= number_format($kpis['total_courses']) ?></div>
                    <div class="kpi-label">Active Courses</div>
                </div>
                <div class="kpi-card pink">
                    <div class="kpi-value"><?= number_format($kpis['total_profiles']) ?></div>
                    <div class="kpi-label">Community Profiles</div>
                </div>
            </div>

            <div class="card-main">
                <h2 class="section-header">Quick Actions</h2>
                <div class="action-flex">
                    <a href="enrollments.php" class="btn-act btn-manage-e">📋 Manage Enrollments</a>
                    <a href="courses.php" class="btn-act btn-manage-c">📚 Manage Courses</a>
                    <a href="students.php" class="btn-act btn-ghost">📄 Student Records</a>
                    <a href="reports.php" class="btn-act btn-ghost">📈 Reports</a>
                    <a href="audit.php" class="btn-act btn-ghost">📜 Audit Logs</a>
                </div>
            </div>

            <div class="card-main">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="section-header mb-0">Recent Enrollments</h2>
                    <a href="enrollments.php" style="color:var(--gold); font-size:0.75rem; text-decoration:none;">View all →</a>
                </div>
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Course</th>
                            <th>Semester</th>
                            <th>OR Number</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No records found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recent as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['first_name'] . ' ' . $r['surname']) ?></td>
                                <td><?= htmlspecialchars($r['course_name']) ?></td>
                                <td><?= htmlspecialchars($r['semester'] ?? '2nd Sem 2024-2025') ?></td>
                                <td style="color:var(--gold); font-family:monospace;"><?= htmlspecialchars($r['or_number'] ?? '—') ?></td>
                                <td><?= date('M d, Y', strtotime($r['submitted_at'])) ?></td>
                                <td><span class="status-rejected"><?= strtoupper($r['status']) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Logout Confirmation Modal -->
    <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content bg-dark text-white">
                <div class="modal-header">
                    <h5 class="modal-title" id="logoutModalLabel">Confirm Logout</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to logout?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="../auth/logout.php" class="btn btn-danger">Logout</a>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
<?php
// ══════════════════════════════════════
//  WMSU OESCD — Administrative Reports
// ══════════════════════════════════════
require_once __DIR__ . '/../config/database.php';
session_start();

// Auth Check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header('Location: ../auth/login.php?role=admin');
    exit;
}

/**
 * 1. FETCH SUMMARY STATISTICS
 */
// Total Enrollments
$total_stmt = $pdo->query("SELECT COUNT(*) FROM enrollment_forms");
$total_enrollments = $total_stmt->fetchColumn();

// Approved vs Pending vs Rejected
$status_stmt = $pdo->query("SELECT status, COUNT(*) as count FROM enrollment_forms GROUP BY status");
$status_data = $status_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Enrollment Method Breakdown
$method_stmt = $pdo->query("SELECT enrollment_method, COUNT(*) as count FROM enrollment_forms GROUP BY enrollment_method");
$method_data = $method_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

/**
 * 2. FETCH DETAILED TABLE DATA
 */
$query = "SELECT e.*, s.first_name, s.surname, c.course_name 
          FROM enrollment_forms e
          JOIN students s ON e.student_id = s.student_id
          JOIN courses c ON e.course_id = c.course_id
          ORDER BY e.submitted_at DESC";
$report_list = $pdo->query($query)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports & Analytics | WMSU DESCD</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Syne:wght@700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --bg: #0a0c10; --sidebar-bg: #111419; --card-bg: #14171c; --gold: #f1b933; --border: #1e2229; --text-muted: #64748b; }
        body { background: var(--bg); color: white; font-family: 'Inter', sans-serif; display: flex; margin: 0; }
        
        /* Sidebar */
        .sidebar { width: 240px; height: 100vh; background: var(--sidebar-bg); border-right: 1px solid var(--border); padding: 20px; position: fixed; }
        .sidebar-brand { font-family: 'Syne', sans-serif; font-weight: 800; color: var(--gold); font-size: 1.1rem; text-decoration: none; display: block; margin-bottom: 40px; }
        .nav-link { color: var(--text-muted); padding: 10px 15px; border-radius: 6px; margin-bottom: 4px; display: flex; align-items: center; gap: 10px; text-decoration: none; font-size: 0.85rem; }
        .nav-link.active { background: rgba(241, 185, 51, 0.1); color: var(--gold); font-weight: 600; }
        .nav-link:hover:not(.active) { color: white; background: rgba(255, 255, 255, 0.05); }

        .main-wrapper { margin-left: 240px; width: calc(100% - 240px); min-height: 100vh; }
        .content-area { padding: 40px; max-width: 1200px; }

        h1.page-title { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 2.2rem; margin-bottom: 30px; }
        
        /* Stat Cards */
        .stat-card { background: var(--card-bg); border: 1px solid var(--border); padding: 20px; border-radius: 12px; height: 100%; }
        .stat-label { color: var(--text-muted); font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 700; margin-bottom: 5px; }
        .stat-value { font-family: 'Syne', sans-serif; font-size: 1.8rem; font-weight: 800; color: #fff; }

        /* Report Table */
        .card-main { background: var(--card-bg); border: 1px solid var(--border); padding: 25px; border-radius: 12px; margin-top: 30px; }
        .custom-table { width: 100%; color: white; border-collapse: collapse; }
        .custom-table th { color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; padding: 15px; border-bottom: 1px solid var(--border); }
        .custom-table td { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.85rem; }

        .btn-print { background: var(--gold); color: black; font-weight: 700; border: none; padding: 10px 20px; border-radius: 8px; }

@media print {
            .sidebar, .btn-print, .print-hide { display: none !important; }
            .main-wrapper { margin-left: 0 !important; width: 100% !important; padding: 10px !important; }
            .card-main, body { background: white !important; color: black !important; border: none !important; box-shadow: none !important; }
            .custom-table th, .custom-table td { border-color: #ddd !important; color: black !important; }
            
            /* Fix table breaks - prevent content from being cut */
            table, .custom-table { page-break-inside: avoid !important; }
            tr, td, th { page-break-inside: avoid !important; }
            
            /* Reduce font sizes */
            h1.page-title { font-size: 1.5rem !important; }
            .stat-card { padding: 10px !important; }
            .stat-value { font-size: 1.2rem !important; }
        }
    </style>
</head>
<body>

    <aside class="sidebar">
        <a href="dashboard.php" class="sidebar-brand">WMSU <span style="font-weight:400; font-size:0.8rem;">ADMIN</span></a>
        <nav>
            <a href="dashboard.php" class="nav-link">📊 Dashboard</a>
            <a href="enrollments.php" class="nav-link">📋 Enrollments</a>
            <a href="community.php" class="nav-link">👥 Community Profiles</a>
            <a href="courses.php" class="nav-link">📚 Courses</a>
            <a href="schedules.php" class="nav-link">📅 Schedules</a>
            <a href="students.php" class="nav-link">📄 Student Records</a>
            <a href="reports.php" class="nav-link active">📉 Reports</a>
            <a href="users.php" class="nav-link">👥 User Management</a>
            <a href="audit.php" class="nav-link">📜 Audit Logs</a>
            <a href="../auth/logout.php" class="nav-link text-danger">🚪 Logout</a>
        </nav>
    </aside>

    <header class="top-nav">
        <div class="d-flex align-items-center bg-dark p-1 px-3 rounded-pill border border-secondary">
            <span class="me-2">🔒</span>
            <small class="me-3"><?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?></small>
            <a href="#" class="btn btn-sm btn-dark py-0 text-danger" data-bs-toggle="modal" data-bs-target="#logoutModal">Logout</a>
        </div>
    </header>

    <div class="main-wrapper">
        <main class="content-area">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="page-title m-0">System Reports</h1>
                <button onclick="window.print()" class="btn-print">🖨️ Export PDF / Print</button>
            </div>

            <div class="row g-4 mb-2">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-label">Total Applications</div>
                        <div class="stat-value"><?= $total_enrollments ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-label">Approved</div>
                        <div class="stat-value text-success"><?= $status_data['approved'] ?? 0 ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-label">Walk-in Enrollments</div>
                        <div class="stat-value text-info"><?= $method_data['walk-in'] ?? 0 ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-label">Online Apps</div>
                        <div class="stat-value" style="color: var(--gold);"><?= $method_data['online'] ?? 0 ?></div>
                    </div>
                </div>
            </div>

            <div class="card-main">
                <h5 class="mb-4 font-syne fw-bold">Enrollment Transaction Logs</h5>
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Course</th>
                            <th>Method</th>
                            <th>OR Number</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($report_list as $row): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($row['surname'] . ', ' . $row['first_name']) ?></td>
                            <td><?= htmlspecialchars($row['course_name']) ?></td>
                            <td class="text-uppercase small"><?= htmlspecialchars($row['enrollment_method']) ?></td>
                            <td style="color: var(--gold); font-family: monospace;"><?= htmlspecialchars($row['or_number'] ?? '---') ?></td>
                            <td class="text-white"><?= date('M d, Y', strtotime($row['submitted_at'])) ?></td>
                            <td>
                                <span class="badge text-uppercase" style="font-size: 0.65rem; letter-spacing: 1px; border: 1px solid currentColor;">
                                    <?= $row['status'] ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($report_list)): ?>
                            <tr><td colspan="6" class="text-center py-5 text-white">No report data found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
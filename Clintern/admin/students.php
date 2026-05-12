<?php
// ══════════════════════════════════════
//  WMSU OESCD — Student Records
// ══════════════════════════════════════
require_once __DIR__ . '/../config/database.php';
session_start();

// Auth Check - Admins only
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header('Location: ../auth/login.php?role=admin');
    exit;
}

/**
 * 1. FETCH ALL STUDENTS
 * Joined with users table to show account email
 */
$query = "SELECT s.*, u.email as account_email 
          FROM students s
          LEFT JOIN users u ON s.user_id = u.user_id 
          ORDER BY s.surname ASC";
$students = $pdo->query($query)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Records | WMSU DESCD</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Syne:wght@700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --bg: #0a0c10; --sidebar-bg: #111419; --card-bg: #14171c; --gold: #f1b933; --border: #1e2229; --text-muted: #64748b; }
        body { background: var(--bg); color: white; font-family: 'Inter', sans-serif; display: flex; margin: 0; }
        
        .sidebar { width: 240px; height: 100vh; background: var(--sidebar-bg); border-right: 1px solid var(--border); padding: 20px; position: fixed; }
        .sidebar-brand { font-family: 'Syne', sans-serif; font-weight: 800; color: var(--gold); font-size: 1.1rem; text-decoration: none; display: block; margin-bottom: 40px; }
        .nav-link { color: var(--text-muted); padding: 10px 15px; border-radius: 6px; margin-bottom: 4px; display: flex; align-items: center; gap: 10px; text-decoration: none; font-size: 0.85rem; }
        .nav-link.active { background: rgba(241, 185, 51, 0.1); color: var(--gold); font-weight: 600; }
        .nav-link:hover:not(.active) { color: white; background: rgba(255, 255, 255, 0.05); }

        .main-wrapper { margin-left: 240px; width: calc(100% - 240px); min-height: 100vh; }
        .content-area { padding: 40px; max-width: 1200px; }

        h1.page-title { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 2.2rem; margin-bottom: 30px; }
        .card-main { background: var(--card-bg); border: 1px solid var(--border); padding: 25px; border-radius: 12px; }

        .custom-table { width: 100%; color: white; border-collapse: collapse; }
        .custom-table th { color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; padding: 15px; border-bottom: 1px solid var(--border); }
        .custom-table td { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.85rem; }

        .student-id { font-family: monospace; color: var(--gold); font-weight: 600; }
        .search-bar { background: #0a0c10; border: 1px solid var(--border); color: white; border-radius: 8px; padding: 10px 20px; width: 300px; margin-bottom: 20px; }
        .search-bar:focus { outline: none; border-color: var(--gold); }
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
            <a href="students.php" class="nav-link active">📄 Student Records</a>
            <a href="reports.php" class="nav-link">📉 Reports</a>
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
            <h1 class="page-title">Student Directory</h1>
            <input type="text" id="studentSearch" class="search-bar" placeholder="Search by name or ID...">

            <div class="card-main">
                <table class="custom-table" id="studentTable">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Full Name</th>
                            <th>Email Address</th>
                            <th>Sex</th>
                            <th>Age</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($students as $s): ?>
                        <tr>
                            <td class="student-id"><?= htmlspecialchars($s['student_id']) ?></td>
                            <td class="fw-bold">
                                <?php 
                                    // FIXED LINE 97: Added null coalescing check for middle_name
                                    $full_name = htmlspecialchars($s['surname']) . ', ' . 
                                                 htmlspecialchars($s['first_name']) . ' ' . 
                                                 htmlspecialchars($s['middle_name'] ?? ''); 
                                    echo trim($full_name);
                                ?>
                            </td>
                            <td class="text-white"><?= htmlspecialchars($s['account_email'] ?? 'No Account') ?></td>
                            <td><?= htmlspecialchars($s['sex'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($s['age'] ?? 'N/A') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if(empty($students)): ?>
                            <tr><td colspan="5" class="text-center py-5 text-white">No student records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <script>
        document.getElementById('studentSearch').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('#studentTable tbody tr');
            rows.forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
            });
        });
    </script>
</body>
</html>
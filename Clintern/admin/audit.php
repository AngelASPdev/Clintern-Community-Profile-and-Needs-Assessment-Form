<?php
// ══════════════════════════════════════
//  WMSU OESCD — System Audit Logs
// ══════════════════════════════════════
require_once __DIR__ . '/../config/database.php';
session_start();

// Auth Check - Only Admins/Superadmins
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header('Location: ../auth/login.php?role=admin');
    exit;
}

$message = '';

/**
 * 1. HANDLE ACTIONS
 */
// Optional: Clear logs (Only for Superadmins)
if (isset($_POST['clear_logs']) && $_SESSION['role'] === 'superadmin') {
    $pdo->query("DELETE FROM audit_logs");
    $message = "Audit logs have been cleared.";
}

/**
 * 2. FETCH LOGS
 */
// Join with users to see who performed the action
$query = "SELECT al.*, u.name as user_fullname, u.role as user_role 
          FROM audit_logs al
          LEFT JOIN users u ON al.user_id = u.user_id 
          ORDER BY al.created_at DESC 
          LIMIT 500";
$logs = $pdo->query($query)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Audit Logs | WMSU OESCD</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Syne:wght@700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --bg: #0a0c10; --sidebar-bg: #111419; --card-bg: #14171c; --gold: #f1b933; --border: #1e2229; --text-muted: #64748b; }
        body { background: var(--bg); color: white; font-family: 'Inter', sans-serif; display: flex; margin: 0; }
        
        /* Sidebar - Consistent with Admin Side */
        .sidebar { width: 240px; height: 100vh; background: var(--sidebar-bg); border-right: 1px solid var(--border); padding: 20px; position: fixed; }
        .sidebar-brand { font-family: 'Syne', sans-serif; font-weight: 800; color: var(--gold); font-size: 1.1rem; text-decoration: none; display: block; margin-bottom: 40px; }
        .nav-link { color: var(--text-muted); padding: 10px 15px; border-radius: 6px; margin-bottom: 4px; display: flex; align-items: center; gap: 10px; text-decoration: none; font-size: 0.85rem; }
        .nav-link.active { background: rgba(241, 185, 51, 0.1); color: var(--gold); font-weight: 600; }
        .nav-link:hover:not(.active) { color: white; background: rgba(255, 255, 255, 0.05); }

        .main-wrapper { margin-left: 240px; width: calc(100% - 240px); min-height: 100vh; }
        .content-area { padding: 40px; max-width: 1200px; }

        h1.page-title { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 2.2rem; margin-bottom: 30px; }
        .card-main { background: var(--card-bg); border: 1px solid var(--border); padding: 25px; border-radius: 12px; }

        /* Table Styling */
        .custom-table { width: 100%; color: white; border-collapse: collapse; }
        .custom-table th { color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; padding: 15px; border-bottom: 1px solid var(--border); }
        .custom-table td { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.85rem; vertical-align: top; }

        /* Action Badges */
        .badge-action { font-size: 0.7rem; font-weight: 700; padding: 4px 8px; border-radius: 4px; text-transform: uppercase; }
        .bg-login { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .bg-create { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .bg-delete { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        .bg-update { background: rgba(241, 185, 51, 0.1); color: var(--gold); }

        .text-ip { font-family: monospace; color: var(--text-muted); }
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
            <a href="reports.php" class="nav-link">📉 Reports</a>
            <a href="users.php" class="nav-link">👥 User Management</a>
            <a href="audit.php" class="nav-link active">📜 Audit Logs</a>
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
                <h1 class="page-title m-0">System Audit Logs</h1>
                <?php if($_SESSION['role'] === 'superadmin'): ?>
                <form method="POST" onsubmit="return confirm('Permanently delete ALL audit logs?')">
                    <button type="submit" name="clear_logs" class="btn btn-outline-danger btn-sm">Clear All Logs</button>
                </form>
                <?php endif; ?>
            </div>

            <?php if($message): ?>
                <div class="alert alert-info bg-dark text-white border-secondary py-2 small mb-4"><?= $message ?></div>
            <?php endif; ?>

            <div class="card-main">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Target</th>
                            <th>Details</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($logs as $l): 
                            $actionType = strtolower(explode(' ', $l['action'])[0]);
                            $badgeClass = match($actionType) {
                                'login'  => 'bg-login',
                                'create' => 'bg-create',
                                'add'    => 'bg-create',
                                'delete' => 'bg-delete',
                                'remove' => 'bg-delete',
                                'update' => 'bg-update',
                                'edit'   => 'bg-update',
                                default  => 'bg-secondary'
                            };
                        ?>
                        <tr>
                            <td class="text-muted" style="white-space: nowrap;">
                                <?= date('M d, Y', strtotime($l['created_at'])) ?><br>
                                <small><?= date('H:i:s', strtotime($l['created_at'])) ?></small>
                            </td>
                            <td>
                                <span class="fw-bold d-block"><?= htmlspecialchars($l['user_fullname'] ?? 'System/Unknown') ?></span>
                                <small class="text-gold" style="font-size: 0.7rem; text-transform: uppercase;"><?= htmlspecialchars($l['user_role'] ?? 'N/A') ?></small>
                            </td>
                            <td>
                                <span class="badge-action <?= $badgeClass ?>"><?= htmlspecialchars($l['action']) ?></span>
                            </td>
                            <td class="fw-medium"><?= htmlspecialchars($l['target'] ?? '---') ?></td>
                            <td style="max-width: 300px; color: var(--text-muted); font-size: 0.8rem;">
                                <?= htmlspecialchars($l['details'] ?? 'No additional info.') ?>
                            </td>
                            <td class="text-ip"><?= htmlspecialchars($l['ip_address'] ?? '0.0.0.0') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if(empty($logs)): ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">No system activity recorded.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
// ══════════════════════════════════════════════════════════════════════════════
//  WMSU OESCD — Admin: Community Profile Management
// ══════════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../config/database.php';
session_start();

// Auth Check: Admin/Superadmin only
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header('Location: ../auth/login.php?role=admin');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_profile_id'])) {
    $delete_profile_id = (int) $_POST['delete_profile_id'];

    try {
        $pdo->beginTransaction();

        $stmt_work_ids = $pdo->prepare("SELECT work_id FROM work_experience WHERE profile_id = ?");
        $stmt_work_ids->execute([$delete_profile_id]);
        $work_ids = $stmt_work_ids->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($work_ids)) {
            $placeholders = implode(',', array_fill(0, count($work_ids), '?'));
            $stmt_delete_skills = $pdo->prepare("DELETE FROM skills_learned WHERE work_id IN ($placeholders)");
            $stmt_delete_skills->execute($work_ids);
        }

        $pdo->prepare("DELETE FROM work_experience WHERE profile_id = ?")->execute([$delete_profile_id]);
        $pdo->prepare("DELETE FROM family_health WHERE profile_id = ?")->execute([$delete_profile_id]);
        $pdo->prepare("DELETE FROM household_members WHERE profile_id = ?")->execute([$delete_profile_id]);
        $pdo->prepare("DELETE FROM household_profile WHERE profile_id = ?")->execute([$delete_profile_id]);
        $pdo->prepare("DELETE FROM community_profiles WHERE profile_id = ?")->execute([$delete_profile_id]);

        $pdo->commit();
        header('Location: community.php?deleted=1');
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $delete_error = "Failed to delete profile. " . $e->getMessage();
    }
}

// Fetch all community profiles with student info
try {
    $profiles = $pdo->query("
        SELECT cp.profile_id, cp.student_id, cp.first_name, cp.surname, cp.submission_status, cp.step_completed, cp.updated_at,
               s.email, CONCAT(s.first_name, ' ', s.surname) as student_full_name
        FROM community_profiles cp
        INNER JOIN students s ON s.student_id = cp.student_id
        ORDER BY cp.updated_at DESC
    ")->fetchAll();
} catch (Exception $e) {
    $profiles = [];
}

$total_profiles = count($profiles);
$submitted_count = count(array_filter($profiles, fn($p) => $p['submission_status'] === 'submitted'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Profiles | Admin | WMSU OESCD</title>
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
            --success: #10b981;
        }

        body {
            background-color: var(--bg-body);
            color: #ffffff;
            font-family: 'Inter', sans-serif;
        }

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
            font-size: 1.1rem;
            text-decoration: none;
            display: block;
            margin-bottom: 40px;
        }

        .nav-link {
            color: var(--text-muted);
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            font-size: 0.85rem;
            transition: all 0.3s;
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

        .main-wrapper {
            margin-left: 240px;
            width: calc(100% - 240px);
            min-height: 100vh;
        }

        .top-nav {
            height: 60px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding: 0 40px;
        }

        .content-area {
            padding: 40px;
            max-width: 1300px;
        }

        .portal-badge {
            display: inline-block;
            background: rgba(241, 185, 51, 0.1);
            color: var(--gold);
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 800;
            border-left: 3px solid var(--gold);
            margin-bottom: 15px;
        }

        h1.page-title {
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: 2.5rem;
            letter-spacing: -1px;
            margin-bottom: 10px;
        }

        .sub-text {
            color: var(--text-muted);
            font-size: 0.95rem;
            margin-bottom: 40px;
        }

        /* KPI Cards */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 40px;
        }

        .kpi-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            padding: 20px;
            border-radius: 10px;
            position: relative;
            transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
        }

        .kpi-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.35);
            border-color: rgba(241, 185, 51, 0.35);
        }

        .kpi-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            border-radius: 10px 10px 0 0;
        }

        .kpi-card.gold::after { background: var(--gold); }
        .kpi-card.success::after { background: var(--success); }
        .kpi-card.blue::after { background: #3b82f6; }

        .kpi-value {
            font-family: 'Syne', sans-serif;
            font-size: 2rem;
            font-weight: 800;
        }

        .kpi-label {
            font-size: 0.7rem;
            color: var(--text-muted);
            text-transform: uppercase;
            font-weight: 600;
            margin-top: 5px;
        }

        /* Table */
        .card-main {
            background: var(--card-bg);
            border: 1px solid var(--border);
            padding: 25px;
            border-radius: 12px;
        }

        .custom-table {
            width: 100%;
            border-collapse: collapse;
        }

        .custom-table th {
            background: rgba(241, 185, 51, 0.1);
            color: var(--gold);
            font-size: 0.7rem;
            text-transform: uppercase;
            font-weight: 600;
            padding: 15px;
            border: 1px solid var(--border);
            text-align: left;
        }

        .custom-table td {
            padding: 15px;
            border: 1px solid var(--border);
            font-size: 0.9rem;
        }

        .custom-table tr:hover {
            background: rgba(241, 185, 51, 0.03);
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-submitted {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .status-draft {
            background: rgba(100, 116, 139, 0.1);
            color: var(--text-muted);
        }

        .btn-custom {
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-view {
            background: var(--gold);
            color: #000;
        }

        .btn-view:hover {
            background: #e6a91f;
        }

        .btn-delete {
            background: transparent;
            color: #ef4444;
            border: 1px solid #ef4444;
        }

        .btn-delete:hover {
            background: rgba(239, 68, 68, 0.1);
        }

        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.6);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            width: 90%;
            max-width: 420px;
            padding: 22px;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 18px;
        }

        .btn-modal-yes {
            background: var(--gold);
            color: #000;
            border: none;
            border-radius: 8px;
            padding: 9px 16px;
            font-weight: 600;
        }

        .btn-modal-no {
            background: transparent;
            color: #ef4444;
            border: 1px solid #ef4444;
            border-radius: 8px;
            padding: 9px 16px;
            font-weight: 600;
        }

        .no-data {
            text-align: center;
            padding: 50px;
            color: var(--text-muted);
        }

        .no-data-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }

        @media (max-width: 768px) {
            .main-wrapper { margin-left: 0; width: 100%; }
            .sidebar { transform: translateX(-100%); }
            .kpi-grid { grid-template-columns: 1fr; }
            .content-area { padding: 20px; }
        }
    </style>
</head>
<body>

<?php
// Determine current page for active sidebar state
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- Sidebar -->
<aside class="sidebar">
        <a href="#" class="sidebar-brand">WMSU <span style="font-weight:400; font-size:0.8rem;">ADMIN</span></a>
        <nav>
            <a href="dashboard.php" class="nav-link<?php echo $current_page == 'dashboard.php' ? ' active' : ''; ?>">📊 Dashboard</a>
            <a href="enrollments.php" class="nav-link<?php echo $current_page == 'enrollments.php' ? ' active' : ''; ?>">📋 Enrollments</a>
            <a href="community.php" class="nav-link<?php echo in_array($current_page, ['community.php', 'community-view.php']) ? ' active' : ''; ?>">👥 Community Profiles</a>
            <a href="courses.php" class="nav-link<?php echo $current_page == 'courses.php' ? ' active' : ''; ?>">📚 Courses</a>
            <a href="schedules.php" class="nav-link<?php echo $current_page == 'schedules.php' ? ' active' : ''; ?>">📅 Schedules</a>
            <a href="students.php" class="nav-link<?php echo $current_page == 'students.php' ? ' active' : ''; ?>">📄 Student Records</a>
            <a href="reports.php" class="nav-link<?php echo $current_page == 'reports.php' ? ' active' : ''; ?>">📉 Reports</a>
            <a href="users.php" class="nav-link<?php echo $current_page == 'users.php' ? ' active' : ''; ?>">👥 User Management</a>
            <a href="audit.php" class="nav-link<?php echo $current_page == 'audit.php' ? ' active' : ''; ?>">📜 Audit Logs</a>
            <a href="#" class="nav-link text-danger" id="logoutTrigger">🚪 Logout</a>
        </nav>
    </aside>

    
<!-- Main Content -->
<div class="main-wrapper">
    <header class="top-nav">
        <span style="font-size: 0.9rem; color: var(--text-muted);">
            <strong style="color: var(--gold); letter-spacing: 0.3px;">WMSU DESCD</strong> — Admin Panel
        </span>
    </header>

    <main class="content-area">
        <span class="portal-badge">✦ COMMUNITY PROFILES</span>
        <h1 class="page-title">Community Profile Management</h1>
        <p class="sub-text">View and manage student-submitted community profile and needs assessment forms</p>

        <!-- KPI Cards -->
        <div class="kpi-grid">
            <div class="kpi-card gold">
                <div class="kpi-value"><?php echo $total_profiles; ?></div>
                <div class="kpi-label">Total Profiles</div>
            </div>
            <div class="kpi-card success">
                <div class="kpi-value"><?php echo $submitted_count; ?></div>
                <div class="kpi-label">Submitted</div>
            </div>
            <div class="kpi-card blue">
                <div class="kpi-value"><?php echo $total_profiles - $submitted_count; ?></div>
                <div class="kpi-label">In Progress</div>
            </div>
        </div>

        <!-- Profiles Table -->
        <div class="card-main">
            <h3 style="font-family: 'Syne', sans-serif; font-weight: 700; margin-bottom: 20px;">Student Profiles</h3>
            
            <?php if (count($profiles) > 0): ?>
                <div style="overflow-x: auto;">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Progress</th>
                                <th>Last Updated</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($profiles as $profile): ?>
                                <tr>
                                    <td style="font-weight: 600;">
                                        <?php echo htmlspecialchars($profile['first_name'] && $profile['surname'] ? $profile['surname'] . ', ' . $profile['first_name'] : $profile['student_full_name']); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($profile['email'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $profile['submission_status'] === 'submitted' ? 'submitted' : 'draft'; ?>">
                                            <?php echo ucfirst($profile['submission_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="color: var(--gold); font-weight: 600;">
                                            Step <?php echo htmlspecialchars($profile['step_completed']); ?>/4
                                        </span>
                                    </td>
                                    <td style="color: var(--text-muted); font-size: 0.85rem;">
                                        <?php echo htmlspecialchars(date('M d, Y', strtotime($profile['updated_at']))); ?>
                                    </td>
                                    <td style="display:flex; gap:8px; flex-wrap:wrap;">
                                        <a href="community-view.php?profile_id=<?php echo htmlspecialchars($profile['profile_id']); ?>" class="btn-custom btn-view">
                                            👁️ View
                                        </a>
                                        <button
                                            type="button"
                                            class="btn-custom btn-delete"
                                            onclick="openDeleteModal(<?php echo (int) $profile['profile_id']; ?>)">
                                            🗑️ Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <div class="no-data-icon">📋</div>
                    <p style="font-size: 1.1rem; margin-bottom: 5px;">No community profiles yet</p>
                    <p style="color: var(--text-muted); font-size: 0.9rem;">Students will submit their profiles as they progress through the form</p>
                </div>
            <?php endif; ?>
        </div>

    </main>
</div>

<div class="modal-overlay" id="deleteModal">
    <div class="modal-card">
        <div style="font-size:1rem;">Are you sure you want to delete it?</div>
        <form method="POST" id="deleteForm">
            <input type="hidden" name="delete_profile_id" id="deleteProfileId">
            <div class="modal-actions">
                <button type="submit" class="btn-modal-yes">Yes</button>
                <button type="button" class="btn-modal-no" onclick="closeDeleteModal()">No</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="logoutModal">
    <div class="modal-card">
        <div style="font-size:1rem;">Are you sure you want to logout?</div>
        <div class="modal-actions">
            <a href="../auth/logout.php" class="btn-modal-yes" style="text-decoration:none; display:inline-flex; align-items:center;">Yes</a>
            <button type="button" class="btn-modal-no" onclick="closeLogoutModal()">No</button>
        </div>
    </div>
</div>

<script>
function openDeleteModal(profileId) {
    document.getElementById('deleteProfileId').value = profileId;
    document.getElementById('deleteModal').style.display = 'flex';
}
function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}
function openLogoutModal() {
    document.getElementById('logoutModal').style.display = 'flex';
}
function closeLogoutModal() {
    document.getElementById('logoutModal').style.display = 'none';
}

var logoutTrigger = document.getElementById('logoutTrigger');
if (logoutTrigger) {
    logoutTrigger.addEventListener('click', function(e) {
        e.preventDefault();
        openLogoutModal();
    });
}

var deleteModal = document.getElementById('deleteModal');
if (deleteModal) {
    deleteModal.addEventListener('click', function(e) {
        if (e.target === this) closeDeleteModal();
    });
}

var logoutModal = document.getElementById('logoutModal');
if (logoutModal) {
    logoutModal.addEventListener('click', function(e) {
        if (e.target === this) closeLogoutModal();
    });
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// ══════════════════════════════════════
//  WMSU OESCD — Enrollment Management (CRUD)
// ══════════════════════════════════════
require_once __DIR__ . '/../config/database.php';
session_start();

// Auth Check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header('Location: ../auth/login.php?role=admin');
    exit;
}

$message = '';
$admin_id = $_SESSION['user_id'];

/**
 * 1. HANDLE CRUD ACTIONS
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // CREATE (Walk-in Enrollment)
    if (isset($_POST['add_enrollment'])) {
        $sid = $_POST['student_id'];
        $cid = $_POST['course_id'];
        $sem = trim($_POST['semester']);
        $or  = trim($_POST['or_number']);

        // Updated query to include semester column
        $stmt = $pdo->prepare("INSERT INTO enrollment_forms 
            (student_id, course_id, semester, or_number, status, enrollment_method, processed_by, processed_at) 
            VALUES (?, ?, ?, ?, 'approved', 'walk-in', ?, NOW())");
        
        if ($stmt->execute([$sid, $cid, $sem, $or, $admin_id])) {
            $message = "Walk-in enrollment created successfully!";
        }
    }

    // UPDATE (Decision & Status)
    if (isset($_POST['update_status'])) {
        $eid    = $_POST['enrollment_id'];
        $status = $_POST['status'];
        $or     = trim($_POST['or_number']);
        $sem    = trim($_POST['semester']);

        $stmt = $pdo->prepare("UPDATE enrollment_forms SET status = ?, or_number = ?, semester = ?, processed_by = ?, processed_at = NOW() WHERE enrollment_id = ?");
        $stmt->execute([$status, $or, $sem, $admin_id, $eid]);
        $message = "Enrollment record updated.";
    }

    // DELETE
    if (isset($_POST['delete_id'])) {
        $stmt = $pdo->prepare("DELETE FROM enrollment_forms WHERE enrollment_id = ?");
        $stmt->execute([$_POST['delete_id']]);
        $message = "Enrollment record removed.";
    }
}

/**
 * 2. FETCH DATA
 */
$enrollments = $pdo->query("SELECT e.*, s.first_name, s.surname, c.course_name, u.name as processor_name 
                            FROM enrollment_forms e
                            JOIN students s ON e.student_id = s.student_id
                            JOIN courses c ON e.course_id = c.course_id
                            LEFT JOIN users u ON e.processed_by = u.user_id
                            ORDER BY e.submitted_at DESC")->fetchAll();

$all_students = $pdo->query("SELECT student_id, first_name, surname FROM students ORDER BY surname ASC")->fetchAll();
$all_courses  = $pdo->query("SELECT course_id, course_name FROM courses WHERE is_active = 1")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Enrollments | WMSU OESCD</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Syne:wght@700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --bg: #0a0c10; --sidebar-bg: #111419; --card-bg: #14171c; --gold: #f1b933; --border: #1e2229; --text-muted: #64748b; }
        body { background: var(--bg); color: white; font-family: 'Inter', sans-serif; display: flex; margin: 0; }
        
        /* Sidebar - UNCHANGED FROM DASHBOARD */
        .sidebar { width: 240px; height: 100vh; background: var(--sidebar-bg); border-right: 1px solid var(--border); padding: 20px; position: fixed; }
        .sidebar-brand { font-family: 'Syne', sans-serif; font-weight: 800; color: var(--gold); font-size: 1.1rem; text-decoration: none; display: block; margin-bottom: 40px; }
        .nav-link { color: var(--text-muted); padding: 10px 15px; border-radius: 6px; margin-bottom: 4px; display: flex; align-items: center; gap: 10px; text-decoration: none; font-size: 0.85rem; }
        .nav-link.active { background: rgba(241, 185, 51, 0.1); color: var(--gold); font-weight: 600; }
        .nav-link:hover:not(.active) { color: white; background: rgba(255, 255, 255, 0.05); }

        .main-wrapper { margin-left: 240px; width: calc(100% - 240px); min-height: 100vh; }
        .content-area { padding: 40px; max-width: 1300px; }

        h1.page-title { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 2.2rem; margin-bottom: 30px; }
        .card-main { background: var(--card-bg); border: 1px solid var(--border); padding: 25px; border-radius: 12px; margin-bottom: 30px; }
        .btn-gold { background: var(--gold); color: black; font-weight: 700; border: none; }

        .custom-table { width: 100%; color: white; border-collapse: collapse; }
        .custom-table th { color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; padding: 15px; border-bottom: 1px solid var(--border); }
        .custom-table td { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.85rem; }

        .status-pill { padding: 4px 12px; border-radius: 20px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .status-pending { background: rgba(241, 185, 51, 0.1); color: var(--gold); }
        .status-approved { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .status-rejected { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

        /* Modal Visibility Fixes */
        .modal-content { background: #1a1d23; border: 1px solid var(--border); color: white; }
        .form-label { color: #ffffff !important; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-control, .form-select { background: #0a0c10; border: 1px solid var(--border); color: #ffffff !important; }
        .form-control:focus, .form-select:focus { background: #000; border-color: var(--gold); color: white; box-shadow: none; }
    </style>
</head>
<body>

    <aside class="sidebar">
        <a href="dashboard.php" class="sidebar-brand">WMSU <span style="font-weight:400; font-size:0.8rem;">ADMIN</span></a>
        <nav>
            <a href="dashboard.php" class="nav-link">📊 Dashboard</a>
            <a href="enrollments.php" class="nav-link active">📋 Enrollments</a>
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
                <h1 class="page-title m-0">Enrollment Management</h1>
                <button class="btn btn-gold px-4" data-bs-toggle="modal" data-bs-target="#addEnrollModal">+ New Walk-in</button>
            </div>

            <?php if($message): ?>
                <div class="alert alert-success bg-success text-white border-0 py-2 small mb-4"><?= $message ?></div>
            <?php endif; ?>

            <div class="card-main">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Course</th>
                            <th>Semester</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($enrollments as $e): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($e['surname'] . ', ' . $e['first_name']) ?></td>
                            <td><?= htmlspecialchars($e['course_name']) ?></td>
                            <td class="text-white"><?= htmlspecialchars($e['semester'] ?: '—') ?></td>
                            <td><span class="status-pill status-<?= $e['status'] ?>"><?= $e['status'] ?></span></td>
                            <td>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-sm btn-outline-light" onclick='editEnroll(<?= json_encode($e) ?>)'>Edit</button>
                                    <form method="POST" onsubmit="return confirm('Delete this record?')">
                                        <input type="hidden" name="delete_id" value="<?= $e['enrollment_id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <div class="modal fade" id="addEnrollModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title font-syne">New Walk-in Enrollment</h5>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select Student</label>
                        <select name="student_id" class="form-select" required>
                            <?php foreach($all_students as $as): ?>
                                <option value="<?= $as['student_id'] ?>"><?= $as['surname'] ?>, <?= $as['first_name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Select Course</label>
                        <select name="course_id" class="form-select" required>
                            <?php foreach($all_courses as $ac): ?>
                                <option value="<?= $ac['course_id'] ?>"><?= $ac['course_name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Semester</label>
                            <input type="text" name="semester" class="form-control" value="2nd Sem 2024-2025" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">OR Number</label>
                            <input type="text" name="or_number" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_enrollment" class="btn btn-gold px-4">Create Enrollment</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="editEnrollModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content">
                <input type="hidden" name="enrollment_id" id="edit_eid">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title font-syne">Update Enrollment</h5>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Semester</label>
                        <input type="text" name="semester" id="edit_sem" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">OR Number</label>
                        <input type="text" name="or_number" id="edit_or" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Decision</label>
                        <select name="status" id="edit_status" class="form-select">
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_status" class="btn btn-gold px-4">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const editModal = new bootstrap.Modal(document.getElementById('editEnrollModal'));
        function editEnroll(e) {
            document.getElementById('edit_eid').value = e.enrollment_id;
            document.getElementById('edit_sem').value = e.semester || '2nd Sem 2024-2025';
            document.getElementById('edit_or').value = e.or_number || '';
            document.getElementById('edit_status').value = e.status;
            editModal.show();
        }
    </script>

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
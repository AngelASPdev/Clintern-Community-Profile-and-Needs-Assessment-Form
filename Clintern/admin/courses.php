<?php
// ══════════════════════════════════════
//  WMSU OESCD — Course Management
// ══════════════════════════════════════
require_once __DIR__ . '/../config/database.php';
session_start();

// Auth Check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header('Location: ../auth/login.php?role=admin');
    exit;
}

$message = '';

/**
 * HANDLE CRUD ACTIONS
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ADD COURSE
    if (isset($_POST['add_course'])) {
        $name = trim($_POST['course_name']);
        $desc = trim($_POST['description']);
        $cap  = (int)$_POST['max_capacity'];
        
        $stmt = $pdo->prepare("INSERT INTO courses (course_name, description, max_capacity, is_active) VALUES (?, ?, ?, 1)");
        $stmt->execute([$name, $desc, $cap]);
        $message = "Course added successfully!";
    }

    // UPDATE COURSE
    if (isset($_POST['update_course'])) {
        $id   = $_POST['course_id'];
        $name = trim($_POST['course_name']);
        $desc = trim($_POST['description']);
        $cap  = (int)$_POST['max_capacity'];
        $status = isset($_POST['is_active']) ? 1 : 0;

        $stmt = $pdo->prepare("UPDATE courses SET course_name = ?, description = ?, max_capacity = ?, is_active = ? WHERE course_id = ?");
        $stmt->execute([$name, $desc, $cap, $status, $id]);
        $message = "Course updated successfully!";
    }

    // DELETE COURSE
    if (isset($_POST['delete_id'])) {
        $id = $_POST['delete_id'];
        $stmt = $pdo->prepare("DELETE FROM courses WHERE course_id = ?");
        $stmt->execute([$id]);
        $message = "Course deleted.";
    }
}

/**
 * FETCH ALL COURSES
 */
$courses = $pdo->query("SELECT * FROM courses ORDER BY course_id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Courses | WMSU DESCD</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Syne:wght@700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --bg: #0a0c10; --sidebar-bg: #111419; --card-bg: #14171c; --gold: #f1b933; --border: #1e2229; --text-muted: #64748b; }
        body { background: var(--bg); color: white; font-family: 'Inter', sans-serif; display: flex; margin: 0; }
        
        /* Sidebar Navigation - UNCHANGED */
        .sidebar { width: 240px; height: 100vh; background: var(--sidebar-bg); border-right: 1px solid var(--border); padding: 20px; position: fixed; }
        .sidebar-brand { font-family: 'Syne', sans-serif; font-weight: 800; color: var(--gold); font-size: 1.1rem; text-decoration: none; display: block; margin-bottom: 40px; }
        .nav-link { color: var(--text-muted); padding: 10px 15px; border-radius: 6px; margin-bottom: 4px; display: flex; align-items: center; gap: 10px; text-decoration: none; font-size: 0.85rem; }
        .nav-link.active { background: rgba(241, 185, 51, 0.1); color: var(--gold); font-weight: 600; }
        .nav-link:hover:not(.active) { color: white; background: rgba(255, 255, 255, 0.05); }

        .main-wrapper { margin-left: 240px; width: calc(100% - 240px); min-height: 100vh; }
        .content-area { padding: 40px; max-width: 1200px; }

        h1.page-title { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 2.2rem; margin-bottom: 30px; }
        
        .card-main { background: var(--card-bg); border: 1px solid var(--border); padding: 25px; border-radius: 12px; margin-bottom: 30px; }
        .btn-gold { background: var(--gold); color: black; font-weight: 700; border: none; }

        /* Table */
        .custom-table { width: 100%; color: white; border-collapse: collapse; }
        .custom-table th { color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; padding: 15px; border-bottom: 1px solid var(--border); }
        .custom-table td { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.9rem; }
        
        /* Modal - ENHANCED VISIBILITY */
        .modal-content { background: #1a1d23; border: 1px solid var(--border); color: white; }
        .modal-header { border-bottom: 1px solid var(--border); }
        .modal-title { font-family: 'Syne', sans-serif; font-weight: 700; color: white; }
        
        .form-label { color: #ffffff !important; font-weight: 500; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-control { background: #0a0c10; border: 1px solid var(--border); color: #ffffff !important; }
        .form-control::placeholder { color: #4b5563; }
        .form-control:focus { background: #000000; border-color: var(--gold); color: white; box-shadow: none; }
        
        .badge-active { color: #10b981; background: rgba(16, 185, 129, 0.1); padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; }
        .badge-inactive { color: #ef4444; background: rgba(239, 68, 68, 0.1); padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; }
    </style>
</head>
<body>

    <aside class="sidebar">
        <a href="dashboard.php" class="sidebar-brand">WMSU <span style="font-weight:400; font-size:0.8rem;">ADMIN</span></a>
        <nav>
            <a href="dashboard.php" class="nav-link">📊 Dashboard</a>
            <a href="enrollments.php" class="nav-link">📋 Enrollments</a>
            <a href="community.php" class="nav-link">👥 Community Profiles</a>
            <a href="courses.php" class="nav-link active">📚 Courses</a>
            <a href="schedules.php" class="nav-link">📅 Schedules</a>
            <a href="students.php" class="nav-link">📄 Student Records</a>
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="page-title m-0">Course Management</h1>
                <button class="btn btn-gold px-4" data-bs-toggle="modal" data-bs-target="#addModal">+ Add New Course</button>
            </div>

            <?php if($message): ?>
                <div class="alert alert-success bg-success text-white border-0 py-2 small mb-4"><?= $message ?></div>
            <?php endif; ?>

            <div class="card-main">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>Course Name</th>
                            <th>Description</th>
                            <th>Capacity</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($courses as $c): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($c['course_name']) ?></td>
                            <td class="text-muted small" style="max-width: 250px;"><?= htmlspecialchars($c['description']) ?></td>
                            <td><?= $c['max_capacity'] ?></td>
                            <td>
                                <span class="<?= $c['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
                                    <?= $c['is_active'] ? 'Active' : 'Archived' ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-sm btn-outline-light" onclick='editCourse(<?= json_encode($c) ?>)'>Edit</button>
                                    <form method="POST" onsubmit="return confirm('Permanently delete this course?')">
                                        <input type="hidden" name="delete_id" value="<?= $c['course_id'] ?>">
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

    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Course</h5>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Course Name</label>
                        <input type="text" name="course_name" class="form-control" placeholder="e.g. Organic Farming" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Brief course overview..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Max Capacity</label>
                        <input type="number" name="max_capacity" class="form-control" value="50" required>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_course" class="btn btn-gold px-4">Create Course</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content">
                <input type="hidden" name="course_id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Course Details</h5>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Course Name</label>
                        <input type="text" name="course_name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="edit_desc" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Max Capacity</label>
                        <input type="number" name="max_capacity" id="edit_cap" class="form-control" required>
                    </div>
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" name="is_active" id="edit_active" checked>
                        <label class="form-label ms-2" style="text-transform: none;">Active / Enrollment Open</label>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_course" class="btn btn-gold px-4">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const editModal = new bootstrap.Modal(document.getElementById('editModal'));
        function editCourse(c) {
            document.getElementById('edit_id').value = c.course_id;
            document.getElementById('edit_name').value = c.course_name;
            document.getElementById('edit_desc').value = c.description;
            document.getElementById('edit_cap').value = c.max_capacity;
            document.getElementById('edit_active').checked = (c.is_active == 1);
            editModal.show();
        }
    </script>
</body>
</html>
<?php
// ══════════════════════════════════════
//  WMSU OESCD — Course Scheduling
// ══════════════════════════════════════
require_once __DIR__ . '/../config/database.php';
session_start();

// Auth Check - Admins only
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header('Location: ../auth/login.php?role=admin');
    exit;
}

$message = '';

/**
 * Ensure schedules table exists in the database.
 */
function ensureSchedulesTableExists(PDO $pdo)
{
    try {
        $pdo->query('SELECT 1 FROM schedules LIMIT 1');
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), '1146') !== false) {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS schedules (
                    schedule_id INT(11) NOT NULL AUTO_INCREMENT,
                    course_id INT(11) NOT NULL,
                    semester VARCHAR(100) NOT NULL,
                    days VARCHAR(50) NOT NULL,
                    time_slot VARCHAR(100) NOT NULL,
                    created_at TIMESTAMP NULL DEFAULT current_timestamp(),
                    PRIMARY KEY (schedule_id),
                    KEY fk_course_schedule (course_id),
                    CONSTRAINT fk_course_schedule FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
            );
        } else {
            throw $e;
        }
    }
}

/**
 * 1. HANDLE FORM SUBMISSIONS
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ADD NEW SCHEDULE
    if (isset($_POST['add_schedule'])) {
        $course_id = $_POST['course_id'];
        $semester  = $_POST['semester'];
        $days      = $_POST['days']; // e.g., M-W-F
        $time      = $_POST['time_slot']; // e.g., 8:00 AM - 10:00 AM

        $stmt = $pdo->prepare("INSERT INTO schedules (course_id, semester, days, time_slot) VALUES (?, ?, ?, ?)");
        $stmt->execute([$course_id, $semester, $days, $time]);
        $message = "Schedule added successfully!";
    }

    // DELETE SCHEDULE
    if (isset($_POST['delete_schedule_id'])) {
        $stmt = $pdo->prepare("DELETE FROM schedules WHERE schedule_id = ?");
        $stmt->execute([$_POST['delete_schedule_id']]);
        $message = "Schedule removed.";
    }
}

/**
 * 2. FETCH DATA
 */
try {
    ensureSchedulesTableExists($pdo);

    // Fetch schedules joined with courses
    $schedules = $pdo->query(
        "SELECT sc.*, c.course_name, c.course_code 
         FROM schedules sc 
         JOIN courses c ON sc.course_id = c.course_id 
         ORDER BY c.course_name ASC, sc.semester DESC"
    )->fetchAll();

    // Fetch courses for the dropdown
    $courses = $pdo->query("SELECT course_id, course_name FROM courses ORDER BY course_name")->fetchAll();
} catch (PDOException $e) {
    $schedules = [];
    $courses = [];
    $message = "Schedule page is unavailable until the database schema is fixed.";
    error_log('Schedules page error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Schedules | WMSU OESCD</title>
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

        .main-wrapper { margin-left: 240px; width: calc(100% - 240px); min-height: 100vh; }
        .content-area { padding: 40px; max-width: 1200px; }

        h1.page-title { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 2.2rem; margin-bottom: 30px; }
        .card-main { background: var(--card-bg); border: 1px solid var(--border); padding: 25px; border-radius: 12px; }

        .custom-table { width: 100%; color: white; border-collapse: collapse; }
        .custom-table th { color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; padding: 15px; border-bottom: 1px solid var(--border); }
        .custom-table td { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.85rem; }

        .btn-gold { background: var(--gold); color: black; font-weight: 700; border: none; }
        .modal-content { background: #1a1d23; border: 1px solid var(--border); color: white; }
        .form-control, .form-select { background: #0a0c10; border: 1px solid var(--border); color: white; }
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
            <a href="schedules.php" class="nav-link active">📅 Schedules</a>
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
                <h1 class="page-title m-0">Course Schedules</h1>
                <button class="btn btn-gold px-4" data-bs-toggle="modal" data-bs-target="#addSchedModal">+ New Schedule</button>
            </div>

            <?php if($message): ?>
                <div class="alert alert-info bg-dark text-white border-secondary small mb-4"><?= $message ?></div>
            <?php endif; ?>

            <div class="card-main">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>Course</th>
                            <th>Semester</th>
                            <th>Days</th>
                            <th>Time Slot</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($schedules as $s): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($s['course_name']) ?> <br> <small class="text-white"><?= htmlspecialchars($s['course_code']) ?></small></td>
                            <td><?= htmlspecialchars($s['semester']) ?></td>
                            <td><span class="badge bg-dark text-gold border border-warning"><?= htmlspecialchars($s['days']) ?></span></td>
                            <td><?= htmlspecialchars($s['time_slot']) ?></td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Delete this schedule?')">
                                    <input type="hidden" name="delete_schedule_id" value="<?= $s['schedule_id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger">Remove</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <div class="modal fade" id="addSchedModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content">
                <div class="modal-header border-secondary"><h5 class="modal-title font-syne">Add Schedule Slot</h5></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-white small uppercase">Select Course</label>
                        <select name="course_id" class="form-select" required>
                            <?php foreach($courses as $c): ?>
                                <option value="<?= $c['course_id'] ?>"><?= htmlspecialchars($c['course_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-white small uppercase">Semester</label>
                        <select name="semester" class="form-select">
                            <option value="1st Sem 2024-2025">1st Sem 2024-2025</option>
                            <option value="2nd Sem 2024-2025">2nd Sem 2024-2025</option>
                            <option value="Summer 2025">Summer 2025</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-white small uppercase">Days (e.g., M-W-F, T-TH, SAT)</label>
                        <input type="text" name="days" class="form-control" placeholder="M-W-F" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-white small uppercase">Time Slot</label>
                        <input type="text" name="time_slot" class="form-control" placeholder="8:00 AM - 12:00 PM" required>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" name="add_schedule" class="btn btn-gold w-100">Save Schedule</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
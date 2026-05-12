<?php
// ══════════════════════════════════════
//  WMSU OESCD — User Management
// ══════════════════════════════════════
require_once __DIR__ . '/../config/database.php';
session_start();

// Auth Check - Only Admins/Superadmins
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header('Location: ../auth/login.php?role=admin');
    exit;
}

$message = '';
$current_admin_id = $_SESSION['user_id'];

/**
 * 1. HANDLE CRUD ACTIONS
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // CREATE USER
    if (isset($_POST['add_user'])) {
        $name  = $_POST['name'];
        $email = $_POST['email'];
        $role  = $_POST['role'];
        $pass  = password_hash($_POST['password'], PASSWORD_DEFAULT);

        try {
            $stmt = $pdo->prepare("INSERT INTO users (name, email, role, password_hash) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $role, $pass]);
            $message = "User account created successfully!";
        } catch (PDOException $e) {
            $message = "Error: Email might already be in use.";
        }
    }

    // UPDATE USER
    if (isset($_POST['update_user'])) {
        $uid   = $_POST['user_id'];
        $name  = $_POST['name'];
        $email = $_POST['email'];
        $role  = $_POST['role'];

        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ? WHERE user_id = ?");
        $stmt->execute([$name, $email, $role, $uid]);
        $message = "User updated successfully!";
    }

    // DELETE USER
    if (isset($_POST['delete_user_id'])) {
        $del_id = $_POST['delete_user_id'];
        if ($del_id == $current_admin_id) {
            $message = "Error: You cannot delete your own account.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->execute([$del_id]);
            $message = "User account removed.";
        }
    }
}

/**
 * 2. FETCH DATA
 */
// The order matches the sidebar priority
$users = $pdo->query("SELECT * FROM users ORDER BY FIELD(role, 'superadmin', 'admin', 'student'), name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Management | WMSU OESCD</title>
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

        /* Main Content area */
        .main-wrapper { margin-left: 240px; width: calc(100% - 240px); min-height: 100vh; }
        .content-area { padding: 40px; max-width: 1200px; }

        h1.page-title { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 2.2rem; margin-bottom: 30px; }
        .card-main { background: var(--card-bg); border: 1px solid var(--border); padding: 25px; border-radius: 12px; }
        
        .btn-gold { background: var(--gold); color: black; font-weight: 700; border: none; }
        .btn-gold:hover { background: #d9a62e; color: black; }

        .custom-table { width: 100%; color: white; border-collapse: collapse; }
        .custom-table th { color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; padding: 15px; border-bottom: 1px solid var(--border); }
        .custom-table td { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.9rem; }

        /* Role Badges */
        .role-badge { font-size: 0.7rem; font-weight: 800; padding: 4px 10px; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
        .role-superadmin { background: rgba(241, 185, 51, 0.1); color: var(--gold); border: 1px solid var(--gold); }
        .role-admin { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .role-student { background: rgba(16, 185, 129, 0.1); color: #10b981; }

        /* Modal Styles */
        .modal-content { background: #1a1d23; border: 1px solid var(--border); color: white; }
        .form-label { color: #fff; font-weight: 600; font-size: 0.8rem; text-transform: uppercase; }
        .form-control, .form-select { background: #0a0c10; border: 1px solid var(--border); color: white; }
        .form-control:focus, .form-select:focus { background: #000; border-color: var(--gold); color: white; box-shadow: none; }
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
            <a href="users.php" class="nav-link active">👥 User Management</a>
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
                <h1 class="page-title m-0">User Accounts</h1>
                <button class="btn btn-gold px-4" data-bs-toggle="modal" data-bs-target="#addUserModal">+ Add User</button>
            </div>

            <?php if($message): ?>
                <div class="alert alert-info bg-dark text-white border-secondary py-2 small mb-4"><?= $message ?></div>
            <?php endif; ?>

            <div class="card-main">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>User Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Joined Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $u): ?>
                        <tr>
                            <td class="fw-bold">
                                <?= htmlspecialchars($u['name']) ?>
                                <?php if($u['user_id'] == $current_admin_id): ?>
                                    <small class="text-gold ms-1">(You)</small>
                                <?php endif; ?>
                            </td>
                            <td class="text-white"><?= htmlspecialchars($u['email']) ?></td>
                            <td>
                                <span class="role-badge role-<?= strtolower($u['role']) ?>">
                                    <?= $u['role'] ?>
                                </span>
                            </td>
                            <td class="text-white small">
                                <?php 
                                    if (isset($u['created_at']) && !empty($u['created_at'])) {
                                        echo date('M d, Y', strtotime($u['created_at']));
                                    } else {
                                        echo "N/A";
                                    }
                                ?>
                            </td>
                            <td>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-sm btn-outline-light" onclick='editUser(<?= json_encode($u) ?>)'>Edit</button>
                                    <?php if($u['user_id'] != $current_admin_id): ?>
                                    <form method="POST" onsubmit="return confirm('Delete this user account permanently?')">
                                        <input type="hidden" name="delete_user_id" value="<?= $u['user_id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content">
                <div class="modal-header border-secondary"><h5 class="modal-title font-syne">New User Account</h5></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Full Name</label><input type="text" name="name" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select">
                            <option value="student">Student</option>
                            <option value="admin">Admin</option>
                            <option value="superadmin">Superadmin</option>
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label">Temporary Password</label><input type="password" name="password" class="form-control" required></div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_user" class="btn btn-gold">Create Account</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content">
                <input type="hidden" name="user_id" id="edit_uid">
                <div class="modal-header border-secondary"><h5 class="modal-title font-syne">Edit Profile</h5></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Full Name</label><input type="text" name="name" id="edit_name" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" id="edit_email" class="form-control" required></div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" id="edit_role" class="form-select">
                            <option value="student">Student</option>
                            <option value="admin">Admin</option>
                            <option value="superadmin">Superadmin</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_user" class="btn btn-gold">Update Account</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const editUserModal = new bootstrap.Modal(document.getElementById('editUserModal'));
        function editUser(u) {
            document.getElementById('edit_uid').value = u.user_id;
            document.getElementById('edit_name').value = u.name;
            document.getElementById('edit_email').value = u.email;
            document.getElementById('edit_role').value = u.role;
            editUserModal.show();
        }
    </script>
</body>
</html>
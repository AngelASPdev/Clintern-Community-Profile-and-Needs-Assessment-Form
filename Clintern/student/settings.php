<?php
// ══════════════════════════════════════
//  WMSU OESCD — Student Account Settings
// ══════════════════════════════════════
require_once __DIR__ . '/../config/database.php';
session_start();

// Auth Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../auth/login.php?role=student');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = 'info';

/**
 * 1. HANDLE PROFILE UPDATES
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $new_name = $_POST['name'];
    $new_email = $_POST['email'];
    $new_password = $_POST['password'];

    try {
        $pdo->beginTransaction();

        // Update Users Table
        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, password_hash = ? WHERE user_id = ?");
            $stmt->execute([$new_name, $new_email, $hashed_password, $user_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE user_id = ?");
            $stmt->execute([$new_name, $new_email, $user_id]);
        }

        // Sync with Students Table
        $stmt_student = $pdo->prepare("UPDATE students SET email = ? WHERE user_id = ?");
        $stmt_student->execute([$new_email, $user_id]);

        $pdo->commit();
        $_SESSION['name'] = $new_name; // Update session name
        $message = "Account settings updated successfully!";
        $message_type = "success";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Error updating settings: " . $e->getMessage();
        $message_type = "danger";
    }
}

/**
 * 2. FETCH CURRENT DATA
 */
$stmt = $pdo->prepare("SELECT name, email FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Account Settings | WMSU OESCD</title>
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

        /* Sidebar - Consistent with Dashboard */
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

        .main-wrapper { margin-left: 240px; width: calc(100% - 240px); }

        .top-nav {
            height: 70px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding: 0 40px;
        }

        .content-area { padding: 40px; max-width: 800px; }

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

        h1.page-title {
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: 2.8rem;
            letter-spacing: -1px;
            margin-bottom: 10px;
        }

        .settings-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 40px;
        }

        .form-label {
            color: var(--text-muted);
            font-size: 0.75rem;
            text-transform: uppercase;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .form-control {
            background: #0a0c10;
            border: 1px solid var(--border);
            color: white;
            padding: 12px;
            border-radius: 8px;
        }

        .form-control:focus {
            background: #000;
            border-color: var(--gold);
            color: white;
            box-shadow: none;
        }

        .btn-gold {
            background: var(--gold);
            color: black;
            font-weight: 700;
            border: none;
            padding: 14px;
            border-radius: 8px;
            text-transform: uppercase;
            font-size: 0.9rem;
            width: 100%;
            margin-top: 20px;
        }
    </style>
</head>
<body>

    <aside class="sidebar">
        <a href="dashboard.php" class="sidebar-brand">WMSU OESCD</a>
        <nav>
            <a href="dashboard.php" class="nav-link">🏠 Dashboard</a>
            <a href="enroll.php" class="nav-link">📝 Enroll Now</a>
            <a href="my-enrollments.php" class="nav-link">📄 My Enrollments</a>
            <a href="community-profile.php" class="nav-link">📊 Community Profile</a>
            <a href="settings.php" class="nav-link active">⚙️ Profile Settings</a>
        </nav>
    </aside>

    <div class="main-wrapper">
        <header class="top-nav">
            <span style="font-size: 0.9rem; color: var(--text-white); margin-right: 20px;">
                <?= htmlspecialchars($user['name']) ?>
            </span>
            <a href="#" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#logoutConfirmModal" data-logout-url="../auth/logout.php">Logout</a>
        </header>

        <main class="content-area">
            <span class="portal-badge">✦ PREFERENCES</span>
            <h1 class="page-title">Account Settings</h1>
            <p class="text-white mb-5">Manage your personal information and security credentials.</p>

            <?php if($message): ?>
                <div class="alert alert-<?= $message_type ?> bg-dark text-white border-secondary mb-4">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <div class="settings-card">
                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>

                    <hr style="border-color: var(--border); margin: 30px 0;">

                    <div class="mb-4">
                        <label class="form-label">New Password (Leave blank to keep current)</label>
                        <input type="password" name="password" class="form-control" placeholder="••••••••">
                    </div>

                    <button type="submit" name="update_settings" class="btn btn-gold">
                        Save Changes
                    </button>
                </form>
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
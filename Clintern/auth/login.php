<?php
// ══════════════════════════════════════════════════════════════════════════
//  WMSU OESCD — Unified Login Portal (Secured)
// ══════════════════════════════════════════════════════════════════════════
require_once __DIR__ . '/../config/database.php';

// Set session security options
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_only_cookies', 1);
session_start();

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Validate and sanitize role parameter
$valid_roles = ['student', 'admin'];
$role_requested = isset($_GET['role']) && in_array($_GET['role'], $valid_roles) 
    ? $_GET['role'] 
    : 'student';

$error = '';
$login_attempts = 0;

// Track failed login attempts (simple rate limiting)
$attempt_key = 'login_attempts_' . $_SERVER['REMOTE_ADDR'];
if (isset($_SESSION[$attempt_key])) {
    $login_attempts = $_SESSION[$attempt_key];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid request. Please try again.";
    } elseif ($login_attempts >= 5) {
        $error = "Too many failed attempts. Please try again later.";
    } else {
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = "Email and password are required.";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = ? AND is_active = 1 LIMIT 1");
                $stmt->execute([$email, $role_requested]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    // Login successful - reset attempts
                    $_SESSION[$attempt_key] = 0;
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['role']    = $user['role'];
                    $_SESSION['name']    = $user['name'];

                    $dest = ($user['role'] === 'admin') ? '../admin/dashboard.php' : '../student/dashboard.php';
                    header("Location: $dest");
                    exit;
                } else {
                    $login_attempts++;
                    $_SESSION[$attempt_key] = $login_attempts;
                    $error = "Invalid email or password for the " . ucfirst($role_requested) . " portal.";
                }
            } catch (PDOException $e) {
                $error = "An error occurred. Please try again later.";
                error_log("Login error: " . $e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ucfirst($role_requested); ?> Login | WMSU</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Syne:wght@800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --bg: #0a0c10; --gold: #f1b933; --border: #1e2229; --gray: #94a3b8; }
        body { background: var(--bg); color: white; font-family: 'Inter', sans-serif; height: 100vh; display: flex; align-items: center; justify-content: center; margin: 0; overflow: hidden; }
        .grid-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-image: linear-gradient(to right, rgba(255,255,255,0.02) 1px, transparent 1px), linear-gradient(to bottom, rgba(255,255,255,0.02) 1px, transparent 1px); background-size: 50px 50px; z-index: -1; }
        
        .login-card { background: rgba(30, 41, 59, 0.4); backdrop-filter: blur(12px); border: 1px solid var(--border); padding: 40px; border-radius: 16px; width: 100%; max-width: 400px; box-shadow: 0 25px 50px rgba(0,0,0,0.5); position: relative; }
        
        /* Back Button Style */
        .btn-back { position: absolute; top: 20px; left: 20px; color: var(--gray); text-decoration: none; font-size: 0.75rem; font-weight: 600; display: flex; align-items: center; gap: 5px; transition: 0.2s; }
        .btn-back:hover { color: white; }

        .login-header h2 { font-family: 'Syne', sans-serif; text-transform: uppercase; font-size: 1.4rem; text-align: center; margin-bottom: 5px; margin-top: 10px; letter-spacing: -0.5px; }
        .login-header p { text-align: center; color: var(--gray); font-size: 0.8rem; margin-bottom: 30px; }
        
        .form-control { background: rgba(10, 12, 16, 0.6); border: 1px solid var(--border); color: white; padding: 12px; border-radius: 8px; }
        .form-control:focus { background: rgba(10, 12, 16, 0.8); border-color: var(--gold); color: white; box-shadow: none; }
        
        .btn-gold { background: var(--gold); color: black; font-weight: 800; width: 100%; padding: 12px; border: none; text-transform: uppercase; border-radius: 8px; margin-top: 10px; transition: 0.3s; }
        .btn-gold:hover { background: #ffcc4d; transform: translateY(-2px); }
        
        .error-msg { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #f87171; padding: 10px; border-radius: 8px; font-size: 0.8rem; margin-bottom: 20px; text-align: center; }
        
        /* Toggle Link Style */
        .portal-toggle { display: block; text-align: center; margin-top: 25px; font-size: 0.85rem; color: var(--gray); text-decoration: none; border-top: 1px solid var(--border); padding-top: 20px; }
        .portal-toggle b { color: var(--gold); }
        .portal-toggle:hover b { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="grid-overlay"></div>
    
    <div class="login-card">
        <a href="../index.php" class="btn-back">← BACK</a>

        <div class="login-header">
            <h2><?php echo ($role_requested === 'admin') ? '🔐 Admin' : '🎓 Student'; ?> Login</h2>
            <p>Please enter your credentials to continue</p>
        </div>

        <?php if($error): ?> <div class="error-msg"><?php echo htmlspecialchars($error); ?></div> <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div class="mb-3">
                <label class="small text-secondary fw-bold mb-1">EMAIL ADDRESS</label>
                <input type="email" name="email" class="form-control" placeholder="user@example.com" required>
            </div>
            <div class="mb-4">
                <label class="small text-secondary fw-bold mb-1">PASSWORD</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn btn-gold">Sign In →</button>
        </form>

        <?php if($role_requested === 'admin'): ?>
            <a href="login.php?role=student" class="portal-toggle">Are you a student? <b>Student Login</b></a>
        <?php else: ?>
            <a href="login.php?role=admin" class="portal-toggle">Are you staff? <b>Admin Portal</b></a>
        <?php endif; ?>
    </div>
</body>
</html>
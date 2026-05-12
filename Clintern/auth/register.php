<?php
// ══════════════════════════════════════
//  WMSU OESCD — Student Registration (Secured)
// ══════════════════════════════════════
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

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid request. Please try again.";
    } else {
        $full_name = trim($_POST['name'] ?? '');
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validation
        if (empty($full_name) || empty($email) || empty($password)) {
            $error = "All fields are required.";
        } elseif (strlen($password) < 8) {
            $error = "Password must be at least 8 characters long.";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            try {
                // Check if email exists
                $check = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
                $check->execute([$email]);
                
                if ($check->fetch()) {
                    $error = "Email is already registered.";
                } else {
                    // START TRANSACTION: Ensure both User and Student are created
                    $pdo->beginTransaction();

                    // 1. Insert into 'users' table
                    $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
                    $user_stmt = $pdo->prepare("INSERT INTO users (email, password_hash, role, name) VALUES (?, ?, 'student', ?)");
                    $user_stmt->execute([$email, $hashed_pass, $full_name]);

                    // Get the ID generated for this new user
                    $new_user_id = $pdo->lastInsertId();

                    // 2. Insert into 'students' table to prevent "Profile not found" error
                    // We split the name into First Name and Surname
                    $name_parts = explode(' ', $full_name, 2);
                    $first_name = $name_parts[0];
                    $surname = $name_parts[1] ?? ' '; // Handle cases with no last name

                    $student_stmt = $pdo->prepare("INSERT INTO students (user_id, first_name, surname) VALUES (?, ?, ?)");
                    $student_stmt->execute([$new_user_id, $first_name, $surname]);

                    // COMMIT: Save everything to database
                    $pdo->commit();

                    $message = "Registration successful! You can now login.";
                }
            } catch (PDOException $e) {
                // ROLLBACK: If any step fails, undo everything to keep data clean
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                // Don't expose database errors
                error_log("Registration error: " . $e->getMessage());
                $error = "Registration failed. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Registration | WMSU OESCD</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Syne:wght@800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --bg: #0a0c10; --gold: #f1b933; --card: #111419; --border: #1e2229; --text-muted: #64748b; }
        body { background: var(--bg); color: white; font-family: 'Inter', sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .reg-card { background: var(--card); border: 1px solid var(--border); padding: 40px; border-radius: 16px; width: 100%; max-width: 450px; }
        .brand { font-family: 'Syne', sans-serif; color: var(--gold); text-align: center; margin-bottom: 30px; font-size: 1.5rem; text-transform: uppercase; }
        .form-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); font-weight: 600; margin-bottom: 8px; }
        .form-control { background: #0a0c10; border: 1px solid var(--border); color: white; padding: 12px; border-radius: 8px; }
        .form-control:focus { background: #000; border-color: var(--gold); box-shadow: none; color: white; }
        .btn-reg { background: var(--gold); color: black; font-weight: 700; border: none; width: 100%; padding: 14px; margin-top: 20px; border-radius: 8px; transition: 0.3s; }
        .btn-reg:hover { background: #ffcc4d; transform: translateY(-2px); }
        .footer-link { text-align: center; margin-top: 25px; font-size: 0.85rem; color: var(--text-muted); }
        .footer-link a { color: var(--gold); text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>

    <div class="reg-card">
        <div class="brand">WMSU <span>STUDENT REG</span></div>

        <?php if($message): ?>
            <div class="alert alert-success bg-success text-white border-0 small py-2"><?= htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if($error): ?>
            <div class="alert alert-danger bg-danger text-white border-0 small py-2"><?= htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div class="mb-3">
                <label class="form-label">Full Name</label>
                <input type="text" name="name" class="form-control" placeholder="Juan Dela Cruz" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="juan@example.com" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn-reg">CREATE ACCOUNT</button>
        </form>

        <div class="footer-link">
            Already registered? <a href="login.php?role=student">Sign In</a>
        </div>
    </div>

</body>
</html>
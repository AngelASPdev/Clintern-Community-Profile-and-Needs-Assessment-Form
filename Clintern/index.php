<?php
// ══════════════════════════════════════
//  WMSU DESCD — Official Landing Page
// ══════════════════════════════════════
require_once __DIR__ . '/config/database.php';

// Set session security options
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_only_cookies', 1);
session_start();

// Redirect based on roles in your users table if already logged in
if (isset($_SESSION['user_id'])) {
    $dest = ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin')
        ? 'admin/dashboard.php'
        : 'student/dashboard.php';

    header("Location: {$dest}");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WMSU - DESCD System</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Syne:wght@800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --bg-black: #0a0c10;
            --wmsu-gold: #f1b933;
            --border-dark: #1e2229;
            --text-gray: #94a3b8;
            --card-bg: rgba(30, 41, 59, 0.3);
        }

        body {
            background-color: var(--bg-black);
            color: white;
            font-family: 'Inter', sans-serif;
            margin: 0;
            overflow-x: hidden;
        }

        /* Grid Pattern Background */
        .grid-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background-image: 
                linear-gradient(to right, rgba(255,255,255,0.02) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(255,255,255,0.02) 1px, transparent 1px);
            background-size: 50px 50px;
            z-index: -1;
            pointer-events: none;
        }

        /* Navbar */
        .main-nav {
            padding: 20px 60px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-dark);
            backdrop-filter: blur(10px);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .brand-logo {
            font-weight: 700;
            font-size: 1.1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-decoration: none;
            color: white;
        }

        .brand-logo span { color: var(--wmsu-gold); }
        .brand-logo small { color: var(--text-gray); font-weight: 400; margin-left: 5px; }

        .nav-btn {
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--border-dark);
            color: var(--text-gray);
            font-size: 0.75rem;
            padding: 6px 14px;
            border-radius: 6px;
            text-decoration: none;
            margin-left: 8px;
            transition: 0.3s;
        }

        .nav-btn:hover { border-color: var(--wmsu-gold); color: white; }

        /* Hero Section */
        .hero {
            padding: 80px 20px 40px;
            text-align: center;
        }

        .system-badge {
            display: inline-block;
            border: 1px solid #4d3d14;
            color: var(--wmsu-gold);
            padding: 5px 15px;
            border-radius: 4px;
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 2px;
            margin-bottom: 30px;
            text-transform: uppercase;
            background: rgba(241, 185, 51, 0.05);
        }

        .hero-title {
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: clamp(2rem, 6vw, 4.2rem);
            line-height: 1.1;
            text-transform: uppercase;
            letter-spacing: -1px;
            margin-bottom: 25px;
        }

        .text-gold { color: var(--wmsu-gold); }

        .hero-desc {
            color: var(--text-gray);
            max-width: 550px;
            font-size: 0.95rem;
            margin: 0 auto 40px;
            line-height: 1.6;
        }

        /* Primary Action Buttons */
        .hero-btns .btn {
            padding: 12px 30px;
            font-weight: 600;
            font-size: 0.85rem;
            border-radius: 6px;
            margin: 0 8px;
            transition: 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-gold { background: var(--wmsu-gold); color: black; border: none; }
        .btn-gold:hover { background: #ffcc4d; transform: translateY(-2px); box-shadow: 0 10px 20px rgba(241, 185, 51, 0.2); }

        .btn-outline { border: 1px solid var(--border-dark); color: var(--text-gray); }
        .btn-outline:hover { color: white; border-color: var(--text-gray); background: rgba(255,255,255,0.05); }

        /* Features Section */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            max-width: 1100px;
            margin: 60px auto;
            padding: 0 20px 100px;
        }

        /* Clickable Card Link Wrapper */
        .card-link {
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .feature-card {
            background: var(--card-bg);
            border: 1px solid var(--border-dark);
            padding: 35px;
            border-radius: 12px;
            backdrop-filter: blur(10px);
            transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            height: 100%;
        }

        .feature-card:hover {
            border-color: var(--wmsu-gold);
            transform: translateY(-8px);
            background: rgba(241, 185, 51, 0.05);
        }

        .feature-icon { font-size: 1.8rem; margin-bottom: 20px; display: block; }
        .feature-title { font-weight: 700; margin-bottom: 12px; font-size: 1.2rem; color: white; }
        .feature-desc { color: var(--text-gray); font-size: 0.88rem; line-height: 1.6; margin: 0; }

        footer {
            padding: 60px 40px;
            text-align: center;
            border-top: 1px solid var(--border-dark);
            margin-top: 50px;
        }

        .footer-text {
            color: #334155;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 600;
        }
    </style>
</head>
<body>

    <div class="grid-overlay"></div>

    <nav class="main-nav">
        <a href="index.php" class="brand-logo">🏢 <span>WMSU</span> <small>· DESCD System</small></a>
        <div>
            <a href="auth/login.php?role=student" class="nav-btn">🎓 Student Login</a>
            <a href="auth/login.php?role=admin" class="nav-btn">🔐 Admin</a>
        </div>
    </nav>

    <main class="hero">
        <div class="system-badge">✨ WMSU · OESCD · Extension Services & Community Development</div>

        <h1 class="hero-title">
            Extension Services<br>
            & <span class="text-gold">Community Development</span>
        </h1>

        <p class="hero-desc">
            Western Mindanao State University's portal for livelihood course enrollment and community profiling. Fast, paperless, and accessible anywhere.
        </p>

        <div class="hero-btns">
            <a href="auth/register.php" class="btn btn-gold">🚀 Get Started / Register</a>
            <a href="auth/login.php?role=student" class="btn btn-outline">🎓 Student Portal</a>
        </div>
    </main>

    <div class="features-grid">
        <a href="auth/register.php" class="card-link">
            <div class="feature-card">
                <span class="feature-icon">📌</span>
                <h3 class="feature-title">Online Enrollment</h3>
                <p class="feature-desc">Enroll in livelihood courses from anywhere. Register an account and get digital confirmations instantly.</p>
            </div>
        </a>

        <a href="auth/login.php?role=student" class="card-link">
            <div class="feature-card">
                <span class="feature-icon">📢</span>
                <h3 class="feature-title">Community Profiling</h3>
                <p class="feature-desc">Submit socio-economic data through your student dashboard to help WMSU tailor programs to your community.</p>
            </div>
        </a>

        <div class="feature-card">
            <span class="feature-icon">📜</span>
            <h3 class="feature-title">Audit Trails</h3>
            <p class="feature-desc">Every application and enrollment action is logged with secure timestamps for full institutional accountability.</p>
        </div>
    </div>

    <footer>
        <div class="footer-text">
            &copy; 2026 Western Mindanao State University · DESCD Office
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


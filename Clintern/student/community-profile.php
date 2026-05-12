<?php
// ══════════════════════════════════════════════════════════════════════════════
//  WMSU DESCD — Community Profile & Needs Assessment Form (Multi-Step)
// ══════════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../config/database.php';
session_start();

// Auth Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../auth/login.php?role=student');
    exit;
}

$user_id = $_SESSION['user_id'];
$current_step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$current_step = max(1, min(4, $current_step)); // Clamp between 1-4

$message = '';
$message_type = 'info';
$form_data = [];

// Fetch student info
$stmt = $pdo->prepare("SELECT student_id, first_name, surname FROM students WHERE user_id = ?");
$stmt->execute([$user_id]);
$student = $stmt->fetch();
$student_id = $student['student_id'];

// Fetch or create profile
$stmt = $pdo->prepare("SELECT * FROM community_profiles WHERE student_id = ?");
$stmt->execute([$student_id]);
$profile = $stmt->fetch();

if (!$profile) {
    // Create new profile
    $stmt = $pdo->prepare("INSERT INTO community_profiles (student_id, submission_status, step_completed) VALUES (?, 'draft', 0)");
    $stmt->execute([$student_id]);
    $profile_id = $pdo->lastInsertId();
    
    $stmt = $pdo->prepare("SELECT * FROM community_profiles WHERE profile_id = ?");
    $stmt->execute([$profile_id]);
    $profile = $stmt->fetch();
} else {
    $profile_id = $profile['profile_id'];
}

// ────────────────────────────────────────────────────────────────────────────────
// HANDLE FORM SUBMISSIONS
// ────────────────────────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $step = (int)$_POST['step'] ?? 1;
    
    try {
        if ($action === 'save_step') {
            switch ($step) {
                case 1:
                    handleStep1($pdo, $profile_id, $_POST);
                    break;
                case 2:
                    handleStep2($pdo, $profile_id, $_POST);
                    break;
                case 3:
                    handleStep3($pdo, $profile_id, $_POST);
                    break;
                case 4:
                    handleStep4($pdo, $profile_id, $_POST);
                    break;
            }
            
            // Refresh profile
            $stmt = $pdo->prepare("SELECT * FROM community_profiles WHERE profile_id = ?");
            $stmt->execute([$profile_id]);
            $profile = $stmt->fetch();
            
            // Redirect to next step or show completion
            if ($step < 4) {
                // Redirect to next step
                header('Location: ?step=' . ($step + 1));
                exit;
            } else {
                // Step 4 submitted - show completion message
                $message = "Profile submitted successfully! Thank you.";
                $message_type = 'success';
                $current_step = 4; // Keep on step 4 to show completion banner
            }
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = 'danger';
    }
}

// ────────────────────────────────────────────────────────────────────────────────
// STEP HANDLERS
// ────────────────────────────────────────────────────────────────────────────────

function handleStep1($pdo, $profile_id, $data) {
    // Handle photo upload
    $photo_path = null;
    if (!empty($_FILES['photo']['name'])) {
        $photo_path = uploadPhoto($_FILES['photo'], $profile_id);
    }
    
    $sql = "UPDATE community_profiles SET 
            first_name = ?, age = ?, gender = ?, position_in_family = ?,
            address = ?, occupation = ?, ethnicity = ?, religion = ?,
            monthly_income = ?, civil_status = ?, dialect = ?, highest_education = ?,
            step_completed = 1" . ($photo_path ? ", photo_path = '$photo_path'" : "") . "
            WHERE profile_id = ?";
    
    $params = [
        $data['first_name'] ?? '',
        $data['age'] ?? null,
        $data['gender'] ?? '',
        $data['position_in_family'] ?? '',
        $data['address'] ?? '',
        $data['occupation'] ?? '',
        $data['ethnicity'] ?? '',
        $data['religion'] ?? '',
        $data['monthly_income'] ?? 0,
        $data['civil_status'] ?? '',
        $data['dialect'] ?? '',
        $data['highest_education'] ?? '',
        $profile_id
    ];
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

function handleStep2($pdo, $profile_id, $data) {
    // Clear existing household data
    $pdo->prepare("DELETE FROM household_members WHERE profile_id = ?")->execute([$profile_id]);
    $pdo->prepare("DELETE FROM household_profile WHERE profile_id = ?")->execute([$profile_id]);
    
    // Insert household profile
    $sql = "INSERT INTO household_profile (profile_id, household_type, family_structure, land_ownership, length_of_stay, land_area_use) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $profile_id,
        $data['household_type'] ?? '',
        $data['family_structure'] ?? '',
        $data['land_ownership'] ?? '',
        $data['length_of_stay'] ?? '',
        $data['land_area_use'] ?? ''
    ]);
    
    // Insert household members
    $member_count = isset($data['member_name']) ? count($data['member_name']) : 0;
    for ($i = 0; $i < $member_count; $i++) {
        if (!empty($data['member_name'][$i])) {
            $sql = "INSERT INTO household_members (profile_id, name, date_of_birth, age, gender, education_level, occupation) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $profile_id,
                $data['member_name'][$i],
                $data['member_dob'][$i] ?? null,
                $data['member_age'][$i] ?? null,
                $data['member_gender'][$i] ?? '',
                $data['member_education'][$i] ?? '',
                $data['member_occupation'][$i] ?? ''
            ]);
        }
    }
    
    // Update step
    $pdo->prepare("UPDATE community_profiles SET step_completed = 2 WHERE profile_id = ?")->execute([$profile_id]);
}

function handleStep3($pdo, $profile_id, $data) {
    // Clear existing health data
    $pdo->prepare("DELETE FROM family_health WHERE profile_id = ?")->execute([$profile_id]);
    
    // Insert health data
    $sql = "INSERT INTO family_health (profile_id, vaccinated, vaccination_details, health_consultation, expert_consulted, consultation_frequency, health_notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $profile_id,
        $data['vaccinated'] ?? '',
        $data['vaccination_details'] ?? '',
        $data['health_consultation'] ?? '',
        $data['expert_consulted'] ?? '',
        $data['consultation_frequency'] ?? '',
        $data['health_notes'] ?? ''
    ]);
    
    // Update step
    $pdo->prepare("UPDATE community_profiles SET step_completed = 3 WHERE profile_id = ?")->execute([$profile_id]);
}

function handleStep4($pdo, $profile_id, $data) {
    // Clear existing work data
    $pdo->prepare("DELETE FROM skills_learned WHERE work_id IN (SELECT work_id FROM work_experience WHERE profile_id = ?)")->execute([$profile_id]);
    $pdo->prepare("DELETE FROM work_experience WHERE profile_id = ?")->execute([$profile_id]);
    
    // Insert work experience
    $sql = "INSERT INTO work_experience (profile_id, employment_status, work_type, years_in_job) 
            VALUES (?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $profile_id,
        $data['employment_status'] ?? '',
        $data['work_type'] ?? '',
        $data['years_in_job'] ?? null
    ]);
    
    $work_id = $pdo->lastInsertId();
    
    // Insert skills
    $skills = isset($data['skills']) ? $data['skills'] : [];
    foreach ($skills as $skill) {
        if (!empty($skill)) {
            $sql = "INSERT INTO skills_learned (work_id, skill_name) VALUES (?, ?)";
            $pdo->prepare($sql)->execute([$work_id, $skill]);
        }
    }
    
    // Update step and mark as submitted
    $pdo->prepare("UPDATE community_profiles SET step_completed = 4, submission_status = 'submitted' WHERE profile_id = ?")->execute([$profile_id]);
}

function uploadPhoto($file, $profile_id) {
    $upload_dir = __DIR__ . '/../uploads/profiles/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    
    if (!in_array(strtolower($file_ext), $allowed)) {
        throw new Exception("Invalid file type. Only JPG, PNG, GIF allowed.");
    }
    
    $max_size = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $max_size) {
        throw new Exception("File too large. Maximum 5MB allowed.");
    }
    
    $new_filename = "profile_{$profile_id}_" . time() . "." . $file_ext;
    $upload_path = $upload_dir . $new_filename;
    
    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        throw new Exception("Failed to upload file.");
    }
    
    return "uploads/profiles/" . $new_filename;
}

// Fetch related data
$household_members_stmt = $pdo->prepare("SELECT * FROM household_members WHERE profile_id = ? ORDER BY created_at");
$household_members_stmt->execute([$profile_id]);
$household_members = $household_members_stmt->fetchAll();

$household_stmt = $pdo->prepare("SELECT * FROM household_profile WHERE profile_id = ?");
$household_stmt->execute([$profile_id]);
$household = $household_stmt->fetch();

$health_stmt = $pdo->prepare("SELECT * FROM family_health WHERE profile_id = ?");
$health_stmt->execute([$profile_id]);
$health = $health_stmt->fetch();

$work_stmt = $pdo->prepare("SELECT * FROM work_experience WHERE profile_id = ?");
$work_stmt->execute([$profile_id]);
$work = $work_stmt->fetch();

$skills = [];
if ($work) {
    $skills_stmt = $pdo->prepare("SELECT * FROM skills_learned WHERE work_id = ? ORDER BY created_at");
    $skills_stmt->execute([$work['work_id']]);
    $skills = $skills_stmt->fetchAll();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Profile & Needs Assessment | WMSU OESCD</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Syne:wght@700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        :root {
            --bg-body: #0a0c10;
            --sidebar-bg: #111419;
            --card-bg: #14171c;
            --gold: #f1b933;
            --border: #1e2229;
            --text-muted: #64748b;
            --success: #10b981;
            --error: #ef4444;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background-color: var(--bg-body);
            color: #ffffff;
            font-family: 'Inter', sans-serif;
        }

        /* Sidebar */
        .sidebar {
            width: 240px;
            height: 100vh;
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border);
            padding: 20px;
            position: fixed;
            z-index: 1000;
            overflow-y: auto;
        }

        .sidebar-brand {
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            color: var(--gold);
            margin-bottom: 40px;
            font-size: 1.2rem;
            text-decoration: none;
            display: block;
        }

        .nav-link {
            color: var(--text-muted);
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 5px;
            text-decoration: none;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: 0.2s;
        }

        .nav-link:hover { color: white; background: rgba(255, 255, 255, 0.05); }
        .nav-link.active { background: rgba(241, 185, 51, 0.1); color: var(--gold); font-weight: 600; }

        /* Main Content */
        .main-wrapper {
            margin-left: 240px;
            width: calc(100% - 240px);
            min-height: 100vh;
            padding: 40px;
        }

        .form-container {
            max-width: 900px;
            margin: 0 auto;
        }

/* Progress Bar */
        .progress-section {
            margin-bottom: 60px;
            padding-top: 20px;
        }

        .progress-bar-custom {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin-bottom: 10px;
        }

        .progress-bar-custom::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--border);
            z-index: 0;
        }

        .step-indicator {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 1;
            flex: 1;
        }

        .step-circle {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--card-bg);
            border: 2px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--text-muted);
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }

        .step-indicator.active .step-circle {
            background: var(--gold);
            color: #000;
            border-color: var(--gold);
        }

        .step-indicator.completed .step-circle {
            background: var(--success);
            color: white;
            border-color: var(--success);
        }

        .step-indicator.completed .step-circle::after {
            content: '✓';
            position: absolute;
        }

        .step-label {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-align: center;
            max-width: 100px;
            text-transform: uppercase;
            font-weight: 600;
        }

        .step-indicator.active .step-label,
        .step-indicator.completed .step-label {
            color: white;
        }

        /* Alert Message */
        .alert-custom {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 4px solid;
        }

        .alert-custom.success {
            background: rgba(16, 185, 129, 0.1);
            border-color: var(--success);
            color: var(--success);
        }

        .alert-custom.danger {
            background: rgba(239, 68, 68, 0.1);
            border-color: var(--error);
            color: var(--error);
        }

        /* Form Container */
        .form-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 40px;
            margin-bottom: 30px;
        }

        .form-section-title {
            font-family: 'Syne', sans-serif;
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 30px;
            color: white;
        }

        .form-section-subtitle {
            color: var(--text-muted);
            margin-bottom: 20px;
            font-size: 0.95rem;
        }

        /* Form Groups */
        .form-group-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        label {
            color: #ffffff;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .required::after {
            content: ' *';
            color: var(--error);
        }

        input[type="text"],
        input[type="email"],
        input[type="number"],
        input[type="date"],
        select,
        textarea {
            background: #0f1117;
            border: 1px solid var(--border);
            color: white;
            padding: 12px 15px;
            border-radius: 6px;
            font-size: 0.9rem;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
        }

        /* Remove number input spinners */
        input[type="number"]::-webkit-outer-spin-button,
        input[type="number"]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        input[type="number"] {
            -moz-appearance: textfield;
            appearance: textfield;
        }

        input:focus,
        select:focus,
        textarea:focus {
            background: #14171c;
            border-color: var(--gold);
            outline: none;
            box-shadow: 0 0 0 3px rgba(241, 185, 51, 0.1);
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        /* Photo Upload */
        .photo-upload-container {
            border: 2px dashed var(--border);
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: rgba(241, 185, 51, 0.02);
        }

        .photo-upload-container:hover {
            border-color: var(--gold);
            background: rgba(241, 185, 51, 0.05);
        }

        .photo-upload-container.has-photo {
            border-style: solid;
        }

        .photo-preview {
            max-width: 200px;
            height: auto;
            border-radius: 8px;
            margin: 15px auto;
        }

        input[type="file"] {
            display: none;
        }

        /* Radio/Checkbox Groups */
        .checkbox-group,
        .radio-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .checkbox-item,
        .radio-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .checkbox-item:hover,
        .radio-item:hover {
            background: rgba(241, 185, 51, 0.05);
        }

        input[type="checkbox"],
        input[type="radio"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--gold);
        }

        /* Dynamic Table */
        .table-section {
            margin-bottom: 30px;
        }

        .table-section-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--gold);
        }

        .dynamic-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .dynamic-table th {
            background: rgba(241, 185, 51, 0.1);
            border: 1px solid var(--border);
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: var(--gold);
            font-size: 0.85rem;
        }

        .dynamic-table td {
            border: 1px solid var(--border);
            padding: 12px;
        }

        .dynamic-table td input {
            width: 100%;
        }

        .btn-remove-row {
            background: var(--error);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.2s ease;
        }

        .btn-remove-row:hover {
            background: #dc2626;
        }

        /* Buttons */
        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 40px;
            justify-content: space-between;
        }

        .btn-custom {
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--gold);
            color: #000;
        }

        .btn-primary:hover {
            background: #e6a91f;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: transparent;
            color: var(--gold);
            border: 2px solid var(--gold);
        }

        .btn-secondary:hover {
            background: rgba(241, 185, 51, 0.1);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-add-row {
            background: rgba(241, 185, 51, 0.1);
            color: var(--gold);
            border: 1px solid var(--gold);
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            margin-bottom: 15px;
            transition: all 0.2s ease;
        }

        .btn-add-row:hover {
            background: rgba(241, 185, 51, 0.2);
        }

        /* Completion Banner */
        .completion-banner {
            background: rgba(16, 185, 129, 0.1);
            border: 2px solid var(--success);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: center;
        }

        .completion-banner-title {
            color: var(--success);
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 10px;
        }

        .completion-banner-text {
            color: var(--text-muted);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-wrapper { margin-left: 0; width: 100%; padding: 20px; }
            .sidebar { width: 240px; transform: translateX(-100%); transition: all 0.3s ease; }
            .sidebar.active { transform: translateX(0); }
            .form-card { padding: 20px; }
            .form-group-row { grid-template-columns: 1fr; }
        }

/* Community Profile Header Specific Styles */
        .top-nav {
            height: 29px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 15px;
            padding: 0 40px;
        }
        .top-nav > span,
        .top-nav > a.btn {
            line-height: 29px;
            vertical-align: middle;
            position: relative;
            top: -15px;
        }
        .top-nav > span {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-right: 15px;
        }
        .top-nav > a.btn {
            padding: 0.15rem 0.5rem;
        }
        .community-profile-header {
            border-bottom: none;
            margin-bottom: 0;
        }
        .header-separator {
            border-top: 1px solid var(--border);
            margin: 0 -40px;
        }
    </style>
</head>
<body>

<!-- Sidebar Navigation -->
<aside class="sidebar">
        <a href="#" class="sidebar-brand">WMSU DESCD</a>
        <nav>
            <a href="dashboard.php" class="nav-link">🏠 Dashboard</a>
            <a href="enroll.php" class="nav-link">📝 Enroll Now</a>
            <a href="my-enrollments.php" class="nav-link">📄 My Enrollments</a>
            <a href="community-profile.php" class="nav-link active">📊 Community Profile</a>
            <a href="settings.php" class="nav-link">⚙️ Profile Settings</a>
        </nav>
    </aside>

<!-- Main Content -->
<div class="main-wrapper">
<!-- Header -->
    <header class="top-nav community-profile-header">
            <span><?= htmlspecialchars($student['first_name'] ?? 'User') ?></span>
            <a href="#" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#logoutConfirmModal">Logout</a>
        </header>
    <div class="header-separator"></div>

    <div class="form-container">
        
        <!-- Progress Bar -->
        <div class="progress-section">
            <div class="progress-bar-custom">
                <div class="step-indicator <?php echo $current_step >= 1 ? 'active' : ''; ?> <?php echo $profile['step_completed'] >= 1 ? 'completed' : ''; ?>">
                    <div class="step-circle">1</div>
                    <div class="step-label">Respondent<br>Profile</div>
                </div>
                <div class="step-indicator <?php echo $current_step >= 2 ? 'active' : ''; ?> <?php echo $profile['step_completed'] >= 2 ? 'completed' : ''; ?>">
                    <div class="step-circle">2</div>
                    <div class="step-label">Household<br>Profile</div>
                </div>
                <div class="step-indicator <?php echo $current_step >= 3 ? 'active' : ''; ?> <?php echo $profile['step_completed'] >= 3 ? 'completed' : ''; ?>">
                    <div class="step-circle">3</div>
                    <div class="step-label">Family<br>Health</div>
                </div>
                <div class="step-indicator <?php echo $current_step >= 4 ? 'active' : ''; ?> <?php echo $profile['step_completed'] >= 4 ? 'completed' : ''; ?>">
                    <div class="step-circle">4</div>
                    <div class="step-label">Work & Skills</div>
                </div>
            </div>
        </div>

        <!-- Message Alert -->
        <?php if ($message): ?>
            <div class="alert-custom <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Form Card -->
        <div class="form-card">
            
            <?php if ($current_step === 1): ?>
                <!-- STEP 1: RESPONDENT PROFILE -->
                <h2 class="form-section-title">Step 1: Respondent Profile</h2>
                <p class="form-section-subtitle">Socio-Economic & Demographic Information</p>

                <form method="POST" enctype="multipart/form-data" id="stepForm">
                    <input type="hidden" name="action" value="save_step">
                    <input type="hidden" name="step" value="1">

                    <!-- Photo Upload -->
                    <div class="form-group" style="margin-bottom: 30px;">
                        <label class="required">Photo</label>
                        <div class="photo-upload-container <?php echo $profile['photo_path'] ? 'has-photo' : ''; ?>" onclick="document.getElementById('photoInput').click()">
                            <?php if ($profile['photo_path']): ?>
                                <img src="../<?php echo htmlspecialchars($profile['photo_path']); ?>" alt="Profile Photo" class="photo-preview">
                                <p style="color: var(--text-muted); margin: 0;">Click to change photo</p>
                            <?php else: ?>
                                <p style="color: var(--text-muted); margin-bottom: 10px;">📷 Click to upload your photo</p>
                                <p style="font-size: 0.8rem; color: var(--text-muted);">JPG, PNG, GIF (Max 5MB)</p>
                            <?php endif; ?>
                        </div>
                        <input type="file" id="photoInput" name="photo" accept="image/*">
                    </div>

                    <!-- Basic Information -->
                    <div class="form-group-row">
                        <div class="form-group">
                            <label class="required">Name</label>
                            <input type="text" name="first_name" value="<?php echo htmlspecialchars($profile['first_name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="required">Date of Birth</label>
                            <input type="date" name="respondent_dob" id="respondentDob" required>
                        </div>
                        <div class="form-group">
                            <label>Age</label>
                            <input type="number" name="age" id="respondentAge" value="<?php echo htmlspecialchars($profile['age'] ?? ''); ?>" min="15" max="120" readonly>
                        </div>
                        <div class="form-group">
                            <label>Gender</label>
                            <select name="gender">
                                <option value="">Select...</option>
                                <option value="Male" <?php echo $profile['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo $profile['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo $profile['gender'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>

                    <!-- Personal Details -->
                    <div class="form-group-row">
                        <div class="form-group">
                            <label>Position in Family</label>
                            <select name="position_in_family">
                                <option value="">Select...</option>
                                <option value="Head" <?php echo $profile['position_in_family'] === 'Head' ? 'selected' : ''; ?>>Head of Family</option>
                                <option value="Spouse" <?php echo $profile['position_in_family'] === 'Spouse' ? 'selected' : ''; ?>>Spouse</option>
                                <option value="Child" <?php echo $profile['position_in_family'] === 'Child' ? 'selected' : ''; ?>>Child</option>
                                <option value="Parent" <?php echo $profile['position_in_family'] === 'Parent' ? 'selected' : ''; ?>>Parent</option>
                                <option value="Other" <?php echo $profile['position_in_family'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Civil Status</label>
                            <select name="civil_status">
                                <option value="">Select...</option>
                                <option value="Single" <?php echo $profile['civil_status'] === 'Single' ? 'selected' : ''; ?>>Single</option>
                                <option value="Married" <?php echo $profile['civil_status'] === 'Married' ? 'selected' : ''; ?>>Married</option>
                                <option value="Divorced" <?php echo $profile['civil_status'] === 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
                                <option value="Widowed" <?php echo $profile['civil_status'] === 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Ethnicity</label>
                            <input type="text" name="ethnicity" value="<?php echo htmlspecialchars($profile['ethnicity'] ?? ''); ?>">
                        </div>
                    </div>

                    <!-- Contact & Location -->
                    <div class="form-group">
                        <label class="required">Address</label>
                        <textarea name="address" required><?php echo htmlspecialchars($profile['address'] ?? ''); ?></textarea>
                    </div>

                    <!-- Work & Finance -->
                    <div class="form-group-row">
                        <div class="form-group">
                            <label>Occupation</label>
                            <input type="text" name="occupation" value="<?php echo htmlspecialchars($profile['occupation'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Monthly Income</label>
                            <input type="number" name="monthly_income" value="<?php echo htmlspecialchars($profile['monthly_income'] ?? '0'); ?>" min="0" step="0.01">
                        </div>
                    </div>

                    <!-- Education & Culture -->
                    <div class="form-group-row">
                        <div class="form-group">
                            <label>Highest Educational Attainment</label>
                            <select name="highest_education">
                                <option value="">Select...</option>
                                <option value="Elementary" <?php echo $profile['highest_education'] === 'Elementary' ? 'selected' : ''; ?>>Elementary</option>
                                <option value="High School" <?php echo $profile['highest_education'] === 'High School' ? 'selected' : ''; ?>>High School</option>
                                <option value="Bachelor's" <?php echo $profile['highest_education'] === 'Bachelor\'s' ? 'selected' : ''; ?>>Bachelor's Degree</option>
                                <option value="Master's" <?php echo $profile['highest_education'] === 'Master\'s' ? 'selected' : ''; ?>>Master's Degree</option>
                                <option value="Vocational" <?php echo $profile['highest_education'] === 'Vocational' ? 'selected' : ''; ?>>Vocational Training</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Religion</label>
                            <input type="text" name="religion" value="<?php echo htmlspecialchars($profile['religion'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Primary Dialect</label>
                            <input type="text" name="dialect" value="<?php echo htmlspecialchars($profile['dialect'] ?? ''); ?>">
                        </div>
                    </div>

                    <!-- Navigation Buttons -->
                    <div class="button-group">
                        <button type="button" class="btn-custom btn-secondary" disabled style="opacity: 0.5;">← Back</button>
                        <button type="submit" class="btn-custom btn-primary">Save & Continue →</button>
                    </div>
                </form>

            <?php elseif ($current_step === 2): ?>
                <!-- STEP 2: HOUSEHOLD PROFILE -->
                <h2 class="form-section-title">Step 2: Household Profile</h2>
                <p class="form-section-subtitle">Family Structure & Living Conditions</p>

                <form method="POST" id="stepForm">
                    <input type="hidden" name="action" value="save_step">
                    <input type="hidden" name="step" value="2">

                    <!-- Household Structure -->
                    <div class="form-group-row">
                        <div class="form-group">
                            <label class="required">Type of Household</label>
                            <select name="household_type" required>
                                <option value="">Select...</option>
                                <option value="Shanty" <?php echo $household && $household['household_type'] === 'Shanty' ? 'selected' : ''; ?>>Shanty</option>
                                <option value="Wood" <?php echo $household && $household['household_type'] === 'Wood' ? 'selected' : ''; ?>>Wood</option>
                                <option value="Concrete" <?php echo $household && $household['household_type'] === 'Concrete' ? 'selected' : ''; ?>>Concrete</option>
                                <option value="Semi-concrete" <?php echo $household && $household['household_type'] === 'Semi-concrete' ? 'selected' : ''; ?>>Semi-concrete</option>
                                <option value="Masonry" <?php echo $household && $household['household_type'] === 'Masonry' ? 'selected' : ''; ?>>Masonry</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="required">Family Structure</label>
                            <select name="family_structure" required>
                                <option value="">Select...</option>
                                <option value="Nuclear" <?php echo $household && $household['family_structure'] === 'Nuclear' ? 'selected' : ''; ?>>Nuclear</option>
                                <option value="Extended" <?php echo $household && $household['family_structure'] === 'Extended' ? 'selected' : ''; ?>>Extended</option>
                                <option value="Blended" <?php echo $household && $household['family_structure'] === 'Blended' ? 'selected' : ''; ?>>Blended</option>
                            </select>
                        </div>
                    </div>

                    <!-- Land Information -->
                    <div class="form-group-row">
                        <div class="form-group">
                            <label>Land Ownership</label>
                            <select name="land_ownership">
                                <option value="">Select...</option>
                                <option value="Yes" <?php echo $household && $household['land_ownership'] === 'Yes' ? 'selected' : ''; ?>>Yes</option>
                                <option value="No" <?php echo $household && $household['land_ownership'] === 'No' ? 'selected' : ''; ?>>No</option>
                                <option value="Rented" <?php echo $household && $household['land_ownership'] === 'Rented' ? 'selected' : ''; ?>>Rented</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Length of Stay</label>
                            <input type="text" name="length_of_stay" placeholder="e.g., 5 years" value="<?php echo htmlspecialchars($household['length_of_stay'] ?? ''); ?>">
                        </div>
                    </div>

                    <!-- Land Use -->
                    <div class="form-group">
                        <label>Land Area Use</label>
                        <textarea name="land_area_use" placeholder="Describe how the land is being used..."><?php echo htmlspecialchars($household['land_area_use'] ?? ''); ?></textarea>
                    </div>

                    <!-- Household Members Table -->
                    <div class="table-section">
                        <div class="table-section-title">Household Members</div>
                        <button type="button" class="btn-add-row" onclick="addHouseholdMemberRow()">+ Add Family Member</button>
                        <table class="dynamic-table">
                            <thead>
                                <tr>
                                    <th>Name *</th>
                                    <th>Date of Birth</th>
                                    <th>Age</th>
                                    <th>Gender</th>
                                    <th>Education</th>
                                    <th>Occupation</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="membersTable">
                                <?php foreach ($household_members as $member): ?>
                                    <tr>
                                        <td><input type="text" name="member_name[]" value="<?php echo htmlspecialchars($member['name']); ?>" required></td>
                                        <td><input type="date" name="member_dob[]" value="<?php echo htmlspecialchars($member['date_of_birth'] ?? ''); ?>"></td>
                                        <td><input type="number" name="member_age[]" value="<?php echo htmlspecialchars($member['age'] ?? ''); ?>" min="0"></td>
                                        <td><select name="member_gender[]"><option value="">Select</option><option value="Male" <?php echo $member['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option><option value="Female" <?php echo $member['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option></select></td>
                                        <td><input type="text" name="member_education[]" value="<?php echo htmlspecialchars($member['education_level'] ?? ''); ?>"></td>
                                        <td><input type="text" name="member_occupation[]" value="<?php echo htmlspecialchars($member['occupation'] ?? ''); ?>"></td>
                                        <td><button type="button" class="btn-remove-row" onclick="removeHouseholdMemberRow(this)">Remove</button></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (count($household_members) === 0): ?>
                                    <tr>
                                        <td><input type="text" name="member_name[]" placeholder="Family member name" required></td>
                                        <td><input type="date" name="member_dob[]"></td>
                                        <td><input type="number" name="member_age[]" min="0"></td>
                                        <td><select name="member_gender[]"><option value="">Select</option><option value="Male">Male</option><option value="Female">Female</option></select></td>
                                        <td><input type="text" name="member_education[]"></td>
                                        <td><input type="text" name="member_occupation[]"></td>
                                        <td><button type="button" class="btn-remove-row" onclick="removeHouseholdMemberRow(this)">Remove</button></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Navigation Buttons -->
                    <div class="button-group">
                        <a href="?step=1" class="btn-custom btn-secondary">← Back</a>
                        <button type="submit" class="btn-custom btn-primary">Save & Continue →</button>
                    </div>
                </form>

            <?php elseif ($current_step === 3): ?>
                <!-- STEP 3: FAMILY HEALTH -->
                <h2 class="form-section-title">Step 3: Family Health</h2>
                <p class="form-section-subtitle">Health & Medical Consultations</p>

                <form method="POST" id="stepForm">
                    <input type="hidden" name="action" value="save_step">
                    <input type="hidden" name="step" value="3">

                    <!-- Vaccination -->
                    <div class="form-group" style="margin-bottom: 25px;">
                        <label class="required">Are family members vaccinated?</label>
                        <div class="radio-group">
                            <label class="radio-item">
                                <input type="radio" name="vaccinated" value="Yes" <?php echo $health && $health['vaccinated'] === 'Yes' ? 'checked' : ''; ?> required>
                                Yes
                            </label>
                            <label class="radio-item">
                                <input type="radio" name="vaccinated" value="No" <?php echo $health && $health['vaccinated'] === 'No' ? 'checked' : ''; ?> required>
                                No
                            </label>
                        </div>
                    </div>

                    <!-- Vaccination Details -->
                    <div class="form-group">
                        <label>Vaccination Details</label>
                        <textarea name="vaccination_details" placeholder="Please specify which vaccines and dates..."><?php echo htmlspecialchars($health['vaccination_details'] ?? ''); ?></textarea>
                    </div>

                    <!-- Health Consultation -->
                    <div class="form-group" style="margin-bottom: 25px; margin-top: 25px;">
                        <label class="required">Have you consulted a health expert?</label>
                        <div class="radio-group">
                            <label class="radio-item">
                                <input type="radio" name="health_consultation" value="Yes" <?php echo $health && $health['health_consultation'] === 'Yes' ? 'checked' : ''; ?> required>
                                Yes
                            </label>
                            <label class="radio-item">
                                <input type="radio" name="health_consultation" value="No" <?php echo $health && $health['health_consultation'] === 'No' ? 'checked' : ''; ?> required>
                                No
                            </label>
                        </div>
                    </div>

                    <!-- Expert Type -->
                    <div class="form-group">
                        <label>Type of Expert Consulted</label>
                        <select name="expert_consulted">
                            <option value="">Select...</option>
                            <option value="Doctor" <?php echo $health && $health['expert_consulted'] === 'Doctor' ? 'selected' : ''; ?>>Doctor/Physician</option>
                            <option value="Midwife" <?php echo $health && $health['expert_consulted'] === 'Midwife' ? 'selected' : ''; ?>>Midwife</option>
                            <option value="Nurse" <?php echo $health && $health['expert_consulted'] === 'Nurse' ? 'selected' : ''; ?>>Nurse</option>
                            <option value="Albularyo" <?php echo $health && $health['expert_consulted'] === 'Albularyo' ? 'selected' : ''; ?>>Albularyo (Healer)</option>
                            <option value="Manghihilot" <?php echo $health && $health['expert_consulted'] === 'Manghihilot' ? 'selected' : ''; ?>>Manghihilot (Massage Therapist)</option>
                            <option value="Other" <?php echo $health && $health['expert_consulted'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>

                    <!-- Consultation Frequency -->
                    <div class="form-group">
                        <label>Frequency of Consultation</label>
                        <select name="consultation_frequency">
                            <option value="">Select...</option>
                            <option value="Weekly" <?php echo $health && $health['consultation_frequency'] === 'Weekly' ? 'selected' : ''; ?>>Weekly</option>
                            <option value="Monthly" <?php echo $health && $health['consultation_frequency'] === 'Monthly' ? 'selected' : ''; ?>>Monthly</option>
                            <option value="Quarterly" <?php echo $health && $health['consultation_frequency'] === 'Quarterly' ? 'selected' : ''; ?>>Quarterly</option>
                            <option value="Annually" <?php echo $health && $health['consultation_frequency'] === 'Annually' ? 'selected' : ''; ?>>Annually</option>
                            <option value="As needed" <?php echo $health && $health['consultation_frequency'] === 'As needed' ? 'selected' : ''; ?>>As needed</option>
                        </select>
                    </div>

                    <!-- Additional Notes -->
                    <div class="form-group">
                        <label>Additional Health Notes</label>
                        <textarea name="health_notes" placeholder="Any other relevant health information..."><?php echo htmlspecialchars($health['health_notes'] ?? ''); ?></textarea>
                    </div>

                    <!-- Navigation Buttons -->
                    <div class="button-group">
                        <a href="?step=2" class="btn-custom btn-secondary">← Back</a>
                        <button type="submit" class="btn-custom btn-primary">Save & Continue →</button>
                    </div>
                </form>

            <?php elseif ($current_step === 4): ?>
                <!-- STEP 4: WORK EXPERIENCE & SKILLS -->
                <h2 class="form-section-title">Step 4: Work Experience & Skills</h2>
                <p class="form-section-subtitle">Employment & Professional Skills</p>

                <form method="POST" id="stepForm">
                    <input type="hidden" name="action" value="save_step">
                    <input type="hidden" name="step" value="4">

                    <!-- Employment Status -->
                    <div class="form-group">
                        <label class="required">Employment Status</label>
                        <select name="employment_status" required>
                            <option value="">Select...</option>
                            <option value="Public" <?php echo $work && $work['employment_status'] === 'Public' ? 'selected' : ''; ?>>Public Sector</option>
                            <option value="Private" <?php echo $work && $work['employment_status'] === 'Private' ? 'selected' : ''; ?>>Private Sector</option>
                            <option value="Self-employed" <?php echo $work && $work['employment_status'] === 'Self-employed' ? 'selected' : ''; ?>>Self-employed</option>
                            <option value="Unemployed" <?php echo $work && $work['employment_status'] === 'Unemployed' ? 'selected' : ''; ?>>Unemployed</option>
                            <option value="Student" <?php echo $work && $work['employment_status'] === 'Student' ? 'selected' : ''; ?>>Student</option>
                        </select>
                    </div>

                    <!-- Type of Work -->
                    <div class="form-group">
                        <label>Type of Work</label>
                        <select name="work_type">
                            <option value="">Select...</option>
                            <option value="Government" <?php echo $work && $work['work_type'] === 'Government' ? 'selected' : ''; ?>>Government Officer</option>
                            <option value="Fisherman" <?php echo $work && $work['work_type'] === 'Fisherman' ? 'selected' : ''; ?>>Fisherman</option>
                            <option value="Farmer" <?php echo $work && $work['work_type'] === 'Farmer' ? 'selected' : ''; ?>>Farmer</option>
                            <option value="Vendor" <?php echo $work && $work['work_type'] === 'Vendor' ? 'selected' : ''; ?>>Vendor/Trader</option>
                            <option value="Professional" <?php echo $work && $work['work_type'] === 'Professional' ? 'selected' : ''; ?>>Professional</option>
                            <option value="Skilled Laborer" <?php echo $work && $work['work_type'] === 'Skilled Laborer' ? 'selected' : ''; ?>>Skilled Laborer</option>
                            <option value="Laborer" <?php echo $work && $work['work_type'] === 'Laborer' ? 'selected' : ''; ?>>Laborer</option>
                            <option value="Other" <?php echo $work && $work['work_type'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>

                    <!-- Years in Job -->
                    <div class="form-group">
                        <label>Years in Current Job</label>
                        <input type="number" name="years_in_job" value="<?php echo htmlspecialchars($work['years_in_job'] ?? ''); ?>" min="0" max="70">
                    </div>

                    <!-- Skills Section -->
                    <div style="margin-top: 40px; padding-top: 30px; border-top: 1px solid var(--border);">
                        <div class="table-section-title" style="margin-bottom: 20px;">Skills Learned</div>
                        <p class="form-section-subtitle">Select all applicable skills you have learned or currently possess:</p>
                        
                        <div class="checkbox-group">
                            <?php 
                            $all_skills = ['Farming', 'Cookery', 'Plumbing', 'Carpentry', 'Welding', 'Electrical', 'Tailoring', 'Hair Dressing', 'Automotive', 'Masonry', 'Animal Husbandry'];
                            $selected_skills = array_column($skills, 'skill_name');
                            
                            foreach ($all_skills as $skill):
                            ?>
                                <label class="checkbox-item">
                                    <input type="checkbox" name="skills[]" value="<?php echo htmlspecialchars($skill); ?>" <?php echo in_array($skill, $selected_skills) ? 'checked' : ''; ?>>
                                    <?php echo htmlspecialchars($skill); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Navigation Buttons -->
                    <div class="button-group" style="margin-top: 50px;">
                        <a href="?step=3" class="btn-custom btn-secondary">← Back</a>
                        <button type="submit" class="btn-custom btn-success">✓ Submit Profile</button>
                    </div>
                </form>

                <!-- Completion Message -->
                <?php if ($profile['submission_status'] === 'submitted'): ?>
                    <div class="completion-banner" style="margin-top: 30px;">
                        <div class="completion-banner-title">✓ Profile Submitted Successfully</div>
                        <div class="completion-banner-text">Your Community Profile and Needs Assessment form has been submitted. You can review and edit your information at any time.</div>
                    </div>
                <?php endif; ?>

            <?php endif; ?>

        </div>

    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteConfirmModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:2000; align-items:center; justify-content:center;">
    <div style="background:#14171c; border:1px solid var(--border); border-radius:12px; width:90%; max-width:420px; padding:24px;">
        <p style="margin:0 0 20px 0; color:white; font-size:1rem;">Are you sure you want to delete it?</p>
        <div style="display:flex; gap:12px; justify-content:flex-end;">
            <button type="button" id="confirmDeleteYes" style="background:var(--gold); color:#000; border:none; border-radius:8px; padding:10px 18px; font-weight:600;">Yes</button>
            <button type="button" id="confirmDeleteNo" style="background:transparent; color:#ef4444; border:1px solid #ef4444; border-radius:8px; padding:10px 18px; font-weight:600;">No</button>
        </div>
    </div>
</div>

<!-- Logout Confirmation Modal -->
<div class="modal fade" id="logoutConfirmModal" tabindex="-1" aria-labelledby="logoutConfirmLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header border-0">
                <h5 class="modal-title" id="logoutConfirmLabel">Confirm Logout</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to logout?
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn" style="background: transparent; color: #ef4444; border: 1px solid #ef4444; border-radius: 8px; padding: 8px 20px;" data-bs-dismiss="modal">No</button>
                <button type="button" id="logoutConfirmYes" class="btn" style="background: #f1b933; color: #000; border: none; border-radius: 8px; padding: 8px 20px; font-weight: 600;" data-logout-url="../auth/logout.php">Yes</button>
            </div>
        </div>
    </div>
</div>

<script>
// ═══════════════════════════════════════════════════════════════════════════════
// MULTI-STEP FORM AJAX HANDLER
// ═══════════════════════════════════════════════════════════════════════════════

function navigateStep(step) {
    // Smooth scroll and update URL
    window.location.hash = 'step-' + step;
    window.location.search = '?step=' + step;
}

// Form submission with validation
document.getElementById('stepForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const required_fields = this.querySelectorAll('[required]');
    let all_valid = true;
    
    required_fields.forEach(field => {
        if (!field.value) {
            field.style.borderColor = 'var(--error)';
            all_valid = false;
        } else {
            field.style.borderColor = '';
        }
    });
    
    if (!all_valid) {
        alert('Please fill in all required fields (marked with *)');
        return false;
    }
    
    // Submit form (with or without AJAX)
    this.submit();
});

function addHouseholdMemberRow() {
    const table = document.getElementById('membersTable');
    const row = table.insertRow();
    row.innerHTML = `
        <td><input type="text" name="member_name[]" placeholder="Family member name" required></td>
        <td><input type="date" name="member_dob[]"></td>
        <td><input type="number" name="member_age[]" min="0"></td>
        <td><select name="member_gender[]"><option value="">Select</option><option value="Male">Male</option><option value="Female">Female</option></select></td>
        <td><input type="text" name="member_education[]"></td>
        <td><input type="text" name="member_occupation[]"></td>
        <td><button type="button" class="btn-remove-row" onclick="removeHouseholdMemberRow(this)">Remove</button></td>
    `;
    setupHouseholdDobConstraints();
}

let pendingDeleteRow = null;

function openDeleteModal(rowElement) {
    pendingDeleteRow = rowElement;
    const modal = document.getElementById('deleteConfirmModal');
    if (modal) modal.style.display = 'flex';
}

function closeDeleteModal() {
    pendingDeleteRow = null;
    const modal = document.getElementById('deleteConfirmModal');
    if (modal) modal.style.display = 'none';
}

function removeHouseholdMemberRow(button) {
    const row = button.closest('tr');
    openDeleteModal(row);
}

// Auto-calculate age from date of birth
function calculateAge(dateOfBirth) {
    if (!dateOfBirth) return '';
    const dob = new Date(dateOfBirth);
    const today = new Date();
    let age = today.getFullYear() - dob.getFullYear();
    const monthDiff = today.getMonth() - dob.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
        age--;
    }
    return age >= 0 ? age : '';
}

// Enforce respondent minimum age (15+) and auto-calculate respondent age
function getMaxDobFor15YearsOld() {
    const today = new Date();
    return new Date(today.getFullYear() - 15, today.getMonth(), today.getDate());
}

function formatDateYYYYMMDD(dateObj) {
    const y = dateObj.getFullYear();
    const m = String(dateObj.getMonth() + 1).padStart(2, '0');
    const d = String(dateObj.getDate()).padStart(2, '0');
    return y + '-' + m + '-' + d;
}

function setupRespondentDobValidation() {
    const dobInput = document.getElementById('respondentDob');
    const ageInput = document.getElementById('respondentAge');
    if (!dobInput || !ageInput) return;

    const maxDob = getMaxDobFor15YearsOld();
    dobInput.max = formatDateYYYYMMDD(maxDob);

    dobInput.addEventListener('change', function() {
        const selected = this.value ? new Date(this.value) : null;
        if (!selected) {
            ageInput.value = '';
            return;
        }

        if (selected > maxDob) {
            alert('Student must be at least 15 years old.');
            this.value = '';
            ageInput.value = '';
            return;
        }

        ageInput.value = calculateAge(this.value);
    });
}

function setupHouseholdDobConstraints() {
    const today = new Date();
    const todayStr = formatDateYYYYMMDD(today);
    const minDob = new Date(today.getFullYear() - 120, today.getMonth(), today.getDate());
    const minDobStr = formatDateYYYYMMDD(minDob);

    document.querySelectorAll('input[name="member_dob[]"]').forEach(function(dobInput) {
        dobInput.max = todayStr;
        dobInput.min = minDobStr;
    });
}

// Live age calculation listener for household members (event delegation, works for dynamic rows)
document.addEventListener('change', function(e) {
    const target = e.target;
    if (target && target.matches('input[name="member_dob[]"]')) {
        const row = target.closest('tr');
        const ageField = row ? row.querySelector('input[name="member_age[]"]') : null;
        if (!ageField) return;

        const selected = target.value ? new Date(target.value) : null;
        const today = new Date();

        if (!selected) {
            ageField.value = '';
            return;
        }

        if (selected > today) {
            alert('Future dates are not allowed for household member DOB.');
            target.value = '';
            ageField.value = '';
            return;
        }

        const age = calculateAge(target.value);
        if (age !== '' && age < 15) {
            alert('Household member must be at least 15 years old.');
            target.value = '';
            ageField.value = '';
            return;
        }

        ageField.value = age;
    }
});

// Photo upload preview
document.getElementById('photoInput')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        // Validate file size (5MB max)
        if (file.size > 5 * 1024 * 1024) {
            alert('File size must not exceed 5MB');
            return;
        }
        
        // Validate file type
        const allowed = ['image/jpeg', 'image/png', 'image/gif'];
        if (!allowed.includes(file.type)) {
            alert('Only JPG, PNG, and GIF files are allowed');
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(event) {
            const container = document.querySelector('.photo-upload-container');
            container.innerHTML = `
                <img src="${event.target.result}" alt="Preview" class="photo-preview">
                <p style="color: var(--text-muted); margin: 0;">Click to change photo</p>
            `;
            container.classList.add('has-photo');
        };
        reader.readAsDataURL(file);
    }
});

// Reattach click handler after preview
document.querySelector('.photo-upload-container')?.addEventListener('click', function() {
    document.getElementById('photoInput')?.click();
});

// Ensure progress indicator follows URL state for forward/back navigation
function syncStepIndicatorWithUrl() {
    const step = parseInt(new URLSearchParams(window.location.search).get('step') || '1', 10);
    document.querySelectorAll('.step-indicator').forEach((el, i) => {
        const stepIndex = i + 1;
        el.classList.remove('active', 'completed');

        if (stepIndex < step) {
            el.classList.add('completed');
        } else if (stepIndex === step) {
            el.classList.add('active');
        }
    });
}

// Call on page load
document.addEventListener('DOMContentLoaded', function() {
    syncStepIndicatorWithUrl();
    setupRespondentDobValidation();

    const yesBtn = document.getElementById('confirmDeleteYes');
    const noBtn = document.getElementById('confirmDeleteNo');
    const modal = document.getElementById('deleteConfirmModal');

    if (yesBtn) {
        yesBtn.addEventListener('click', function() {
            if (pendingDeleteRow) pendingDeleteRow.remove();
            closeDeleteModal();
        });
    }

    if (noBtn) {
        noBtn.addEventListener('click', closeDeleteModal);
    }

    if (modal) {
        modal.addEventListener('click', function(evt) {
            if (evt.target === modal) closeDeleteModal();
        });
    }
});

window.addEventListener('popstate', syncStepIndicatorWithUrl);
window.addEventListener('hashchange', syncStepIndicatorWithUrl);

// Logout confirmation
document.querySelector('a[href*="logout"]')?.addEventListener('click', function(e) {
    if (!confirm('Are you sure you want to logout?')) {
        e.preventDefault();
    }
});

// Handle logout Yes button click
document.getElementById('logoutConfirmYes')?.addEventListener('click', function() {
    const logoutUrl = this.getAttribute('data-logout-url');
    if (logoutUrl) {
        window.location.href = logoutUrl;
    }
});
</script>

</body>
</html>
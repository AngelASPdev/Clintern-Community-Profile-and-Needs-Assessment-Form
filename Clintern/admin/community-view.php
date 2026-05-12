<?php
// ══════════════════════════════════════════════════════════════════════════════
//  WMSU DESCD — Admin: View Student Community Profile with PDF Export
// ══════════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../config/database.php';
session_start();

// Auth Check: Admin/Superadmin only
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header('Location: ../auth/login.php?role=admin');
    exit;
}

$profile_id = isset($_GET['profile_id']) ? (int)$_GET['profile_id'] : 0;
if (!$profile_id) {
    header('Location: community.php');
    exit;
}

// Fetch profile with related student
try {
    $stmt = $pdo->prepare("
        SELECT cp.*, s.email, CONCAT(s.first_name, ' ', s.surname) as student_full_name
        FROM community_profiles cp
        INNER JOIN students s ON s.student_id = cp.student_id
        WHERE cp.profile_id = ?
    ");
    $stmt->execute([$profile_id]);
    $profile = $stmt->fetch();
    
    if (!$profile) {
        throw new Exception("Profile not found");
    }
    
    // Fetch related data
    $stmt_members = $pdo->prepare("SELECT * FROM household_members WHERE profile_id = ? ORDER BY created_at");
    $stmt_members->execute([$profile_id]);
    $household_members = $stmt_members->fetchAll();
    
    $stmt_household = $pdo->prepare("SELECT * FROM household_profile WHERE profile_id = ?");
    $stmt_household->execute([$profile_id]);
    $household = $stmt_household->fetch();
    
    $stmt_health = $pdo->prepare("SELECT * FROM family_health WHERE profile_id = ?");
    $stmt_health->execute([$profile_id]);
    $health = $stmt_health->fetch();
    
    $stmt_work = $pdo->prepare("SELECT * FROM work_experience WHERE profile_id = ?");
    $stmt_work->execute([$profile_id]);
    $work = $stmt_work->fetch();
    
    $skills = [];
    if ($work) {
        $stmt_skills = $pdo->prepare("SELECT * FROM skills_learned WHERE work_id = ? ORDER BY created_at");
        $stmt_skills->execute([$work['work_id']]);
        $skills = $stmt_skills->fetchAll();
    }
    
} catch (Exception $e) {
    $error = "Error loading profile: " . $e->getMessage();
}

// Handle HTML to PDF Export (basic implementation)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_pdf'])) {
    $safe_first_name = preg_replace('/[^a-z0-9]+/i', '', strtolower((string)($profile['first_name'] ?? 'student')));
    if ($safe_first_name === '') {
        $safe_first_name = 'student';
    }
    $export_filename = $safe_first_name . '_id' . (int)$profile['student_id'] . '_' . date('Y_m_d') . '.html';

    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $export_filename . '"');
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Community Profile - <?php echo htmlspecialchars($profile['first_name'] . ' ' . $profile['surname']); ?></title>
        <style>
            /* Dark Theme Export Style */
            :root {
                --bg-body: #1a1c23;
                --card-bg: #242830;
                --gold: #f1b933;
                --border: #3d4451;
                --text-main: #e2e8f0;
                --text-muted: #94a3b8;
            }
            body { 
                font-family: 'Georgia', 'Times New Roman', 'Segoe UI', Arial, sans-serif; 
                background-color: var(--bg-body);
                color: var(--text-main); 
                max-width: 850px; 
                margin: 0 auto; 
                padding: 30px; 
            }
            .header { 
                text-align: center; 
                border-bottom: 3px solid var(--gold); 
                padding: 25px 0; 
                margin-bottom: 35px;
                background: linear-gradient(180deg, var(--card-bg) 0%, var(--bg-body) 100%);
                border-radius: 8px 8px 0 0;
            }
            .title { font-size: 26px; font-weight: bold; color: var(--gold); }
            .subtitle { font-size: 14px; color: var(--text-muted); margin-top: 8px; }
            .section { margin-bottom: 30px; page-break-inside: avoid; }
            .section-title { 
                font-size: 16px; 
                font-weight: bold; 
                background: linear-gradient(135deg, var(--gold) 0%, #d4a52e 100%); 
                color: #0a0c10; 
                padding: 12px 15px; 
                margin-bottom: 18px; 
                border-radius: 6px;
                border-left: 4px solid #fff;
            }
            .info-row { margin-bottom: 10px; display: flex; padding: 8px; background: var(--card-bg); border-radius: 4px; border-left: 3px solid var(--gold); }
            .label { font-weight: bold; width: 200px; flex-shrink: 0; color: var(--gold); }
            .value { flex: 1; }
            table { width: 100%; border-collapse: collapse; margin-top: 15px; }
            th { 
                background: linear-gradient(180deg, var(--gold) 0%, #d4a52e 100%); 
                color: #0a0c10;
                padding: 12px; 
                text-align: left; 
                border: 1px solid var(--border); 
                font-weight: bold; 
            }
            td { 
                padding: 10px; 
                border: 1px solid var(--border); 
                background: var(--card-bg);
            }
            tr:nth-child(even) td { background: rgba(36, 40, 48, 0.7); }
            .photo { 
                width: 140px; 
                height: 140px; 
                border: 3px solid var(--gold); 
                margin: 20px 0; 
                border-radius: 8px;
            }
            .footer { 
                margin-top: 50px; 
                text-align: center; 
                color: var(--text-muted); 
                font-size: 11px; 
                border-top: 1px solid var(--border); 
                padding-top: 25px; 
            }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="title">WMSU-DESCD Community Profile & Needs Assessment</div>
            <div class="subtitle">Socio-Economic and Demographic Data Form</div>
        </div>
        
        <!-- Student Info -->
        <div class="section">
            <div class="section-title">Student Information</div>
            <?php
            $embedded_photo = '';
            if (!empty($profile['photo_path'])) {
                $photo_abs_path = __DIR__ . '/../' . ltrim($profile['photo_path'], '/\\');
                if (file_exists($photo_abs_path) && is_file($photo_abs_path)) {
                    $photo_ext = strtolower(pathinfo($photo_abs_path, PATHINFO_EXTENSION));
                    $mime_map = [
                        'jpg' => 'image/jpeg',
                        'jpeg' => 'image/jpeg',
                        'png' => 'image/png',
                        'gif' => 'image/gif',
                        'webp' => 'image/webp'
                    ];
                    $mime = $mime_map[$photo_ext] ?? 'application/octet-stream';
                    $photo_bytes = @file_get_contents($photo_abs_path);
                    if ($photo_bytes !== false) {
                        $embedded_photo = 'data:' . $mime . ';base64,' . base64_encode($photo_bytes);
                    }
                }
            }
            ?>
            <?php if ($embedded_photo): ?>
                <div style="text-align: center;">
                    <img src="<?php echo $embedded_photo; ?>" class="photo" alt="Student Photo">
                </div>
            <?php else: ?>
                <div style="text-align:center; margin:20px 0; color: var(--text-muted);">No Photo Available</div>
            <?php endif; ?>
            <div class="info-row"><div class="label">Name:</div> <div class="value"><?php echo htmlspecialchars($profile['first_name'] && $profile['surname'] ? $profile['first_name'] . ' ' . $profile['surname'] : $profile['student_full_name']); ?></div></div>
            <div class="info-row"><div class="label">Student ID:</div> <div class="value"><?php echo htmlspecialchars($profile['student_id']); ?></div></div>
            <div class="info-row"><div class="label">Email:</div> <div class="value"><?php echo htmlspecialchars($profile['email']); ?></div></div>
            <div class="info-row"><div class="label">Submission Status:</div> <div class="value"><?php echo ucfirst(htmlspecialchars($profile['submission_status'])); ?></div></div>
            <div class="info-row"><div class="label">Submitted Date:</div> <div class="value"><?php echo htmlspecialchars(date('M d, Y', strtotime($profile['created_at']))); ?></div></div>
        </div>
        
        <!-- Step 1 -->
        <div class="section">
            <div class="section-title">Step 1: Respondent Profile (Socio-Economic & Demographic)</div>
            <div class="info-row"><div class="label">Name:</div> <div class="value"><?php echo htmlspecialchars($profile['first_name']); ?></div></div>
            <div class="info-row"><div class="label">Age:</div> <div class="value"><?php echo htmlspecialchars($profile['age'] ?? 'N/A'); ?></div></div>
            <div class="info-row"><div class="label">Gender:</div> <div class="value"><?php echo htmlspecialchars($profile['gender'] ?? 'N/A'); ?></div></div>
            <div class="info-row"><div class="label">Position in Family:</div> <div class="value"><?php echo htmlspecialchars($profile['position_in_family'] ?? 'N/A'); ?></div></div>
            <div class="info-row"><div class="label">Civil Status:</div> <div class="value"><?php echo htmlspecialchars($profile['civil_status'] ?? 'N/A'); ?></div></div>
            <div class="info-row"><div class="label">Address:</div> <div class="value"><?php echo nl2br(htmlspecialchars($profile['address'] ?? 'N/A')); ?></div></div>
            <div class="info-row"><div class="label">Occupation:</div> <div class="value"><?php echo htmlspecialchars($profile['occupation'] ?? 'N/A'); ?></div></div>
            <div class="info-row"><div class="label">Monthly Income:</div> <div class="value">PHP <?php echo htmlspecialchars(number_format($profile['monthly_income'] ?? 0, 2)); ?></div></div>
            <div class="info-row"><div class="label">Highest Education:</div> <div class="value"><?php echo htmlspecialchars($profile['highest_education'] ?? 'N/A'); ?></div></div>
            <div class="info-row"><div class="label">Ethnicity:</div> <div class="value"><?php echo htmlspecialchars($profile['ethnicity'] ?? 'N/A'); ?></div></div>
            <div class="info-row"><div class="label">Religion:</div> <div class="value"><?php echo htmlspecialchars($profile['religion'] ?? 'N/A'); ?></div></div>
            <div class="info-row"><div class="label">Primary Dialect:</div> <div class="value"><?php echo htmlspecialchars($profile['dialect'] ?? 'N/A'); ?></div></div>
        </div>
        
        <!-- Step 2 -->
        <?php if ($household): ?>
            <div class="section">
                <div class="section-title">Step 2: Household Profile</div>
                <div class="info-row"><div class="label">Household Type:</div> <div class="value"><?php echo htmlspecialchars($household['household_type']); ?></div></div>
                <div class="info-row"><div class="label">Family Structure:</div> <div class="value"><?php echo htmlspecialchars($household['family_structure']); ?></div></div>
                <div class="info-row"><div class="label">Land Ownership:</div> <div class="value"><?php echo htmlspecialchars($household['land_ownership']); ?></div></div>
                <div class="info-row"><div class="label">Length of Stay:</div> <div class="value"><?php echo htmlspecialchars($household['length_of_stay']); ?></div></div>
                <div class="info-row"><div class="label">Land Area Use:</div> <div class="value"><?php echo nl2br(htmlspecialchars($household['land_area_use'])); ?></div></div>
                
                <?php if (count($household_members) > 0): ?>
                    <h4 style="margin-top: 20px;">Household Members</h4>
                    <table>
                        <tr>
                            <th>Name</th>
                            <th>DOB</th>
                            <th>Age</th>
                            <th>Gender</th>
                            <th>Education</th>
                            <th>Occupation</th>
                        </tr>
                        <?php foreach ($household_members as $member): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($member['name']); ?></td>
                                <td><?php echo htmlspecialchars($member['date_of_birth'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($member['age'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($member['gender'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($member['education_level'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($member['occupation'] ?? 'N/A'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- Step 3 -->
        <?php if ($health): ?>
            <div class="section">
                <div class="section-title">Step 3: Family Health</div>
                <div class="info-row"><div class="label">Vaccinated:</div> <div class="value"><?php echo htmlspecialchars($health['vaccinated']); ?></div></div>
                <div class="info-row"><div class="label">Vaccination Details:</div> <div class="value"><?php echo nl2br(htmlspecialchars($health['vaccination_details'] ?? 'N/A')); ?></div></div>
                <div class="info-row"><div class="label">Health Consultation:</div> <div class="value"><?php echo htmlspecialchars($health['health_consultation']); ?></div></div>
                <div class="info-row"><div class="label">Expert Consulted:</div> <div class="value"><?php echo htmlspecialchars($health['expert_consulted'] ?? 'N/A'); ?></div></div>
                <div class="info-row"><div class="label">Consultation Frequency:</div> <div class="value"><?php echo htmlspecialchars($health['consultation_frequency'] ?? 'N/A'); ?></div></div>
                <div class="info-row"><div class="label">Health Notes:</div> <div class="value"><?php echo nl2br(htmlspecialchars($health['health_notes'] ?? 'N/A')); ?></div></div>
            </div>
        <?php endif; ?>
        
        <!-- Step 4 -->
        <?php if ($work): ?>
            <div class="section">
                <div class="section-title">Step 4: Work Experience & Skills</div>
                <div class="info-row"><div class="label">Employment Status:</div> <div class="value"><?php echo htmlspecialchars($work['employment_status']); ?></div></div>
                <div class="info-row"><div class="label">Work Type:</div> <div class="value"><?php echo htmlspecialchars($work['work_type'] ?? 'N/A'); ?></div></div>
                <div class="info-row"><div class="label">Years in Job:</div> <div class="value"><?php echo htmlspecialchars($work['years_in_job'] ?? 'N/A'); ?></div></div>
                <?php if (count($skills) > 0): ?>
                    <div class="info-row"><div class="label">Skills Learned:</div> <div class="value">
                        <?php echo htmlspecialchars(implode(', ', array_column($skills, 'skill_name'))); ?>
                    </div></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="footer">
            <p>Generated: <?php echo date('M d, Y \a\t h:i A'); ?></p>
            <p>Western Mindanao State University - DESCD</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile | Admin | WMSU DESCD</title>
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

        body { background-color: var(--bg-body); color: #ffffff; font-family: 'Inter', sans-serif; }

        .sidebar { width: 240px; height: 100vh; background: var(--sidebar-bg); border-right: 1px solid var(--border); padding: 20px; position: fixed; overflow-y: auto; }
        .sidebar-brand { font-family: 'Syne', sans-serif; font-weight: 800; color: var(--gold); font-size: 1.1rem; text-decoration: none; display: block; margin-bottom: 40px; }
        .nav-link { color: var(--text-muted); padding: 10px 15px; border-radius: 6px; margin-bottom: 4px; display: flex; align-items: center; gap: 10px; text-decoration: none; font-size: 0.85rem; }
        .nav-link.active { background: rgba(241, 185, 51, 0.1); color: var(--gold); font-weight: 600; }
        .nav-link:hover:not(.active) { color: white; background: rgba(255, 255, 255, 0.05); }

        .top-nav { height: 60px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: flex-end; padding: 0 40px; }
        .main-wrapper { margin-left: 240px; width: calc(100% - 240px); padding: 40px; }
        .back-link { color: var(--text-muted); text-decoration: none; font-size: 0.9rem; margin-bottom: 20px; display: inline-flex; align-items: center; gap: 8px; }
        .back-link:hover { color: white; }

        .page-title { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 2.5rem; margin-bottom: 10px; }

        .profile-card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 12px; padding: 30px; margin-bottom: 30px; }
        .section-title { font-family: 'Syne', sans-serif; font-size: 1.3rem; font-weight: 700; color: var(--gold); margin-top: 30px; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid rgba(241, 185, 51, 0.3); }
        
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .info-item { background: rgba(241, 185, 51, 0.05); padding: 15px; border-radius: 6px; border-left: 3px solid var(--gold); }
        .info-label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; margin-bottom: 5px; }
        .info-value { font-size: 1rem; color: white; word-break: break-word; }

        .photo-container { text-align: center; margin: 20px 0; }
        .profile-photo { max-width: 200px; max-height: 250px; border-radius: 8px; border: 2px solid var(--gold); }

        .table-custom { background: transparent; }
        .table-custom th { background: rgba(241, 185, 51, 0.1); color: var(--gold); font-weight: 600; border: 1px solid var(--border); }
        .table-custom td { border: 1px solid var(--border); padding: 12px; }

        .button-group { display: flex; gap: 15px; margin-top: 30px; flex-wrap: wrap; }
        .btn-custom { padding: 12px 25px; border-radius: 8px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; border: none; cursor: pointer; transition: all 0.3s; }
        .btn-gold { background: var(--gold); color: #000; }
        .btn-gold:hover { background: #e6a91f; }
        .btn-secondary { background: transparent; color: var(--gold); border: 2px solid var(--gold); }
        .btn-secondary:hover { background: rgba(241, 185, 51, 0.1); }

        .status-badge { display: inline-block; padding: 6px 12px; border-radius: 4px; font-size: 0.85rem; font-weight: 600; margin-left: 10px; background: rgba(16, 185, 129, 0.1); color: #10b981; }

        .text-value { color: white; }
        .text-muted-info { color: var(--text-muted); }

        @media (max-width: 768px) {
            .main-wrapper { margin-left: 0; width: 100%; }
            .sidebar { transform: translateX(-100%); }
        }

@media print {
            @page { margin: 18mm 14mm; }

            body {
                background: #f4f6fb !important;
                color: #1e293b !important;
                font-family: "Georgia", "Times New Roman", "Segoe UI", Arial, sans-serif !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .sidebar, .button-group, .back-link, .print-hide, .top-nav { display: none !important; }
            .main-wrapper { margin-left: 0 !important; width: 100% !important; padding: 0 !important; }

            .print-letterhead {
                display: block !important;
                text-align: center;
                margin: 0 0 14px 0;
                padding: 10px 10px 12px 10px;
                border-bottom: 2px solid #0f274a;
                color: #0f274a !important;
                background: #e9eef8 !important;
            }

            .print-letterhead .line1 { font-size: 11px; letter-spacing: 1px; text-transform: uppercase; }
            .print-letterhead .line2 { font-size: 17px; font-weight: 700; margin-top: 2px; }
            .print-letterhead .line3 { font-size: 11px; margin-top: 2px; }

            .page-title, .status-badge { display: none !important; }

            .profile-card {
                background: #ffffff !important;
                color: #1e293b !important;
                border: 1px solid #b9c7dd !important;
                box-shadow: none !important;
                margin-bottom: 12px !important;
                page-break-inside: avoid !important;
                break-inside: avoid;
            }

            .section-title {
                font-size: 13px !important;
                color: #0f274a !important;
                border-bottom: 1px solid #b9c7dd !important;
                background: #edf2fb !important;
                padding: 6px 8px !important;
                border-radius: 4px;
                margin-top: 6px !important;
            }

            h2, h3, h4 { color: #0f274a !important; }
            h4[style*="color: var(--gold)"] { color: #0f274a !important; }

            .info-grid { gap: 8px !important; }
            .info-item {
                background: #f2f5fb !important;
                border-left: 3px solid #335b8f !important;
            }
            .info-label { color: #334155 !important; }
            .info-value, .text-value { color: #0f172a !important; }
            .text-muted-info, .subtitle-info { color: #475569 !important; }

            .print-box, div[style*="var(--gold)"] {
                background: #f2f5fb !important;
                border-left-color: #335b8f !important;
                color: #0f172a !important;
            }

            table, .table-custom, .table-responsive { page-break-inside: avoid !important; }
            tr, td, th { page-break-inside: avoid !important; }

            .table-custom th {
                background: #dbe7f7 !important;
                color: #102a43 !important;
                border-color: #b9c7dd !important;
            }
            .table-custom td { border-color: #c7d2e3 !important; color: #0f172a !important; }

            .profile-photo {
                border-color: #335b8f !important;
                max-width: 120px !important;
                max-height: 150px !important;
            }

            a { color: #0f274a !important; text-decoration: none !important; }
            .table-responsive, table.wrapper { overflow: visible !important; }
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
            <a href="#" class="nav-link text-danger" data-bs-toggle="modal" data-bs-target="#logoutModal">🚪 Logout</a>
        </nav>
    </aside>

    
<div class="print-letterhead" style="display:none;">
    <div class="line1">Republic of the Philippines</div>
    <div class="line2">Western Mindanao State University</div>
    <div class="line3">Office of the Extension Services & Community Development</div>
</div>

<div class="main-wrapper">
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php else: ?>

<!-- Back Button at Top -->
<a href="community.php" class="btn-custom btn-secondary print-hide" style="margin-bottom: 20px; display: inline-flex;">← Back to Profiles</a>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <div>
                <h1 class="page-title">Student Community Profile
                    <span class="status-badge">
                        <?php echo ucfirst($profile['submission_status']); ?>
                    </span>
                </h1>
            </div>
<div class="button-group print-hide" style="margin-top: 0;">
                <form method="POST" style="display: inline;">
                    <button type="submit" name="export_pdf" class="btn-custom btn-gold print-hide">📥 Export (HTML)</button>
                </form>
                <button class="btn-custom btn-secondary print-hide" onclick="window.print()">🖨️ Print</button>
            </div>
        </div>

        <!-- Student Header Card -->
        <div class="profile-card">
            <div style="display: flex; gap: 30px; align-items: flex-start;">
                <div>
                    <?php if ($profile['photo_path']): ?>
                        <img src="../<?php echo htmlspecialchars($profile['photo_path']); ?>" alt="Photo" class="profile-photo">
                    <?php else: ?>
                        <div style="width: 200px; height: 200px; background: var(--border); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--text-muted);">No Photo</div>
                    <?php endif; ?>
                </div>
                <div style="flex: 1;">
                    <h2 style="margin-bottom: 5px;"><?php echo htmlspecialchars($profile['first_name'] . ' ' . $profile['surname']); ?></h2>
                    <p style="color: var(--text-muted); margin-bottom: 15px;">Student ID: <?php echo htmlspecialchars($profile['student_id']); ?></p>
                    <p style="color: var(--text-muted); margin-bottom: 20px;"><?php echo htmlspecialchars($profile['email']); ?></p>
                    
                    <div class="info-grid" style="margin-top: 20px;">
                        <div class="info-item">
                            <div class="info-label">Submission Status</div>
                            <div class="info-value"><?php echo ucfirst($profile['submission_status']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Progress</div>
                            <div class="info-value">Step <?php echo $profile['step_completed']; ?>/4</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Last Updated</div>
                            <div class="info-value"><?php echo date('M d, Y', strtotime($profile['updated_at'])); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 1: Respondent Profile -->
        <div class="profile-card">
            <h3 class="section-title">Step 1: Respondent Profile</h3>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Full Name</div>
                    <div class="info-value"><?php echo htmlspecialchars($profile['first_name'] . ' ' . $profile['surname']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Age</div>
                    <div class="info-value"><?php echo htmlspecialchars($profile['age'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Gender</div>
                    <div class="info-value"><?php echo htmlspecialchars($profile['gender'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Civil Status</div>
                    <div class="info-value"><?php echo htmlspecialchars($profile['civil_status'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Position in Family</div>
                    <div class="info-value"><?php echo htmlspecialchars($profile['position_in_family'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Occupation</div>
                    <div class="info-value"><?php echo htmlspecialchars($profile['occupation'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Monthly Income</div>
                    <div class="info-value">PHP <?php echo htmlspecialchars(number_format($profile['monthly_income'], 2)); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Highest Education</div>
                    <div class="info-value"><?php echo htmlspecialchars($profile['highest_education'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Ethnicity</div>
                    <div class="info-value"><?php echo htmlspecialchars($profile['ethnicity'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Religion</div>
                    <div class="info-value"><?php echo htmlspecialchars($profile['religion'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Dialect</div>
                    <div class="info-value"><?php echo htmlspecialchars($profile['dialect'] ?? 'N/A'); ?></div>
                </div>
            </div>

<h4 class="print-header" style="margin-top: 25px; margin-bottom: 15px;">Address</h4>
            <div class="print-box" style="padding: 15px; border-radius: 6px; border-left: 3px solid #333;">
                <?php echo nl2br(htmlspecialchars($profile['address'] ?? 'N/A')); ?>
            </div>
        </div>

        <!-- Step 2: Household Profile -->
        <?php if ($household): ?>
            <div class="profile-card">
                <h3 class="section-title">Step 2: Household Profile</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Household Type</div>
                        <div class="info-value"><?php echo htmlspecialchars($household['household_type']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Family Structure</div>
                        <div class="info-value"><?php echo htmlspecialchars($household['family_structure']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Land Ownership</div>
                        <div class="info-value"><?php echo htmlspecialchars($household['land_ownership']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Length of Stay</div>
                        <div class="info-value"><?php echo htmlspecialchars($household['length_of_stay']); ?></div>
                    </div>
                </div>
                
                <h4 style="margin-top: 25px; color: var(--gold); margin-bottom: 15px;">Land Area Use</h4>
                <div style="background: rgba(241, 185, 51, 0.05); padding: 15px; border-radius: 6px; border-left: 3px solid var(--gold);">
                    <?php echo nl2br(htmlspecialchars($household['land_area_use'])); ?>
                </div>

                <?php if (count($household_members) > 0): ?>
                    <h4 style="margin-top: 25px; color: var(--gold); margin-bottom: 15px;">Household Members</h4>
                    <div style="overflow-x: auto;">
                        <table class="table table-custom">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Date of Birth</th>
                                    <th>Age</th>
                                    <th>Gender</th>
                                    <th>Education Level</th>
                                    <th>Occupation</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($household_members as $member): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($member['name']); ?></td>
                                        <td><?php echo htmlspecialchars($member['date_of_birth'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($member['age'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($member['gender'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($member['education_level'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($member['occupation'] ?? 'N/A'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Step 3: Family Health -->
        <?php if ($health): ?>
            <div class="profile-card">
                <h3 class="section-title">Step 3: Family Health</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Vaccinated</div>
                        <div class="info-value"><?php echo htmlspecialchars($health['vaccinated']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Health Consultation</div>
                        <div class="info-value"><?php echo htmlspecialchars($health['health_consultation']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Expert Consulted</div>
                        <div class="info-value"><?php echo htmlspecialchars($health['expert_consulted'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Consultation Frequency</div>
                        <div class="info-value"><?php echo htmlspecialchars($health['consultation_frequency'] ?? 'N/A'); ?></div>
                    </div>
                </div>

                <h4 style="margin-top: 20px; color: var(--gold); margin-bottom: 10px;">Vaccination Details</h4>
                <div style="background: rgba(241, 185, 51, 0.05); padding: 15px; border-radius: 6px; border-left: 3px solid var(--gold); margin-bottom: 20px;">
                    <?php echo nl2br(htmlspecialchars($health['vaccination_details'] ?? 'No details provided')); ?>
                </div>

                <h4 style="margin-top: 20px; color: var(--gold); margin-bottom: 10px;">Health Notes</h4>
                <div style="background: rgba(241, 185, 51, 0.05); padding: 15px; border-radius: 6px; border-left: 3px solid var(--gold);">
                    <?php echo nl2br(htmlspecialchars($health['health_notes'] ?? 'No notes provided')); ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Step 4: Work Experience & Skills -->
        <?php if ($work): ?>
            <div class="profile-card">
                <h3 class="section-title">Step 4: Work Experience & Skills</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Employment Status</div>
                        <div class="info-value"><?php echo htmlspecialchars($work['employment_status']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Work Type</div>
                        <div class="info-value"><?php echo htmlspecialchars($work['work_type'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Years in Current Job</div>
                        <div class="info-value"><?php echo htmlspecialchars($work['years_in_job'] ?? 'N/A'); ?></div>
                    </div>
                </div>

                <?php if (count($skills) > 0): ?>
                    <h4 style="margin-top: 20px; color: var(--gold); margin-bottom: 15px;">Skills Learned</h4>
                    <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                        <?php foreach ($skills as $skill): ?>
                            <span style="background: rgba(241, 185, 51, 0.2); color: var(--gold); padding: 8px 15px; border-radius: 20px; font-size: 0.85rem;">
                                ✓ <?php echo htmlspecialchars($skill['skill_name']); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

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

<?php
require_once __DIR__ . '/config/database.php';

echo "Checking students table structure...\n\n";

$columns = $pdo->query("SHOW COLUMNS FROM students")->fetchAll();
$column_names = array_column($columns, 'Field');

echo "Current columns in students table:\n";
foreach ($columns as $col) {
    echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
}

echo "\n";

// Add email column if it doesn't exist
if (!in_array('email', $column_names)) {
    echo "Adding email column to students table...\n";
    try {
        $pdo->exec("ALTER TABLE students ADD COLUMN `email` VARCHAR(255) DEFAULT NULL UNIQUE");
        echo "✅ Email column added successfully\n";
    } catch (Exception $e) {
        echo "⚠️  Could not add email: " . $e->getMessage() . "\n";
    }
} else {
    echo "✓ Email column already exists\n";
}

// Verify all required tables one final time
echo "\n\nFinal Database Schema Verification:\n";
echo "════════════════════════════════════════════════\n";

$tables = [
    'community_profiles' => ['profile_id', 'student_id', 'step_completed', 'submission_status', 'photo_path', 'age', 'gender'],
    'household_members' => ['member_id', 'profile_id', 'name', 'date_of_birth', 'gender'],
    'household_profile' => ['household_id', 'profile_id', 'household_type', 'family_structure'],
    'family_health' => ['health_id', 'profile_id', 'vaccinated', 'health_consultation'],
    'work_experience' => ['work_id', 'profile_id', 'employment_status', 'work_type'],
    'skills_learned' => ['skill_id', 'work_id', 'skill_name'],
    'students' => ['student_id', 'first_name', 'surname', 'email']
];

$all_ok = true;
foreach ($tables as $table => $required_cols) {
    echo "\n📋 Table: $table\n";
    $cols = $pdo->query("SHOW COLUMNS FROM $table")->fetchAll();
    $col_names = array_column($cols, 'Field');
    
    $table_ok = true;
    foreach ($required_cols as $col) {
        $exists = in_array($col, $col_names);
        echo "   " . ($exists ? "✅" : "❌") . " $col\n";
        if (!$exists) $table_ok = false;
    }
    
    echo "   → Status: " . ($table_ok ? "✅ READY" : "❌ INCOMPLETE") . "\n";
    if (!$table_ok) $all_ok = false;
}

echo "\n════════════════════════════════════════════════\n";
echo ($all_ok ? "✅ ALL TABLES READY FOR TESTING" : "❌ SOME TABLES NEED ATTENTION") . "\n";
echo "════════════════════════════════════════════════\n";

?>

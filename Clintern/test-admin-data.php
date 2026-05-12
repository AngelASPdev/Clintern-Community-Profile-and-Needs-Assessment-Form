<?php
require_once __DIR__ . '/config/database.php';

echo "Admin Community Profiles Test\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

try {
    $stmt = $pdo->prepare('
        SELECT cp.profile_id, cp.first_name, cp.surname, cp.submission_status, cp.step_completed, s.email
        FROM community_profiles cp
        INNER JOIN students s ON s.student_id = cp.student_id
        ORDER BY cp.updated_at DESC
    ');
    $stmt->execute();
    $profiles = $stmt->fetchAll();

    echo "Found " . count($profiles) . " profiles:\n\n";

    foreach ($profiles as $p) {
        echo "Profile ID: {$p['profile_id']}\n";
        echo "  Name: {$p['first_name']} {$p['surname']}\n";
        echo "  Email: {$p['email']}\n";
        echo "  Status: {$p['submission_status']}\n";
        echo "  Step: {$p['step_completed']}/4\n";
        echo "  View URL: community-view.php?profile_id={$p['profile_id']}\n\n";
    }

    if (count($profiles) > 0) {
        // Test fetching one profile's data
        $test_profile_id = $profiles[0]['profile_id'];
        echo "Testing profile ID $test_profile_id data fetch:\n";

        // Household members
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM household_members WHERE profile_id = ?");
        $stmt->execute([$test_profile_id]);
        $members_count = $stmt->fetch()['count'];
        echo "  Household members: $members_count\n";

        // Household profile
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM household_profile WHERE profile_id = ?");
        $stmt->execute([$test_profile_id]);
        $household_count = $stmt->fetch()['count'];
        echo "  Household profile: $household_count\n";

        // Family health
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM family_health WHERE profile_id = ?");
        $stmt->execute([$test_profile_id]);
        $health_count = $stmt->fetch()['count'];
        echo "  Family health: $health_count\n";

        // Work experience
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM work_experience WHERE profile_id = ?");
        $stmt->execute([$test_profile_id]);
        $work_count = $stmt->fetch()['count'];
        echo "  Work experience: $work_count\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "═══════════════════════════════════════════════════════════════════\n\n";

try {
    $stmt = $pdo->query('SELECT student_id, first_name, surname, email FROM students LIMIT 5');
    $students = $stmt->fetchAll();

    echo "Students in database (" . count($students) . "):\n";
    foreach ($students as $s) {
        echo "  ID: {$s['student_id']}, Name: {$s['first_name']} {$s['surname']}, Email: {$s['email']}\n";
    }
    echo "\n";

    // Check raw community_profiles
    $stmt = $pdo->query('SELECT profile_id, student_id, first_name, surname, submission_status, step_completed FROM community_profiles');
    $raw_profiles = $stmt->fetchAll();
    echo "Raw community_profiles data:\n";
    foreach ($raw_profiles as $p) {
        echo "  Profile ID: {$p['profile_id']}, Student ID: {$p['student_id']}, Name: {$p['first_name']} {$p['surname']}, Status: {$p['submission_status']}, Step: {$p['step_completed']}\n";
    }

    // Check students table structure
    $result = $pdo->query('DESCRIBE students');
    $columns = $result->fetchAll();
    echo "\nStudents table columns:\n";
    foreach ($columns as $col) {
        echo "  {$col['Field']} - {$col['Type']}\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
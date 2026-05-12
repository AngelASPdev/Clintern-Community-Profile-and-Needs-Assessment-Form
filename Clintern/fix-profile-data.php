<?php
require_once __DIR__ . '/config/database.php';

echo "Fixing Invalid Profile Data\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

try {
    // Delete invalid profile ID 0 and its related data
    $pdo->prepare('DELETE FROM household_members WHERE profile_id = 0')->execute();
    $pdo->prepare('DELETE FROM household_profile WHERE profile_id = 0')->execute();
    $pdo->prepare('DELETE FROM family_health WHERE profile_id = 0')->execute();
    $pdo->prepare('DELETE FROM work_experience WHERE profile_id = 0')->execute();
    $pdo->prepare('DELETE FROM skills_learned WHERE work_id IN (SELECT work_id FROM work_experience WHERE profile_id = 0)')->execute();
    $pdo->prepare('DELETE FROM work_experience WHERE profile_id = 0')->execute();
    $pdo->prepare('DELETE FROM community_profiles WHERE profile_id = 0')->execute();
    echo "✅ Deleted invalid profile ID 0 and related data\n";

    // Check if student 2 has a profile
    $stmt = $pdo->prepare('SELECT profile_id FROM community_profiles WHERE student_id = 2');
    $stmt->execute();
    $profile = $stmt->fetch();
    
    if (!$profile) {
        // Create new profile for student 2
        $pdo->prepare('INSERT INTO community_profiles (student_id, submission_status, step_completed) VALUES (2, "draft", 0)')->execute();
        $new_id = $pdo->lastInsertId();
        echo "✅ Created new profile ID $new_id for student 2\n";
    } else {
        echo "ℹ️ Student 2 already has profile ID {$profile['profile_id']}\n";
    }

    // Update profile ID 2 to have proper data if needed
    $stmt = $pdo->prepare('SELECT first_name, surname FROM community_profiles WHERE profile_id = 2');
    $stmt->execute();
    $data = $stmt->fetch();
    if (empty($data['first_name'])) {
        // Copy name from students table
        $pdo->prepare('UPDATE community_profiles SET first_name = (SELECT first_name FROM students WHERE student_id = 1), surname = (SELECT surname FROM students WHERE student_id = 1) WHERE profile_id = 2')->execute();
        echo "✅ Updated profile ID 2 with student name\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n═══════════════════════════════════════════════════════════════════\n";
echo "Data fix complete. Admin view should now work properly.\n";
?>
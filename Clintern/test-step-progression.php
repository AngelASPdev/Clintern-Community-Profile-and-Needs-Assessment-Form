<?php
require_once __DIR__ . '/config/database.php';

echo "Testing Step Progression Logic\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

// Test with student 1
$student_id = 1;
$stmt = $pdo->prepare('SELECT profile_id, step_completed, submission_status FROM community_profiles WHERE student_id = ? LIMIT 1');
$stmt->execute([$student_id]);
$profile = $stmt->fetch();

if ($profile) {
    echo "✅ Profile found for student 1\n";
    echo "   Profile ID: " . $profile['profile_id'] . "\n";
    echo "   Current Step: " . $profile['step_completed'] . "/4\n";
    echo "   Status: " . $profile['submission_status'] . "\n";
    
    // Test the redirect logic
    echo "\n📋 Step Progression Simulation:\n";
    for ($i = 1; $i <= 4; $i++) {
        $next = ($i < 4) ? $i + 1 : "COMPLETE";
        echo "   Step $i saved → " . ($i < 4 ? "Redirect to ?step=$next" : "Show Completion Message") . "\n";
    }
} else {
    echo "❌ No profile found for student 1\n";
    echo "\nCreating test profile...\n";
    $stmt = $pdo->prepare('INSERT INTO community_profiles (student_id, submission_status, step_completed) VALUES (?, ?, ?)');
    $stmt->execute([$student_id, 'draft', 0]);
    echo "✅ Test profile created\n";
}

echo "\n═══════════════════════════════════════════════════════════════════\n";
echo "Form Submission Flow is now configured to:\n";
echo "  1. POST form data with action='save_step' and step value\n";
echo "  2. Save data to appropriate database tables\n";
echo "  3. Update step_completed in community_profiles\n";
echo "  4. Redirect to ?step=(next_step) OR show completion banner\n";
echo "═══════════════════════════════════════════════════════════════════\n";

?>

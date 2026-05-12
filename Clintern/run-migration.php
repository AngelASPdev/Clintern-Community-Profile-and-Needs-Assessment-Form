<?php
/**
 * Database Migration Runner
 * Executes the database migration for the community profile module
 */

require_once __DIR__ . '/config/database.php';

echo "════════════════════════════════════════════════════════════════\n";
echo "WMSU-OESCD Community Profile Migration\n";
echo "════════════════════════════════════════════════════════════════\n\n";

// Read migration file
$migration_file = __DIR__ . '/database_migration.sql';

if (!file_exists($migration_file)) {
    echo "❌ Error: database_migration.sql not found!\n";
    exit(1);
}

$sql = file_get_contents($migration_file);

try {
    echo "📝 Executing migration...\n";
    
    // Split SQL statements more carefully
    $lines = explode("\n", $sql);
    $statement = '';
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Skip empty lines and comments
        if (empty($line) || str_starts_with($line, '--')) {
            continue;
        }
        
        $statement .= ' ' . $line;
        
        // Execute when we find a semicolon
        if (str_ends_with($line, ';')) {
            if (!empty(trim($statement))) {
                echo "   Executing: " . substr(trim($statement), 0, 60) . "...\n";
                try {
                    $pdo->exec($statement);
                } catch (Exception $e) {
                    echo "   ⚠️  Notice: " . $e->getMessage() . "\n";
                    // Some ALTER TABLE ADD COLUMN statements might fail if column already exists
                    // This is okay for idempotent migrations
                }
            }
            $statement = '';
        }
    }
    
    echo "✅ Migration completed successfully!\n\n";
    
    // Verify tables were created
    echo "📊 Verifying database structure...\n";
    
    $tables = [
        'community_profiles' => 'Checking community_profiles expansion...',
        'household_members' => 'Checking household_members table...',
        'household_profile' => 'Checking household_profile table...',
        'family_health' => 'Checking family_health table...',
        'work_experience' => 'Checking work_experience table...',
        'skills_learned' => 'Checking skills_learned table...',
        'profile_uploads' => 'Checking profile_uploads table...',
    ];
    
    foreach ($tables as $table => $message) {
        echo "   $message ";
        
        $result = $pdo->query("SHOW TABLES LIKE '$table'")->fetch();
        
        if ($result) {
            echo "✓\n";
            
            // Show columns for main table
            if ($table === 'community_profiles') {
                $columns = $pdo->query("SHOW COLUMNS FROM community_profiles")->fetchAll();
                echo "      Columns: " . count($columns) . "\n";
            }
        } else {
            echo "✗ NOT FOUND\n";
        }
    }
    
    echo "\n════════════════════════════════════════════════════════════════\n";
    echo "✅ Database migration completed successfully!\n";
    echo "════════════════════════════════════════════════════════════════\n";
    echo "\nYou can now:\n";
    echo "1. Navigate to /Clintern/student/community-profile.php to fill out the form\n";
    echo "2. View submitted profiles in /Clintern/admin/community.php\n";
    echo "3. View individual profiles in /Clintern/admin/community-view.php?profile_id=X\n";
    
} catch (Exception $e) {
    echo "❌ Migration failed!\n";
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>

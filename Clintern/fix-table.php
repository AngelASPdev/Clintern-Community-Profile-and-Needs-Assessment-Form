<?php
require_once __DIR__ . '/config/database.php';

echo "Checking community_profiles table structure...\n\n";

$columns = $pdo->query("SHOW COLUMNS FROM community_profiles")->fetchAll();

echo "Current columns in community_profiles:\n";
foreach ($columns as $col) {
    echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
}

echo "\n\nAttempting to add missing columns...\n";

$missing_columns = [
    'first_name' => "VARCHAR(255) DEFAULT NULL",
    'surname' => "VARCHAR(255) DEFAULT NULL",
    'step_completed' => "INT DEFAULT 0 COMMENT 'Tracks which step user completed (0-4)'",
    'submission_status' => "ENUM('draft', 'incomplete', 'submitted') DEFAULT 'draft'",
    'photo_path' => "VARCHAR(255) DEFAULT NULL COMMENT 'Path to student photo'",
    'age' => "INT DEFAULT NULL",
    'gender' => "VARCHAR(20) DEFAULT NULL",
    'position_in_family' => "VARCHAR(100) DEFAULT NULL",
    'address' => "TEXT DEFAULT NULL",
    'occupation' => "VARCHAR(255) DEFAULT NULL",
    'ethnicity' => "VARCHAR(100) DEFAULT NULL",
    'religion' => "VARCHAR(100) DEFAULT NULL",
    'civil_status' => "VARCHAR(50) DEFAULT NULL",
    'dialect' => "VARCHAR(100) DEFAULT NULL",
    'highest_education' => "VARCHAR(255) DEFAULT NULL",
    'updated_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
];

$existing_columns = array_column($columns, 'Field');

foreach ($missing_columns as $column => $definition) {
    if (!in_array($column, $existing_columns)) {
        $sql = "ALTER TABLE community_profiles ADD COLUMN `$column` $definition";
        try {
            $pdo->exec($sql);
            echo "✅ Added column: $column\n";
        } catch (Exception $e) {
            echo "⚠️  Could not add $column: " . $e->getMessage() . "\n";
        }
    } else {
        echo "✓  Column already exists: $column\n";
    }
}

echo "\n\nFinal table structure:\n";
$final_columns = $pdo->query("SHOW COLUMNS FROM community_profiles")->fetchAll();
foreach ($final_columns as $col) {
    echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
}

?>

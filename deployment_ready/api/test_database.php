<?php
require_once 'database.php';

try {
    echo "Testing database connection...\n";
    $db = new Database();
    echo "✓ Database connection successful\n";
    
    echo "Testing database methods...\n";
    $activeGroups = $db->getActiveGroupsCount();
    echo "✓ Active groups count: $activeGroups\n";
    
    $activeRuns = $db->getActiveRunsCount();
    echo "✓ Active runs count: $activeRuns\n";
    
    echo "✓ All database tests passed!\n";
    
} catch (Exception $e) {
    echo "✗ Database test failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>

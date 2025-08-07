<?php

echo "=== DIRECT ORCHESTRATOR TEST ===\n";

echo "\n1. Creating logs directory...\n";
$logsDir = '/home/ftcceelg/load_testing_system/logs';
if (!is_dir($logsDir)) {
    if (mkdir($logsDir, 0755, true)) {
        echo "✓ Created logs directory: $logsDir\n";
    } else {
        echo "✗ Failed to create logs directory: $logsDir\n";
    }
} else {
    echo "✓ Logs directory already exists: $logsDir\n";
}

echo "\n2. Creating reports directory...\n";
$reportsDir = '/home/ftcceelg/load_testing_system/reports';
if (!is_dir($reportsDir)) {
    if (mkdir($reportsDir, 0755, true)) {
        echo "✓ Created reports directory: $reportsDir\n";
    } else {
        echo "✗ Failed to create reports directory: $reportsDir\n";
    }
} else {
    echo "✓ Reports directory already exists: $reportsDir\n";
}

echo "\n3. Testing log file writing...\n";
$logFile = '/home/ftcceelg/load_testing_system/logs/backend.log';
$testMessage = "[" . date('Y-m-d H:i:s') . "] DIRECT_TEST: Testing continuous adaptive orchestrator\n";

if (file_put_contents($logFile, $testMessage, FILE_APPEND)) {
    echo "✓ Log file writable: $logFile\n";
    echo "  Content: " . trim($testMessage) . "\n";
} else {
    echo "✗ Log file not writable: $logFile\n";
}

echo "\n4. Testing database connection...\n";
try {
    require_once 'api/database.php';
    $db = new Database();
    echo "✓ Database connection successful\n";
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
}

echo "\n5. Testing ContinuousAdaptiveOrchestrator initialization...\n";
try {
    require_once 'api/continuous_adaptive_orchestrator.php';
    $orchestrator = new ContinuousAdaptiveOrchestrator($db);
    echo "✓ ContinuousAdaptiveOrchestrator initialized successfully\n";
    
    $targets = ['https://httpbin.org/status/503'];
    $config = [
        'profile_id' => 'power',
        'threads' => 5,
        'duration' => 60,
        'engine' => 'auto-bypass',
        'behavior_profile_id' => 'power'
    ];
    
    $groupId = $orchestrator->start($targets, $config);
    echo "✓ Orchestrator started with group ID: $groupId\n";
    
    $status = $orchestrator->getStatus();
    echo "✓ Status retrieved: " . json_encode($status) . "\n";
    
    $orchestrator->stop();
    echo "✓ Orchestrator stopped successfully\n";
    
} catch (Exception $e) {
    echo "✗ ContinuousAdaptiveOrchestrator failed: " . $e->getMessage() . "\n";
    echo "  Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== DIRECT TEST COMPLETED ===\n";
echo "Check logs: $logFile\n";
echo "Check reports: $reportsDir\n";

?>

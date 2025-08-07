<?php

echo "=== SIMPLE CONTINUOUS ADAPTIVE TEST ===\n";

echo "\n1. Testing file includes...\n";

$requiredFiles = [
    'api/database.php',
    'api/continuous_adaptive_orchestrator.php',
    'api/success_detector.php',
    'api/stealth_engine_class.php',
    'api/client_profile_class.php',
    'api/tls_profile_class.php',
    'api/proxy_manager_class.php'
];

foreach ($requiredFiles as $file) {
    if (file_exists($file)) {
        echo "✓ Found: $file\n";
    } else {
        echo "✗ Missing: $file\n";
    }
}

echo "\n2. Testing JSON configuration files...\n";

$jsonFiles = [
    'attack_profiles.json',
    'proxy_pool.json', 
    'ua_pool.json'
];

foreach ($jsonFiles as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $data = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "✓ Valid JSON: $file\n";
        } else {
            echo "✗ Invalid JSON: $file - " . json_last_error_msg() . "\n";
        }
    } else {
        echo "✗ Missing: $file\n";
    }
}

echo "\n3. Testing directory creation...\n";

$testDirs = [
    '/home/ftcceelg/load_testing_system/logs',
    '/home/ftcceelg/load_testing_system/reports'
];

foreach ($testDirs as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0755, true)) {
            echo "✓ Created directory: $dir\n";
        } else {
            echo "✗ Failed to create directory: $dir\n";
        }
    } else {
        echo "✓ Directory exists: $dir\n";
    }
}

echo "\n4. Testing log file writing...\n";

$logFile = '/home/ftcceelg/load_testing_system/logs/backend.log';
$testMessage = "[" . date('Y-m-d H:i:s') . "] SIMPLE_TEST: Continuous adaptive test started\n";

if (file_put_contents($logFile, $testMessage, FILE_APPEND)) {
    echo "✓ Log file writable: $logFile\n";
} else {
    echo "✗ Log file not writable: $logFile\n";
}

echo "\n5. Testing attack engine files...\n";

$engineFiles = [
    'api/attack_engines/auto_bypass.php',
    'api/attack_engines/socket_spam.php',
    'api/attack_engines/http_spammer.php',
    'api/attack_engines/raw_socket.php'
];

foreach ($engineFiles as $file) {
    if (file_exists($file)) {
        echo "✓ Found engine: $file\n";
    } else {
        echo "✗ Missing engine: $file\n";
    }
}

echo "\n=== SIMPLE TEST COMPLETED ===\n";
echo "Check logs directory: /home/ftcceelg/load_testing_system/logs/\n";
echo "Check reports directory: /home/ftcceelg/load_testing_system/reports/\n";

?>

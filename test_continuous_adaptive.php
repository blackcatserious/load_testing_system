<?php

require_once 'api/continuous_adaptive_orchestrator.php';
require_once 'api/behavior_profile_manager.php';
require_once 'api/success_detector.php';
require_once 'api/stealth_session_reporter.php';

echo "=== CONTINUOUS ADAPTIVE ORCHESTRATOR TEST ===\n";

echo "\n1. Testing component initialization...\n";

try {
    $orchestrator = new ContinuousAdaptiveOrchestrator();
    echo "✓ ContinuousAdaptiveOrchestrator initialized\n";
} catch (Exception $e) {
    echo "✗ ContinuousAdaptiveOrchestrator failed: " . $e->getMessage() . "\n";
}

try {
    $behaviorManager = new BehaviorProfileManager();
    echo "✓ BehaviorProfileManager initialized\n";
} catch (Exception $e) {
    echo "✗ BehaviorProfileManager failed: " . $e->getMessage() . "\n";
}

try {
    $successDetector = new SuccessDetector(null);
    echo "✓ SuccessDetector initialized\n";
} catch (Exception $e) {
    echo "✗ SuccessDetector failed: " . $e->getMessage() . "\n";
}

try {
    $stealthReporter = new StealthSessionReporter();
    echo "✓ StealthSessionReporter initialized\n";
} catch (Exception $e) {
    echo "✗ StealthSessionReporter failed: " . $e->getMessage() . "\n";
}

echo "\n2. Testing behavior profiles...\n";

$profiles = ['power', 'scanner', 'mobile'];
foreach ($profiles as $profileId) {
    $profile = $behaviorManager->getProfile($profileId);
    if ($profile) {
        echo "✓ Profile '$profileId' loaded successfully\n";
        echo "  - Escalation factor: " . ($profile['escalation_factor'] ?? 'N/A') . "\n";
        echo "  - Thread scaling: " . json_encode($profile['thread_scaling'] ?? []) . "\n";
        echo "  - Human behavior enabled: " . ($profile['human_behavior']['enabled'] ? 'Yes' : 'No') . "\n";
        echo "  - Cookie management: " . ($profile['cookie_management']['per_thread_cookies'] ? 'Yes' : 'No') . "\n";
    } else {
        echo "✗ Profile '$profileId' not found\n";
    }
}

echo "\n3. Testing cookie management...\n";

$threadId = 'test_thread_001';
$profileId = 'power';

if ($behaviorManager->initializeThreadCookies($threadId, $profileId)) {
    echo "✓ Thread cookies initialized for $threadId\n";
    
    $behaviorManager->setCookie($threadId, 'session_id', 'test_session_123', 'example.com');
    $behaviorManager->setCookie($threadId, 'user_pref', 'dark_mode', 'example.com');
    
    $cookies = $behaviorManager->getCookies($threadId, 'example.com');
    echo "✓ Cookies set and retrieved: " . count($cookies) . " cookies\n";
    
    $cookieHeader = $behaviorManager->getCookieHeader($threadId, 'example.com');
    echo "✓ Cookie header generated: " . substr($cookieHeader, 0, 50) . "...\n";
} else {
    echo "✗ Thread cookie initialization failed\n";
}

echo "\n4. Testing human behavior simulation...\n";

$result = $behaviorManager->executeHumanBehavior($threadId, $profileId, 'https://example.com');
if ($result['success']) {
    echo "✓ Human behavior executed successfully\n";
    echo "  - Actions executed: " . $result['actions_executed'] . "\n";
    echo "  - Wait time: " . $result['wait_time_ms'] . "ms\n";
    echo "  - Click pattern: " . ($result['click_pattern'] ?? 'N/A') . "\n";
    echo "  - Scroll type: " . ($result['scroll_type'] ?? 'N/A') . "\n";
} else {
    echo "✗ Human behavior execution failed: " . $result['reason'] . "\n";
}

echo "\n5. Testing success detection...\n";

$testMetrics = [
    'total_requests' => 1000,
    'status_codes' => [
        '200' => 100,
        '404' => 400,
        '503' => 300,
        '524' => 100,
        '403' => 50,
        '429' => 50
    ],
    'latency' => ['avg' => 500, 'p95' => 800, 'p99' => 1200],
    'zero_byte_responses' => 50,
    'recent_responses' => []
];

$analysis = $successDetector->isTargetDisabled('https://test-target.com', $testMetrics);
echo "✓ Target analysis completed\n";
echo "  - Disabled: " . ($analysis['disabled'] ? 'Yes' : 'No') . "\n";
echo "  - Success rate: " . ($analysis['success_rate'] ?? 'N/A') . "\n";
echo "  - Protection rate: " . ($analysis['protection_rate'] ?? 'N/A') . "\n";
echo "  - Requests analyzed: " . $analysis['requests_analyzed'] . "\n";

echo "\n6. Testing stealth reporting...\n";

$groupId = 'test_group_' . time();
$sessionData = [
    'engine' => 'auto-bypass',
    'threads' => 500,
    'stealth_level' => 'maximum',
    'ja3_rotations' => 5,
    'proxy_rotations' => 3,
    'ua_rotations' => 4
];

$stealthReporter->logStealthSession($groupId, $sessionData);
echo "✓ Stealth session logged\n";

$successData = [
    'https://target1.com' => ['success_rate' => 0.8, 'total_requests' => 500],
    'https://target2.com' => ['success_rate' => 0.6, 'total_requests' => 400]
];

$reportFile = $stealthReporter->createSuccessVerificationReport($groupId, ['https://target1.com', 'https://target2.com'], $successData);
echo "✓ Success verification report created: $reportFile\n";

echo "\n7. Testing file system setup...\n";

$requiredDirs = [
    '/home/ftcceelg/load_testing_system/logs',
    '/home/ftcceelg/load_testing_system/reports'
];

foreach ($requiredDirs as $dir) {
    if (is_dir($dir) || mkdir($dir, 0755, true)) {
        echo "✓ Directory exists or created: $dir\n";
    } else {
        echo "✗ Failed to create directory: $dir\n";
    }
}

$logFile = '/home/ftcceelg/load_testing_system/logs/backend.log';
if (file_put_contents($logFile, "[TEST] Continuous adaptive test completed at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND)) {
    echo "✓ Backend log file writable: $logFile\n";
} else {
    echo "✗ Backend log file not writable: $logFile\n";
}

echo "\n8. Testing JSON configuration files...\n";

$configFiles = [
    'attack_profiles.json',
    'proxy_pool.json',
    'ua_pool.json'
];

foreach ($configFiles as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $data = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "✓ Valid JSON: $file\n";
        } else {
            echo "✗ Invalid JSON: $file - " . json_last_error_msg() . "\n";
        }
    } else {
        echo "✗ File not found: $file\n";
    }
}

echo "\n=== TEST COMPLETED ===\n";
echo "Check the logs directory for generated files.\n";
echo "All core components have been tested.\n";

?>

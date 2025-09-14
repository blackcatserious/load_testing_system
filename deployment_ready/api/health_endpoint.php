<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'database.php';

function logMessage($message) {
    $logFile = __DIR__ . '/../logs/backend.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] HEALTH_ENDPOINT: $message" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    logMessage("ERROR: Invalid method: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode([
        'status' => 'error',
        'message' => 'Only GET method allowed'
    ]);
    exit();
}

try {
    $db = new Database();
    $pdo = $db->getPDO();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_groups FROM groups");
    $stmt->execute();
    $totalGroups = $stmt->fetch()['total_groups'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_runs FROM runs");
    $stmt->execute();
    $totalRuns = $stmt->fetch()['total_runs'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as active_groups FROM groups WHERE status = 'running'");
    $stmt->execute();
    $activeGroups = $stmt->fetch()['active_groups'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as active_runs FROM runs WHERE status = 'running'");
    $stmt->execute();
    $activeRuns = $stmt->fetch()['active_runs'];
    
    $reportsDir = __DIR__ . '/../reports';
    $reportsCount = 0;
    if (is_dir($reportsDir)) {
        $files = glob($reportsDir . '/*.{json,csv}', GLOB_BRACE);
        $reportsCount = count($files);
    }
    
    $logsDir = '/home/ftcceelg/load_testing_system/logs';
    $logsExist = is_dir($logsDir) && file_exists($logsDir . '/backend.log');
    
    $currentTime = time();
    $uptime = $currentTime - strtotime('2025-08-03 00:00:00'); // System start time
    
    $healthData = [
        'status' => 'healthy',
        'version' => '1.1.0',
        'timestamp' => date('Y-m-d H:i:s'),
        'uptime_seconds' => $uptime,
        'database' => [
            'connected' => true,
            'total_groups' => $totalGroups,
            'total_runs' => $totalRuns,
            'active_groups' => $activeGroups,
            'active_runs' => $activeRuns
        ],
        'filesystem' => [
            'reports_directory' => is_dir($reportsDir),
            'reports_count' => $reportsCount,
            'logs_directory' => is_dir($logsDir),
            'backend_log_exists' => $logsExist
        ],
        'system' => [
            'php_version' => phpversion(),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true)
        ]
    ];
    
    logMessage("Health check requested - Status: healthy, Active groups: $activeGroups, Active runs: $activeRuns");
    
    echo json_encode([
        'status' => 'success',
        'data' => $healthData
    ]);
    
} catch (Exception $e) {
    logMessage("ERROR during health check: " . $e->getMessage());
    
    echo json_encode([
        'status' => 'error',
        'message' => 'Health check failed',
        'error' => $e->getMessage(),
        'data' => [
            'status' => 'unhealthy',
            'version' => '1.1.0',
            'timestamp' => date('Y-m-d H:i:s'),
            'database' => [
                'connected' => false,
                'error' => $e->getMessage()
            ]
        ]
    ]);
}
?>

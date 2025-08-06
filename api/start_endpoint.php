<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'database.php';

function logMessage($message) {
    $logFile = '/home/ftcceelg/load_testing_system/logs/backend.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] START_ENDPOINT: $message" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logMessage("ERROR: Invalid method: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode([
        'status' => 'error',
        'message' => 'Only POST method allowed'
    ]);
    exit();
}

try {
    $db = new Database();
    $pdo = $db->getPDO();
} catch (Exception $e) {
    logMessage("ERROR: Database connection failed - " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed'
    ]);
    exit();
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

logMessage("Start test request - Data: " . json_encode($data));

$targetUrl = $data['target_url'] ?? '';
$profileId = $data['profile_id'] ?? 'default';
$threads = $data['threads'] ?? 1;
$duration = $data['duration'] ?? 60;
$engine = $data['engine'] ?? 'playwright';
$behaviorProfileId = $data['behavior_profile_id'] ?? 'scanner';

if (empty($targetUrl)) {
    logMessage("ERROR: No target URL provided");
    echo json_encode([
        'status' => 'error',
        'message' => 'Target URL is required'
    ]);
    exit();
}

try {
    $groupId = 'group_' . uniqid();
    $runId = 'run_' . uniqid();
    
    $db->insertGroup($groupId, [$targetUrl], $profileId, $threads, $duration, $engine, $behaviorProfileId);
    
    $db->insertRun($runId, $groupId, $targetUrl);
    
    $reportsDir = '/home/ftcceelg/load_testing_system/reports';
    if (!is_dir($reportsDir)) {
        mkdir($reportsDir, 0755, true);
    }
    
    $runReport = [
        'run_id' => $runId,
        'group_id' => $groupId,
        'target' => $targetUrl,
        'profile_id' => $profileId,
        'threads' => $threads,
        'duration' => $duration,
        'engine' => $engine,
        'behavior_profile_id' => $behaviorProfileId,
        'started_at' => date('Y-m-d H:i:s'),
        'status' => 'running'
    ];
    
    $jsonFile = $reportsDir . '/' . $runId . '_' . date('Y-m-d_H-i-s') . '.json';
    file_put_contents($jsonFile, json_encode($runReport, JSON_PRETTY_PRINT));
    
    $csvFile = $reportsDir . '/' . $runId . '_' . date('Y-m-d_H-i-s') . '.csv';
    $csvData = "run_id,group_id,target,profile_id,threads,duration,engine,behavior_profile_id,started_at,status\n";
    $csvData .= "$runId,$groupId,$targetUrl,$profileId,$threads,$duration,$engine,$behaviorProfileId," . date('Y-m-d H:i:s') . ",running\n";
    file_put_contents($csvFile, $csvData);
    
    logMessage("Individual test started successfully: $runId for target: $targetUrl");
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'run_id' => $runId,
            'group_id' => $groupId,
            'target_url' => $targetUrl,
            'status' => 'started',
            'message' => 'Test started successfully'
        ]
    ]);
    
} catch (Exception $e) {
    logMessage("ERROR starting test: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to start test: ' . $e->getMessage()
    ]);
}
?>

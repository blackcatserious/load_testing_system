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
require_once 'stealth_engine.php';
require_once 'client_profile.php';
require_once 'tls_profile.php';
require_once 'proxy_manager.php';

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
$targets = $data['targets'] ?? [];
$profileId = $data['profile_id'] ?? 'default';
$threads = $data['threads'] ?? 500; // Increased default
$duration = $data['duration'] ?? 3600; // Default to 1 hour
$engine = $data['engine'] ?? 'auto-bypass';
$behaviorProfileId = $data['behavior_profile_id'] ?? 'power';

$stealthProfile = $data['stealth_profile'] ?? 'high';
$attackMethod = $data['attack_method'] ?? 'auto-bypass';
$proxyProfile = $data['proxy_profile'] ?? 'rotating';
$userAgentRotation = $data['user_agent_rotation'] ?? true;
$ja3Rotation = $data['ja3_rotation'] ?? true;
$tlsRotation = $data['tls_rotation'] ?? true;
$proxyRotation = $data['proxy_rotation'] ?? true;
$spoofHeaders = $data['spoof_headers'] ?? true;

if (!empty($targetUrl)) {
    $targets = [$targetUrl];
} elseif (empty($targets)) {
    logMessage("ERROR: No target URL or targets provided");
    echo json_encode([
        'status' => 'error',
        'message' => 'Target URL or targets array is required'
    ]);
    exit();
}

logMessage("Processing " . count($targets) . " targets with unlimited capacity");

try {
    $groupId = 'group_' . uniqid();
    $runIds = [];
    $stealthSessionIds = [];
    
    $stealthEngine = new StealthEngine();
    $clientProfile = new ClientProfile();
    $tlsProfile = new TLSProfile();
    $proxyManager = new ProxyManager();
    
    $db->insertGroup($groupId, $targets, $profileId, $threads, $duration, $engine, $behaviorProfileId);
    
    foreach ($targets as $targetUrl) {
        $runId = 'run_' . uniqid();
        $runIds[] = $runId;
        
        $stealthSessionId = $stealthEngine->createSession([
            'stealth_level' => $stealthProfile,
            'user_agent_rotation' => $userAgentRotation,
            'ja3_rotation' => $ja3Rotation,
            'tls_rotation' => $tlsRotation,
            'proxy_rotation' => $proxyRotation,
            'spoof_headers' => $spoofHeaders,
            'attack_method' => $attackMethod
        ]);
        $stealthSessionIds[] = $stealthSessionId;
        
        $currentUA = $clientProfile->getCurrentUserAgent();
        $currentJA3 = $tlsProfile->getCurrentProfile();
        $currentProxy = $proxyManager->getActiveProxy();
        
        logMessage("Stealth session created: $stealthSessionId for target: $targetUrl");
        
        $db->insertStealthSession($stealthSessionId, $runId, [
            'stealth_profile' => $stealthProfile,
            'attack_method' => $attackMethod,
            'proxy_profile' => $proxyProfile,
            'user_agent' => $currentUA,
            'ja3_fingerprint' => $currentJA3['ja3_fingerprint'],
            'tls_config' => json_encode($currentJA3),
            'proxy_config' => json_encode($currentProxy),
            'rotation_settings' => json_encode([
                'ua_rotation' => $userAgentRotation,
                'ja3_rotation' => $ja3Rotation,
                'tls_rotation' => $tlsRotation,
                'proxy_rotation' => $proxyRotation,
                'spoof_headers' => $spoofHeaders
            ])
        ]);
        
        $db->insertRun($runId, $groupId, $targetUrl);
    }
    
    $reportsDir = '/home/ftcceelg/load_testing_system/reports';
    if (!is_dir($reportsDir)) {
        mkdir($reportsDir, 0755, true);
    }
    
    $allRunReports = [];
    foreach ($runIds as $index => $runId) {
        $targetUrl = $targets[$index];
        $stealthSessionId = $stealthSessionIds[$index];
        
        $runReport = [
            'run_id' => $runId,
            'group_id' => $groupId,
            'target' => $targetUrl,
            'profile_id' => $profileId,
            'threads' => $threads,
            'duration' => $duration,
            'engine' => $engine,
            'behavior_profile_id' => $behaviorProfileId,
            'stealth_session_id' => $stealthSessionId,
            'stealth_config' => [
                'stealth_profile' => $stealthProfile,
                'attack_method' => $attackMethod,
                'proxy_profile' => $proxyProfile,
                'user_agent_rotation' => $userAgentRotation,
                'ja3_rotation' => $ja3Rotation,
                'tls_rotation' => $tlsRotation,
                'proxy_rotation' => $proxyRotation,
                'spoof_headers' => $spoofHeaders
            ],
            'started_at' => date('Y-m-d H:i:s'),
            'status' => 'running'
        ];
        $allRunReports[] = $runReport;
        
        $jsonFile = $reportsDir . '/' . $runId . '_' . date('Y-m-d_H-i-s') . '.json';
        file_put_contents($jsonFile, json_encode($runReport, JSON_PRETTY_PRINT));
    }
    
    $groupReport = [
        'group_id' => $groupId,
        'targets' => $targets,
        'target_count' => count($targets),
        'run_ids' => $runIds,
        'stealth_session_ids' => $stealthSessionIds,
        'profile_id' => $profileId,
        'threads' => $threads,
        'duration' => $duration,
        'engine' => $engine,
        'behavior_profile_id' => $behaviorProfileId,
        'stealth_config' => [
            'stealth_profile' => $stealthProfile,
            'attack_method' => $attackMethod,
            'proxy_profile' => $proxyProfile,
            'user_agent_rotation' => $userAgentRotation,
            'ja3_rotation' => $ja3Rotation,
            'tls_rotation' => $tlsRotation,
            'proxy_rotation' => $proxyRotation,
            'spoof_headers' => $spoofHeaders
        ],
        'started_at' => date('Y-m-d H:i:s'),
        'status' => 'running',
        'runs' => $allRunReports
    ];
    
    $groupJsonFile = $reportsDir . '/' . $groupId . '_' . date('Y-m-d_H-i-s') . '.json';
    file_put_contents($groupJsonFile, json_encode($groupReport, JSON_PRETTY_PRINT));
    
    $csvFile = $reportsDir . '/' . $groupId . '_' . date('Y-m-d_H-i-s') . '.csv';
    $csvData = "run_id,group_id,target,profile_id,threads,duration,engine,behavior_profile_id,stealth_session_id,stealth_profile,attack_method,proxy_profile,started_at,status\n";
    foreach ($runIds as $index => $runId) {
        $targetUrl = $targets[$index];
        $stealthSessionId = $stealthSessionIds[$index];
        $csvData .= "$runId,$groupId,$targetUrl,$profileId,$threads,$duration,$engine,$behaviorProfileId,$stealthSessionId,$stealthProfile,$attackMethod,$proxyProfile," . date('Y-m-d H:i:s') . ",running\n";
    }
    file_put_contents($csvFile, $csvData);
    
    logMessage("Bulk test started successfully: $groupId with " . count($targets) . " targets and " . count($runIds) . " runs");
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'group_id' => $groupId,
            'run_ids' => $runIds,
            'targets' => $targets,
            'target_count' => count($targets),
            'stealth_session_ids' => $stealthSessionIds,
            'stealth_config' => [
                'stealth_profile' => $stealthProfile,
                'attack_method' => $attackMethod,
                'proxy_profile' => $proxyProfile,
                'user_agent_rotation' => $userAgentRotation,
                'ja3_rotation' => $ja3Rotation,
                'tls_rotation' => $tlsRotation,
                'proxy_rotation' => $proxyRotation,
                'spoof_headers' => $spoofHeaders
            ],
            'status' => 'started',
            'message' => 'Bulk test started successfully with unlimited targets and stealth capabilities'
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

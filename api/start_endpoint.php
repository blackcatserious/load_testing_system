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
$profileId = $data['profile_id'] ?? 'default';
$threads = $data['threads'] ?? 1;
$duration = $data['duration'] ?? 60;
$engine = $data['engine'] ?? 'playwright';
$behaviorProfileId = $data['behavior_profile_id'] ?? 'scanner';

$stealthProfile = $data['stealth_profile'] ?? 'medium';
$attackMethod = $data['attack_method'] ?? 'standard';
$proxyProfile = $data['proxy_profile'] ?? 'rotating';
$userAgentRotation = $data['user_agent_rotation'] ?? true;
$ja3Rotation = $data['ja3_rotation'] ?? true;
$tlsRotation = $data['tls_rotation'] ?? true;
$proxyRotation = $data['proxy_rotation'] ?? true;
$spoofHeaders = $data['spoof_headers'] ?? true;

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
    
    $stealthEngine = new StealthEngine();
    $clientProfile = new ClientProfile();
    $tlsProfile = new TLSProfile();
    $proxyManager = new ProxyManager();
    
    $stealthSessionId = $stealthEngine->createSession([
        'stealth_level' => $stealthProfile,
        'user_agent_rotation' => $userAgentRotation,
        'ja3_rotation' => $ja3Rotation,
        'tls_rotation' => $tlsRotation,
        'proxy_rotation' => $proxyRotation,
        'spoof_headers' => $spoofHeaders,
        'attack_method' => $attackMethod
    ]);
    
    $currentUA = $clientProfile->getCurrentUserAgent();
    $currentJA3 = $tlsProfile->getCurrentProfile();
    $currentProxy = $proxyManager->getActiveProxy();
    
    logMessage("Stealth session created: $stealthSessionId with UA: " . substr($currentUA, 0, 50) . "...");
    logMessage("JA3 profile: " . $currentJA3['name'] . ", Proxy: " . $currentProxy['ip'] . ":" . $currentProxy['port']);
    
    $db->insertGroup($groupId, [$targetUrl], $profileId, $threads, $duration, $engine, $behaviorProfileId);
    
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
        'stealth_session_id' => $stealthSessionId,
        'stealth_config' => [
            'stealth_profile' => $stealthProfile,
            'attack_method' => $attackMethod,
            'proxy_profile' => $proxyProfile,
            'user_agent_rotation' => $userAgentRotation,
            'ja3_rotation' => $ja3Rotation,
            'tls_rotation' => $tlsRotation,
            'proxy_rotation' => $proxyRotation,
            'spoof_headers' => $spoofHeaders,
            'initial_ua' => substr($currentUA, 0, 100),
            'initial_ja3' => $currentJA3['name'],
            'initial_proxy' => $currentProxy['ip'] . ':' . $currentProxy['port']
        ],
        'started_at' => date('Y-m-d H:i:s'),
        'status' => 'running'
    ];
    
    $jsonFile = $reportsDir . '/' . $runId . '_' . date('Y-m-d_H-i-s') . '.json';
    file_put_contents($jsonFile, json_encode($runReport, JSON_PRETTY_PRINT));
    
    $csvFile = $reportsDir . '/' . $runId . '_' . date('Y-m-d_H-i-s') . '.csv';
    $csvData = "run_id,group_id,target,profile_id,threads,duration,engine,behavior_profile_id,stealth_session_id,stealth_profile,attack_method,proxy_profile,started_at,status\n";
    $csvData .= "$runId,$groupId,$targetUrl,$profileId,$threads,$duration,$engine,$behaviorProfileId,$stealthSessionId,$stealthProfile,$attackMethod,$proxyProfile," . date('Y-m-d H:i:s') . ",running\n";
    file_put_contents($csvFile, $csvData);
    
    logMessage("Individual test started successfully: $runId for target: $targetUrl");
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'run_id' => $runId,
            'group_id' => $groupId,
            'target_url' => $targetUrl,
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
            'initial_stealth_state' => [
                'user_agent' => substr($currentUA, 0, 100) . '...',
                'ja3_profile' => $currentJA3['name'],
                'proxy' => $currentProxy['ip'] . ':' . $currentProxy['port']
            ],
            'status' => 'started',
            'message' => 'Test started successfully with stealth capabilities'
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

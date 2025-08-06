<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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
    $logEntry = "[$timestamp] GROUP_RUNS_ENDPOINT: $message" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

try {
    $db = new Database();
} catch (Exception $e) {
    logMessage("ERROR: Database connection failed - " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed'
    ]);
    exit();
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $groupId = $_GET['group_id'] ?? '';
    
    logMessage("GET request - Action: $action, Group ID: $groupId");
    
    if ($action === 'status' && $groupId) {
        try {
            $group = $db->getGroup($groupId);
            
            if (!$group) {
                logMessage("ERROR: Group not found: $groupId");
                echo json_encode([
                    'success' => false,
                    'error' => 'Group not found'
                ]);
                exit();
            }
            
            $runs = $db->getGroupRuns($groupId);
            
            logMessage("Group status requested for: $groupId - Status: " . $group['status'] . ", Runs: " . count($runs));
            
            echo json_encode([
                'success' => true,
                'group_id' => $groupId,
                'status' => $group['status'],
                'runs' => $runs,
                'group_info' => $group
            ]);
            
        } catch (Exception $e) {
            logMessage("ERROR getting group status: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Database error'
            ]);
        }
        exit();
    }
    
    if ($action === 'list') {
        try {
            $groups = $db->getAllGroups(50);
            
            logMessage("Groups list requested - Found " . count($groups) . " groups");
            
            echo json_encode([
                'success' => true,
                'groups' => $groups,
                'count' => count($groups),
                'version' => '1.1.0'
            ]);
            
        } catch (Exception $e) {
            logMessage("ERROR listing groups: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Database error'
            ]);
        }
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $data['action'] ?? '';
    
    logMessage("POST request - Action: $action, Data: " . json_encode($data));
    
    switch ($action) {
        case 'start_group':
            $targets = $data['targets'] ?? [];
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
            
            if (empty($targets)) {
                logMessage("ERROR: No targets provided for group start");
                echo json_encode([
                    'success' => false,
                    'error' => 'No targets provided'
                ]);
                exit();
            }
            
            $groupId = 'group_' . uniqid();
            
            try {
                $stealthEngine = new StealthEngine();
                $clientProfile = new ClientProfile();
                $tlsProfile = new TLSProfile();
                $proxyManager = new ProxyManager();
                
                $groupStealthSessionId = $stealthEngine->createSession([
                    'stealth_level' => $stealthProfile,
                    'user_agent_rotation' => $userAgentRotation,
                    'ja3_rotation' => $ja3Rotation,
                    'tls_rotation' => $tlsRotation,
                    'proxy_rotation' => $proxyRotation,
                    'spoof_headers' => $spoofHeaders,
                    'attack_method' => $attackMethod
                ]);
                
                logMessage("Group stealth session created: $groupStealthSessionId for group: $groupId");
                
                $db->insertGroup($groupId, $targets, $profileId, $threads, $duration, $engine, $behaviorProfileId);
                
                logMessage("Starting group: $groupId with " . count($targets) . " targets and stealth profile: $stealthProfile");
                
                $reportsDir = '/home/ftcceelg/load_testing_system/reports';
                if (!is_dir($reportsDir)) {
                    mkdir($reportsDir, 0755, true);
                }
                
                $runs = [];
                $stealthConfigs = [];
                
                foreach ($targets as $target) {
                    $runId = 'run_' . uniqid();
                    
                    $runStealthSessionId = $stealthEngine->createSession([
                        'stealth_level' => $stealthProfile,
                        'user_agent_rotation' => $userAgentRotation,
                        'ja3_rotation' => $ja3Rotation,
                        'tls_rotation' => $tlsRotation,
                        'proxy_rotation' => $proxyRotation,
                        'spoof_headers' => $spoofHeaders,
                        'attack_method' => $attackMethod,
                        'parent_session' => $groupStealthSessionId
                    ]);
                    
                    $currentUA = $clientProfile->getCurrentUserAgent();
                    $currentJA3 = $tlsProfile->getCurrentProfile();
                    $currentProxy = $proxyManager->getActiveProxy();
                    
                    $db->insertRun($runId, $groupId, $target);
                    
                    $db->insertStealthSession($runStealthSessionId, $runId, [
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
                        ]),
                        'parent_session' => $groupStealthSessionId
                    ]);
                    
                    $stealthConfig = [
                        'stealth_session_id' => $runStealthSessionId,
                        'user_agent' => substr($currentUA, 0, 100),
                        'ja3_profile' => $currentJA3['name'],
                        'proxy' => $currentProxy['ip'] . ':' . $currentProxy['port']
                    ];
                    
                    $stealthConfigs[$runId] = $stealthConfig;
                    
                    $runs[] = [
                        'run_id' => $runId,
                        'target' => $target,
                        'status' => 'started',
                        'group_id' => $groupId,
                        'stealth_session_id' => $runStealthSessionId,
                        'stealth_config' => $stealthConfig
                    ];
                    
                    logMessage("Run $runId created with stealth session: $runStealthSessionId for target: $target");
                
                $runReport = [
                    'run_id' => $runId,
                    'group_id' => $groupId,
                    'target' => $target,
                    'profile_id' => $profileId,
                    'threads' => $threads,
                    'duration' => $duration,
                    'engine' => $engine,
                    'behavior_profile_id' => $behaviorProfileId,
                    'stealth_session_id' => $stealthConfigs[$runId]['stealth_session_id'],
                    'stealth_config' => [
                        'stealth_profile' => $stealthProfile,
                        'attack_method' => $attackMethod,
                        'proxy_profile' => $proxyProfile,
                        'user_agent_rotation' => $userAgentRotation,
                        'ja3_rotation' => $ja3Rotation,
                        'tls_rotation' => $tlsRotation,
                        'proxy_rotation' => $proxyRotation,
                        'spoof_headers' => $spoofHeaders,
                        'initial_ua' => $stealthConfigs[$runId]['user_agent'],
                        'initial_ja3' => $stealthConfigs[$runId]['ja3_profile'],
                        'initial_proxy' => $stealthConfigs[$runId]['proxy']
                    ],
                    'started_at' => date('Y-m-d H:i:s'),
                    'status' => 'running'
                ];
                
                $jsonFile = $reportsDir . '/' . $runId . '_' . date('Y-m-d_H-i-s') . '.json';
                file_put_contents($jsonFile, json_encode($runReport, JSON_PRETTY_PRINT));
                
                $csvFile = $reportsDir . '/' . $runId . '_' . date('Y-m-d_H-i-s') . '.csv';
                $csvData = "run_id,group_id,target,profile_id,threads,duration,engine,behavior_profile_id,stealth_session_id,stealth_profile,attack_method,proxy_profile,started_at,status\n";
                $csvData .= "$runId,$groupId,$target,$profileId,$threads,$duration,$engine,$behaviorProfileId," . $stealthConfigs[$runId]['stealth_session_id'] . ",$stealthProfile,$attackMethod,$proxyProfile," . date('Y-m-d H:i:s') . ",running\n";
                file_put_contents($csvFile, $csvData);
            }
            
            $groupReport = [
                'group_id' => $groupId,
                'targets' => $targets,
                'profile_id' => $profileId,
                'threads' => $threads,
                'duration' => $duration,
                'engine' => $engine,
                'behavior_profile_id' => $behaviorProfileId,
                'group_stealth_session_id' => $groupStealthSessionId,
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
                'runs' => $runs,
                'stealth_sessions' => $stealthConfigs
            ];
            
            $groupJsonFile = $reportsDir . '/group_' . $groupId . '_' . date('Y-m-d_H-i-s') . '.json';
            file_put_contents($groupJsonFile, json_encode($groupReport, JSON_PRETTY_PRINT));
            
            $groupCsvFile = $reportsDir . '/group_' . $groupId . '_' . date('Y-m-d_H-i-s') . '.csv';
            $groupCsvData = "group_id,targets_count,profile_id,threads,duration,engine,behavior_profile_id,group_stealth_session_id,stealth_profile,attack_method,proxy_profile,started_at,status\n";
            $groupCsvData .= "$groupId," . count($targets) . ",$profileId,$threads,$duration,$engine,$behaviorProfileId,$groupStealthSessionId,$stealthProfile,$attackMethod,$proxyProfile," . date('Y-m-d H:i:s') . ",running\n";
            file_put_contents($groupCsvFile, $groupCsvData);
            
                logMessage("Group $groupId started successfully with " . count($runs) . " runs");
                
                echo json_encode([
                    'success' => true,
                    'group_id' => $groupId,
                    'group_stealth_session_id' => $groupStealthSessionId,
                    'runs' => $runs,
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
                    'stealth_sessions' => $stealthConfigs,
                    'message' => 'Group started successfully with stealth capabilities'
                ]);
                
            } catch (Exception $e) {
                logMessage("ERROR starting group: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'error' => 'Database error: ' . $e->getMessage()
                ]);
            }
            exit();
            
        case 'stop_group':
            $groupId = $data['group_id'] ?? '';
            
            if (empty($groupId)) {
                logMessage("ERROR: No group ID provided for group stop");
                echo json_encode([
                    'success' => false,
                    'error' => 'No group ID provided'
                ]);
                exit();
            }
            
            try {
                $db->updateGroupStatus($groupId, 'stopped');
                
                $db->updateRunsStatus($groupId, 'stopped');
                
                logMessage("Group $groupId stopped successfully");
                
                echo json_encode([
                    'success' => true,
                    'group_id' => $groupId,
                    'message' => 'Group stopped successfully'
                ]);
                
            } catch (Exception $e) {
                logMessage("ERROR stopping group: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'error' => 'Database error'
                ]);
            }
            exit();
            
        default:
            logMessage("ERROR: Unknown action: $action");
            echo json_encode([
                'success' => false,
                'error' => 'Unknown action'
            ]);
            exit();
    }
}

logMessage("ERROR: Unsupported method: " . $_SERVER['REQUEST_METHOD']);
echo json_encode([
    'success' => false,
    'error' => 'Method not allowed'
]);
?>

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
require_once 'stealth_engine_class.php';
require_once 'client_profile_class.php';
require_once 'tls_profile_class.php';
require_once 'proxy_manager_class.php';
require_once 'continuous_adaptive_orchestrator.php';

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
            
        case 'update_target_status':
            $groupId = $data['group_id'] ?? '';
            $runId = $data['run_id'] ?? '';
            $targetUrl = $data['target_url'] ?? '';
            $targetStatus = $data['target_status'] ?? 'active';
            $targetDisabled = $data['target_disabled'] ?? false;
            $successDetection = $data['success_detection'] ?? [];
            
            if (empty($groupId) || empty($runId)) {
                logMessage("ERROR: Missing group_id or run_id for target status update");
                echo json_encode([
                    'success' => false,
                    'error' => 'Missing group_id or run_id'
                ]);
                exit();
            }
            
            try {
                $pdo = $db->getPDO();
                $stmt = $pdo->prepare("UPDATE runs SET target_status = ? WHERE run_id = ? AND group_id = ?");
                $stmt->execute([$targetStatus, $runId, $groupId]);
                
                logMessage("Target status updated: Group $groupId, Run $runId -> target_status: $targetStatus");
                
                if ($targetDisabled) {
                    logMessage("SUCCESS DETECTION: Target disabled for group $groupId, run $runId, target: $targetUrl - permanent failure achieved");
                    
                    $reportData = [
                        'group_id' => $groupId,
                        'run_id' => $runId,
                        'target_url' => $targetUrl,
                        'timestamp' => date('Y-m-d H:i:s'),
                        'target_status' => 'disabled',
                        'success_detection' => true,
                        'permanent_failure_achieved' => true,
                        'escalation_successful' => true,
                        'success_metrics' => $successDetection
                    ];
                    
                    $reportsDir = '/home/ftcceelg/load_testing_system/reports';
                    if (!is_dir($reportsDir)) {
                        mkdir($reportsDir, 0755, true);
                    }
                    
                    $reportFile = $reportsDir . "/group_{$groupId}_run_{$runId}_SUCCESS_" . date('Y-m-d_H-i-s') . ".json";
                    file_put_contents($reportFile, json_encode($reportData, JSON_PRETTY_PRINT));
                    
                    logMessage("SUCCESS REPORT: Generated success report file: $reportFile");
                }
                
                echo json_encode([
                    'success' => true,
                    'group_id' => $groupId,
                    'run_id' => $runId,
                    'target_status' => $targetStatus,
                    'target_disabled' => $targetDisabled,
                    'message' => 'Target status updated successfully'
                ]);
                
            } catch (Exception $e) {
                logMessage("ERROR updating target status: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'error' => 'Database error'
                ]);
            }
            exit();
            
        case 'generate_group_report':
            $groupId = $data['group_id'] ?? '';
            
            if (empty($groupId)) {
                logMessage("ERROR: Missing group_id for group report generation");
                echo json_encode([
                    'success' => false,
                    'error' => 'Missing group_id'
                ]);
                exit();
            }
            
            try {
                $pdo = $db->getPDO();
                
                $stmt = $pdo->prepare("SELECT * FROM groups WHERE group_id = ?");
                $stmt->execute([$groupId]);
                $group = $stmt->fetch();
                
                if (!$group) {
                    logMessage("ERROR: Group not found for report: $groupId");
                    echo json_encode([
                        'success' => false,
                        'error' => 'Group not found'
                    ]);
                    exit();
                }
                
                $stmt = $pdo->prepare("SELECT * FROM runs WHERE group_id = ?");
                $stmt->execute([$groupId]);
                $runs = $stmt->fetchAll();
                
                $disabledTargets = [];
                $activeTargets = [];
                
                foreach ($runs as $run) {
                    if (($run['target_status'] ?? 'active') === 'disabled') {
                        $disabledTargets[] = $run['target_url'];
                    } else {
                        $activeTargets[] = $run['target_url'];
                    }
                }
                
                $groupReportData = [
                    'group_id' => $groupId,
                    'targets' => json_decode($group['targets'], true),
                    'profile_id' => $group['profile_id'],
                    'threads' => $group['threads'],
                    'duration' => $group['duration'],
                    'engine' => $group['engine'],
                    'behavior_profile_id' => $group['behavior_profile_id'],
                    'status' => $group['status'],
                    'started_at' => $group['started_at'],
                    'finished_at' => $group['finished_at'],
                    'generated_at' => date('Y-m-d H:i:s'),
                    'unlimited_mode_used' => ($group['threads'] > 500 || $group['duration'] > 10800),
                    'success_summary' => [
                        'total_targets' => count($runs),
                        'disabled_targets' => count($disabledTargets),
                        'active_targets' => count($activeTargets),
                        'success_rate' => count($runs) > 0 ? (count($disabledTargets) / count($runs)) * 100 : 0
                    ],
                    'disabled_targets' => $disabledTargets,
                    'active_targets' => $activeTargets,
                    'runs' => $runs,
                    'stealth_features' => [
                        'proxy_rotation' => true,
                        'ja3_fingerprinting' => true,
                        'user_agent_rotation' => true,
                        'tls_profile_rotation' => true,
                        'behavior_emulation' => true,
                        'unlimited_escalation' => true
                    ]
                ];
                
                $reportsDir = '/home/ftcceelg/load_testing_system/reports';
                if (!is_dir($reportsDir)) {
                    mkdir($reportsDir, 0755, true);
                }
                
                $reportFile = $reportsDir . "/group_{$groupId}_FINAL_" . date('Y-m-d_H-i-s') . ".json";
                file_put_contents($reportFile, json_encode($groupReportData, JSON_PRETTY_PRINT));
                
                $csvFile = $reportsDir . "/group_{$groupId}_FINAL_" . date('Y-m-d_H-i-s') . ".csv";
                $csvData = "group_id,total_targets,disabled_targets,active_targets,success_rate,unlimited_mode_used,generated_at\n";
                $csvData .= "{$groupId}," . count($runs) . "," . count($disabledTargets) . "," . count($activeTargets) . "," . $groupReportData['success_summary']['success_rate'] . "," . ($groupReportData['unlimited_mode_used'] ? 'true' : 'false') . "," . date('Y-m-d H:i:s') . "\n";
                file_put_contents($csvFile, $csvData);
                
                logMessage("GROUP REPORT GENERATED: Created final group report files: $reportFile and $csvFile");
                
                echo json_encode([
                    'success' => true,
                    'group_id' => $groupId,
                    'report_file' => $reportFile,
                    'csv_file' => $csvFile,
                    'success_summary' => $groupReportData['success_summary'],
                    'message' => 'Group report generated successfully'
                ]);
                
            } catch (Exception $e) {
                logMessage("ERROR generating group report: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'error' => 'Database error'
                ]);
            }
            exit();
            
        case 'start_continuous_adaptive':
            $targets = $data['targets'] ?? [];
            $profileId = $data['profile_id'] ?? 'continuous-adaptive';
            $threads = $data['threads'] ?? 500;
            $duration = $data['duration'] ?? -1;
            $engine = $data['engine'] ?? 'auto-bypass';
            $behaviorProfileId = $data['behavior_profile_id'] ?? 'power';
            
            $stealthProfile = $data['stealth_profile'] ?? 'maximum';
            $attackMethod = $data['attack_method'] ?? 'continuous-adaptive';
            $proxyProfile = $data['proxy_profile'] ?? 'rotating';
            $userAgentRotation = $data['user_agent_rotation'] ?? true;
            $ja3Rotation = $data['ja3_rotation'] ?? true;
            $tlsRotation = $data['tls_rotation'] ?? true;
            $proxyRotation = $data['proxy_rotation'] ?? true;
            $spoofHeaders = $data['spoof_headers'] ?? true;
            $escalation = $data['escalation'] ?? true;
            $concurrents = $data['concurrents'] ?? 'unlimited';
            
            if (empty($targets)) {
                logMessage("ERROR: No targets provided for continuous adaptive start");
                echo json_encode([
                    'success' => false,
                    'error' => 'No targets provided'
                ]);
                exit();
            }
            
            $groupId = 'continuous_adaptive_' . uniqid();
            
            try {
                $orchestratorConfig = [
                    'threads' => $threads,
                    'duration' => $duration,
                    'stealth_enabled' => true,
                    'proxy_rotation' => $proxyRotation,
                    'ua_rotation' => $userAgentRotation,
                    'ja3_rotation' => $ja3Rotation,
                    'tls_rotation' => $tlsRotation,
                    'escalation_enabled' => $escalation,
                    'max_threads' => 20000,
                    'evolution_interval' => 60,
                    'success_threshold' => 0.75,
                    'behavior_profile' => $behaviorProfileId,
                    'stealth_profile' => $stealthProfile
                ];
                
                $orchestrator = new ContinuousAdaptiveOrchestrator($db, $orchestratorConfig);
                
                $db->insertGroup($groupId, $targets, $profileId, $threads, $duration, $engine, $behaviorProfileId);
                
                logMessage("Starting continuous adaptive attack: $groupId with " . count($targets) . " targets, unlimited mode: $concurrents");
                
                $reportsDir = '/home/ftcceelg/load_testing_system/reports';
                if (!is_dir($reportsDir)) {
                    mkdir($reportsDir, 0755, true);
                }
                
                $orchestrator->start($targets, $groupId, $behaviorProfileId);
                
                logMessage("Continuous adaptive orchestrator started successfully for group: $groupId");
                
                echo json_encode([
                    'success' => true,
                    'group_id' => $groupId,
                    'mode' => 'continuous-adaptive',
                    'targets' => $targets,
                    'threads' => $threads,
                    'duration' => $duration === -1 ? 'infinite' : $duration,
                    'escalation' => $escalation,
                    'concurrents' => $concurrents,
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
                    'message' => 'Continuous adaptive attack started successfully with unlimited capabilities'
                ]);
                
            } catch (Exception $e) {
                logMessage("ERROR starting continuous adaptive attack: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to start continuous adaptive attack: ' . $e->getMessage()
                ]);
            }
            exit();
            
        case 'stop_continuous_adaptive':
            $groupId = $data['group_id'] ?? '';
            
            if (empty($groupId)) {
                logMessage("ERROR: No group ID provided for continuous adaptive stop");
                echo json_encode([
                    'success' => false,
                    'error' => 'No group ID provided'
                ]);
                exit();
            }
            
            try {
                $stopFile = "/tmp/stop_signal_$groupId.flag";
                file_put_contents($stopFile, time());
                
                $db->updateGroupStatus($groupId, 'stopped');
                $db->updateRunsStatus($groupId, 'stopped');
                
                logMessage("Continuous adaptive attack stopped for group: $groupId");
                
                $reportsDir = '/home/ftcceelg/load_testing_system/reports';
                $finalReportFile = $reportsDir . "/continuous_adaptive_{$groupId}_FINAL_" . date('Y-m-d_H-i-s') . ".json";
                
                $finalReport = [
                    'group_id' => $groupId,
                    'mode' => 'continuous-adaptive',
                    'stopped_at' => date('Y-m-d H:i:s'),
                    'stop_method' => 'manual',
                    'final_status' => 'stopped'
                ];
                
                if (is_dir($reportsDir)) {
                    file_put_contents($finalReportFile, json_encode($finalReport, JSON_PRETTY_PRINT));
                }
                
                echo json_encode([
                    'success' => true,
                    'group_id' => $groupId,
                    'mode' => 'continuous-adaptive',
                    'stopped_at' => date('Y-m-d H:i:s'),
                    'final_report' => $finalReportFile,
                    'message' => 'Continuous adaptive attack stopped successfully'
                ]);
                
            } catch (Exception $e) {
                logMessage("ERROR stopping continuous adaptive attack: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to stop continuous adaptive attack: ' . $e->getMessage()
                ]);
            }
            exit();
            
        case 'start_unlimited_groups':
            $targets = $data['targets'] ?? [];
            $groupCount = $data['group_count'] ?? 100;
            $threadsPerGroup = $data['threads_per_group'] ?? 10000;
            $launchInterval = $data['launch_interval'] ?? 1000;
            $attackStrategy = $data['attack_strategy'] ?? 'adaptive';
            $targetDistribution = $data['target_distribution'] ?? 'round_robin';
            $autoScaling = $data['auto_scaling'] ?? true;
            $dynamicThreads = $data['dynamic_threads'] ?? true;
            $failureRecovery = $data['failure_recovery'] ?? true;
            $stealthProfile = $data['stealth_profile'] ?? 'maximum';
            $attackMethod = $data['attack_method'] ?? 'unlimited';
            $proxyProfile = $data['proxy_profile'] ?? 'rotating';
            $userAgentRotation = $data['user_agent_rotation'] ?? true;
            $ja3Rotation = $data['ja3_rotation'] ?? true;
            $tlsRotation = $data['tls_rotation'] ?? true;
            $proxyRotation = $data['proxy_rotation'] ?? true;
            $spoofHeaders = $data['spoof_headers'] ?? true;
            
            if (empty($targets)) {
                logMessage("ERROR: No targets provided for unlimited groups start");
                echo json_encode([
                    'success' => false,
                    'error' => 'No targets provided'
                ]);
                exit();
            }
            
            try {
                $groupIds = [];
                $totalThreads = 0;
                $stealthEngine = new StealthEngine();
                $clientProfile = new ClientProfile();
                $tlsProfile = new TLSProfile();
                $proxyManager = new ProxyManager();
                
                $masterStealthSessionId = $stealthEngine->createSession([
                    'stealth_level' => $stealthProfile,
                    'user_agent_rotation' => $userAgentRotation,
                    'ja3_rotation' => $ja3Rotation,
                    'tls_rotation' => $tlsRotation,
                    'proxy_rotation' => $proxyRotation,
                    'spoof_headers' => $spoofHeaders,
                    'attack_method' => $attackMethod,
                    'unlimited_mode' => true
                ]);
                
                logMessage("Starting unlimited groups launch: $groupCount groups, $threadsPerGroup threads each, strategy: $attackStrategy");
                
                $reportsDir = '/home/ftcceelg/load_testing_system/reports';
                if (!is_dir($reportsDir)) {
                    mkdir($reportsDir, 0755, true);
                }
                
                for ($i = 0; $i < $groupCount; $i++) {
                    $groupId = 'unlimited_group_' . uniqid() . '_' . $i;
                    
                    if ($i > 0 && $launchInterval > 0) {
                        usleep($launchInterval * 1000);
                    }
                    
                    $groupStealthSessionId = $stealthEngine->createSession([
                        'stealth_level' => $stealthProfile,
                        'user_agent_rotation' => $userAgentRotation,
                        'ja3_rotation' => $ja3Rotation,
                        'tls_rotation' => $tlsRotation,
                        'proxy_rotation' => $proxyRotation,
                        'spoof_headers' => $spoofHeaders,
                        'attack_method' => $attackMethod,
                        'parent_session' => $masterStealthSessionId,
                        'group_index' => $i
                    ]);
                    
                    $db->insertGroup($groupId, $targets, 'unlimited', $threadsPerGroup, -1, 'auto-bypass', 'power');
                    
                    $runs = [];
                    foreach ($targets as $targetIndex => $target) {
                        $runId = 'unlimited_run_' . uniqid() . '_g' . $i . '_t' . $targetIndex;
                        
                        $runStealthSessionId = $stealthEngine->createSession([
                            'stealth_level' => $stealthProfile,
                            'user_agent_rotation' => $userAgentRotation,
                            'ja3_rotation' => $ja3Rotation,
                            'tls_rotation' => $tlsRotation,
                            'proxy_rotation' => $proxyRotation,
                            'spoof_headers' => $spoofHeaders,
                            'attack_method' => $attackMethod,
                            'parent_session' => $groupStealthSessionId,
                            'target_index' => $targetIndex
                        ]);
                        
                        $db->insertRun($runId, $groupId, $target);
                        
                        $runs[] = [
                            'run_id' => $runId,
                            'target' => $target,
                            'status' => 'started',
                            'group_id' => $groupId,
                            'stealth_session_id' => $runStealthSessionId,
                            'threads' => $threadsPerGroup
                        ];
                    }
                    
                    $groupIds[] = [
                        'group_id' => $groupId,
                        'group_stealth_session_id' => $groupStealthSessionId,
                        'runs' => $runs,
                        'threads' => $threadsPerGroup,
                        'targets_count' => count($targets),
                        'group_index' => $i
                    ];
                    
                    $totalThreads += $threadsPerGroup;
                    
                    $groupReport = [
                        'group_id' => $groupId,
                        'group_index' => $i,
                        'targets' => $targets,
                        'threads_per_group' => $threadsPerGroup,
                        'attack_strategy' => $attackStrategy,
                        'target_distribution' => $targetDistribution,
                        'auto_scaling' => $autoScaling,
                        'dynamic_threads' => $dynamicThreads,
                        'failure_recovery' => $failureRecovery,
                        'unlimited_mode' => true,
                        'master_stealth_session_id' => $masterStealthSessionId,
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
                        'runs' => $runs
                    ];
                    
                    $groupJsonFile = $reportsDir . '/unlimited_group_' . $groupId . '_' . date('Y-m-d_H-i-s') . '.json';
                    file_put_contents($groupJsonFile, json_encode($groupReport, JSON_PRETTY_PRINT));
                    
                    logMessage("Launched unlimited group $i/$groupCount: $groupId with $threadsPerGroup threads, stealth session: $groupStealthSessionId");
                }
                
                $unlimitedReport = [
                    'unlimited_launch_id' => 'unlimited_' . uniqid(),
                    'master_stealth_session_id' => $masterStealthSessionId,
                    'total_groups' => $groupCount,
                    'threads_per_group' => $threadsPerGroup,
                    'total_threads' => $totalThreads,
                    'launch_interval' => $launchInterval,
                    'attack_strategy' => $attackStrategy,
                    'target_distribution' => $targetDistribution,
                    'auto_scaling' => $autoScaling,
                    'dynamic_threads' => $dynamicThreads,
                    'failure_recovery' => $failureRecovery,
                    'targets' => $targets,
                    'started_at' => date('Y-m-d H:i:s'),
                    'status' => 'unlimited_running',
                    'groups' => $groupIds,
                    'stealth_config' => [
                        'stealth_profile' => $stealthProfile,
                        'attack_method' => $attackMethod,
                        'proxy_profile' => $proxyProfile,
                        'user_agent_rotation' => $userAgentRotation,
                        'ja3_rotation' => $ja3Rotation,
                        'tls_rotation' => $tlsRotation,
                        'proxy_rotation' => $proxyRotation,
                        'spoof_headers' => $spoofHeaders
                    ]
                ];
                
                $unlimitedReportFile = $reportsDir . '/unlimited_launch_' . date('Y-m-d_H-i-s') . '.json';
                file_put_contents($unlimitedReportFile, json_encode($unlimitedReport, JSON_PRETTY_PRINT));
                
                $unlimitedCsvFile = $reportsDir . '/unlimited_launch_' . date('Y-m-d_H-i-s') . '.csv';
                $csvData = "group_id,group_index,threads_per_group,targets_count,stealth_session_id,started_at,status\n";
                foreach ($groupIds as $group) {
                    $csvData .= "{$group['group_id']},{$group['group_index']},{$group['threads']},{$group['targets_count']},{$group['group_stealth_session_id']}," . date('Y-m-d H:i:s') . ",running\n";
                }
                file_put_contents($unlimitedCsvFile, $csvData);
                
                logMessage("UNLIMITED GROUPS LAUNCHED: $groupCount groups with total $totalThreads threads, master stealth session: $masterStealthSessionId");
                
                echo json_encode([
                    'success' => true,
                    'message' => "Launched $groupCount unlimited parallel groups successfully",
                    'unlimited_launch_id' => $unlimitedReport['unlimited_launch_id'],
                    'master_stealth_session_id' => $masterStealthSessionId,
                    'total_groups' => $groupCount,
                    'threads_per_group' => $threadsPerGroup,
                    'total_threads' => $totalThreads,
                    'launch_interval' => $launchInterval,
                    'attack_strategy' => $attackStrategy,
                    'target_distribution' => $targetDistribution,
                    'auto_scaling' => $autoScaling,
                    'dynamic_threads' => $dynamicThreads,
                    'failure_recovery' => $failureRecovery,
                    'group_ids' => array_column($groupIds, 'group_id'),
                    'groups_detail' => $groupIds,
                    'stealth_config' => $unlimitedReport['stealth_config'],
                    'report_file' => $unlimitedReportFile,
                    'csv_file' => $unlimitedCsvFile,
                    'unlimited_mode' => true
                ]);
                
            } catch (Exception $e) {
                logMessage("ERROR starting unlimited groups: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to start unlimited groups: ' . $e->getMessage()
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

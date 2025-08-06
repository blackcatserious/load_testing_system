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

function logMessage($message) {
    $logFile = '/home/ftcceelg/load_testing_system/logs/backend.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] RUNS_ENDPOINT: $message" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $runId = $_GET['run_id'] ?? '';
    $limit = $_GET['limit'] ?? 50;
    
    if ($runId) {
        try {
            $stmt = $pdo->prepare("SELECT r.*, g.profile_id, g.threads, g.duration, g.engine, g.behavior_profile_id FROM runs r LEFT JOIN groups g ON r.group_id = g.group_id WHERE r.run_id = ?");
            $stmt->execute([$runId]);
            $run = $stmt->fetch();
            
            if (!$run) {
                logMessage("ERROR: Run not found: $runId");
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Run not found'
                ]);
                exit();
            }
            
            logMessage("Run details requested for: $runId");
            
            echo json_encode([
                'status' => 'success',
                'data' => $run
            ]);
            
        } catch (Exception $e) {
            logMessage("ERROR getting run details: " . $e->getMessage());
            echo json_encode([
                'status' => 'error',
                'message' => 'Database error'
            ]);
        }
    } else {
        try {
            $stmt = $pdo->prepare("SELECT r.*, g.profile_id, g.threads, g.duration, g.engine, g.behavior_profile_id FROM runs r LEFT JOIN groups g ON r.group_id = g.group_id ORDER BY r.started_at DESC LIMIT ?");
            $stmt->execute([$limit]);
            $runs = $stmt->fetchAll();
            
            logMessage("Runs list requested - Found " . count($runs) . " runs");
            
            echo json_encode([
                'status' => 'success',
                'data' => ['runs' => $runs]
            ]);
            
        } catch (Exception $e) {
            logMessage("ERROR listing runs: " . $e->getMessage());
            echo json_encode([
                'status' => 'error',
                'message' => 'Database error'
            ]);
        }
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    $action = $data['action'] ?? '';
    
    logMessage("POST request - Action: $action, Data: " . json_encode($data));
    
    switch ($action) {
        case 'update_status':
            $runId = $data['run_id'] ?? '';
            $status = $data['status'] ?? '';
            $targetStatus = $data['target_status'] ?? 'active';
            $targetDisabled = $data['target_disabled'] ?? false;
            
            if (empty($runId) || empty($status)) {
                logMessage("ERROR: Missing run_id or status for update");
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Missing run_id or status'
                ]);
                exit();
            }
            
            try {
                $stmt = $pdo->prepare("UPDATE runs SET status = ?, finished_at = ?, target_status = ? WHERE run_id = ?");
                $stmt->execute([$status, date('Y-m-d H:i:s'), $targetStatus, $runId]);
                
                logMessage("Run status updated: $runId -> $status, target_status: $targetStatus");
                
                if ($targetDisabled) {
                    logMessage("SUCCESS DETECTION: Target disabled for run $runId - permanent failure achieved");
                    
                    $reportData = [
                        'run_id' => $runId,
                        'timestamp' => date('Y-m-d H:i:s'),
                        'target_status' => 'disabled',
                        'success_detection' => true,
                        'permanent_failure_achieved' => true,
                        'escalation_successful' => true
                    ];
                    
                    $reportsDir = '/home/ftcceelg/load_testing_system/reports';
                    if (!is_dir($reportsDir)) {
                        mkdir($reportsDir, 0755, true);
                    }
                    
                    $reportFile = $reportsDir . "/run_{$runId}_" . date('Y-m-d_H-i-s') . ".json";
                    file_put_contents($reportFile, json_encode($reportData, JSON_PRETTY_PRINT));
                    
                    logMessage("SUCCESS REPORT: Generated report file: $reportFile");
                }
                
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Run status updated successfully',
                    'target_disabled' => $targetDisabled
                ]);
                
            } catch (Exception $e) {
                logMessage("ERROR updating run status: " . $e->getMessage());
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Database error'
                ]);
            }
            exit();
            
        case 'generate_report':
            $runId = $data['run_id'] ?? '';
            $groupId = $data['group_id'] ?? '';
            
            if (empty($runId)) {
                logMessage("ERROR: Missing run_id for report generation");
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Missing run_id'
                ]);
                exit();
            }
            
            try {
                $stmt = $pdo->prepare("SELECT r.*, g.profile_id, g.threads, g.duration, g.engine, g.behavior_profile_id FROM runs r LEFT JOIN groups g ON r.group_id = g.group_id WHERE r.run_id = ?");
                $stmt->execute([$runId]);
                $run = $stmt->fetch();
                
                if (!$run) {
                    logMessage("ERROR: Run not found for report: $runId");
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Run not found'
                    ]);
                    exit();
                }
                
                $reportData = [
                    'run_id' => $runId,
                    'group_id' => $groupId,
                    'target_url' => $run['target_url'],
                    'profile_id' => $run['profile_id'],
                    'threads' => $run['threads'],
                    'duration' => $run['duration'],
                    'engine' => $run['engine'],
                    'behavior_profile_id' => $run['behavior_profile_id'],
                    'target_status' => $run['target_status'] ?? 'active',
                    'status' => $run['status'],
                    'started_at' => $run['started_at'],
                    'finished_at' => $run['finished_at'],
                    'generated_at' => date('Y-m-d H:i:s'),
                    'unlimited_mode_used' => ($run['threads'] > 500 || $run['duration'] > 10800),
                    'stealth_features' => [
                        'proxy_rotation' => true,
                        'ja3_fingerprinting' => true,
                        'user_agent_rotation' => true,
                        'tls_profile_rotation' => true,
                        'behavior_emulation' => true
                    ]
                ];
                
                $reportsDir = '/home/ftcceelg/load_testing_system/reports';
                if (!is_dir($reportsDir)) {
                    mkdir($reportsDir, 0755, true);
                }
                
                $reportFile = $reportsDir . "/run_{$runId}_" . date('Y-m-d_H-i-s') . ".json";
                file_put_contents($reportFile, json_encode($reportData, JSON_PRETTY_PRINT));
                
                logMessage("REPORT GENERATED: Created report file: $reportFile");
                
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Report generated successfully',
                    'report_file' => $reportFile
                ]);
                
            } catch (Exception $e) {
                logMessage("ERROR generating report: " . $e->getMessage());
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Database error'
                ]);
            }
            exit();
            
        default:
            logMessage("ERROR: Unknown action: $action");
            echo json_encode([
                'status' => 'error',
                'message' => 'Unknown action'
            ]);
            exit();
    }
}

logMessage("ERROR: Unsupported method: " . $_SERVER['REQUEST_METHOD']);
echo json_encode([
    'status' => 'error',
    'message' => 'Method not allowed'
]);
?>

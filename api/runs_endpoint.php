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
            
            if (empty($runId) || empty($status)) {
                logMessage("ERROR: Missing run_id or status for update");
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Missing run_id or status'
                ]);
                exit();
            }
            
            try {
                $stmt = $pdo->prepare("UPDATE runs SET status = ?, finished_at = ? WHERE run_id = ?");
                $stmt->execute([$status, date('Y-m-d H:i:s'), $runId]);
                
                logMessage("Run status updated: $runId -> $status");
                
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Run status updated successfully'
                ]);
                
            } catch (Exception $e) {
                logMessage("ERROR updating run status: " . $e->getMessage());
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

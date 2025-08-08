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
    $logEntry = "[$timestamp] STOP_ENDPOINT: $message" . PHP_EOL;
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

logMessage("Stop test request - Data: " . json_encode($data));

$runId = $data['run_id'] ?? '';
$groupId = $data['group_id'] ?? '';

if (empty($runId) && empty($groupId)) {
    logMessage("ERROR: No run_id or group_id provided");
    echo json_encode([
        'status' => 'error',
        'message' => 'Either run_id or group_id is required'
    ]);
    exit();
}

try {
    if ($runId) {
        $stmt = $pdo->prepare("UPDATE runs SET status = 'stopped', finished_at = ? WHERE run_id = ?");
        $stmt->execute([date('Y-m-d H:i:s'), $runId]);
        
        $stmt = $pdo->prepare("SELECT group_id FROM runs WHERE run_id = ?");
        $stmt->execute([$runId]);
        $run = $stmt->fetch();
        
        if ($run) {
            $groupId = $run['group_id'];
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as running_count FROM runs WHERE group_id = ? AND status = 'running'");
            $stmt->execute([$groupId]);
            $runningCount = $stmt->fetch()['running_count'];
            
            if ($runningCount == 0) {
                $stmt = $pdo->prepare("UPDATE groups SET status = 'stopped', finished_at = ? WHERE group_id = ?");
                $stmt->execute([date('Y-m-d H:i:s'), $groupId]);
                logMessage("Group $groupId automatically stopped - all runs completed");
            }
        }
        
        logMessage("Run stopped successfully: $runId");
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Run stopped successfully',
            'run_id' => $runId
        ]);
        
    } elseif ($groupId) {
        $db->updateGroupStatus($groupId, 'stopped');
        $db->updateRunsStatus($groupId, 'stopped');
        
        logMessage("Group stopped successfully: $groupId");
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Group stopped successfully',
            'group_id' => $groupId
        ]);
    }
    
} catch (Exception $e) {
    logMessage("ERROR stopping test: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to stop test: ' . $e->getMessage()
    ]);
}
?>

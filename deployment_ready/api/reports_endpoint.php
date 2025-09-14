<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

function logMessage($message) {
    $logFile = __DIR__ . '/../logs/backend.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] REPORTS_ENDPOINT: $message" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

$reportsDir = __DIR__ . '/../reports';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'list';
    
    if ($action === 'list') {
        try {
            if (!is_dir($reportsDir)) {
                mkdir($reportsDir, 0755, true);
            }
            
            $reports = [];
            $files = glob($reportsDir . '/*.{json,csv}', GLOB_BRACE);
            
            foreach ($files as $file) {
                $filename = basename($file);
                $extension = pathinfo($file, PATHINFO_EXTENSION);
                $size = filesize($file);
                $created = date('Y-m-d H:i:s', filemtime($file));
                
                $runInfo = null;
                if (preg_match('/^(run_\w+)_/', $filename, $matches)) {
                    $runId = $matches[1];
                    $runInfo = [
                        'run_id' => $runId,
                        'target_url' => 'Unknown',
                        'finished_at' => $created
                    ];
                } elseif (preg_match('/^group_(group_\w+)_/', $filename, $matches)) {
                    $groupId = $matches[1];
                    $runInfo = [
                        'run_id' => $groupId,
                        'target_url' => 'Group Report',
                        'finished_at' => $created
                    ];
                }
                
                $reports[] = [
                    'filename' => $filename,
                    'type' => $extension,
                    'size' => $size,
                    'created_at' => $created,
                    'run_id' => $runInfo ? $runInfo['run_id'] : null,
                    'run_info' => $runInfo
                ];
            }
            
            usort($reports, function($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });
            
            logMessage("Reports list requested - Found " . count($reports) . " reports");
            
            echo json_encode([
                'status' => 'success',
                'data' => ['reports' => $reports]
            ]);
            
        } catch (Exception $e) {
            logMessage("ERROR listing reports: " . $e->getMessage());
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to list reports: ' . $e->getMessage()
            ]);
        }
        exit();
    }
    
    if ($action === 'download') {
        $filename = $_GET['file'] ?? '';
        if (empty($filename)) {
            logMessage("ERROR: No filename provided for download");
            echo json_encode(['status' => 'error', 'message' => 'No filename provided']);
            exit();
        }
        
        $filename = basename($filename);
        $filepath = $reportsDir . '/' . $filename;
        
        if (!file_exists($filepath)) {
            logMessage("ERROR: File not found for download: $filename");
            echo json_encode(['status' => 'error', 'message' => 'File not found']);
            exit();
        }
        
        logMessage("Report download requested: $filename");
        
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $mimeType = $extension === 'json' ? 'application/json' : 'text/csv';
        
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        
        readfile($filepath);
        exit();
    }
    
    if ($action === 'view') {
        $filename = $_GET['file'] ?? '';
        if (empty($filename)) {
            logMessage("ERROR: No filename provided for view");
            echo json_encode(['status' => 'error', 'message' => 'No filename provided']);
            exit();
        }
        
        $filename = basename($filename);
        $filepath = $reportsDir . '/' . $filename;
        
        if (!file_exists($filepath)) {
            logMessage("ERROR: File not found for view: $filename");
            echo json_encode(['status' => 'error', 'message' => 'File not found']);
            exit();
        }
        
        logMessage("Report view requested: $filename");
        
        $content = file_get_contents($filepath);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        
        echo json_encode([
            'status' => 'success',
            'data' => [
                'filename' => $filename,
                'content' => $content,
                'type' => $extension,
                'size' => filesize($filepath)
            ]
        ]);
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    $action = $data['action'] ?? '';
    
    if ($action === 'delete') {
        $filename = $data['filename'] ?? '';
        if (empty($filename)) {
            logMessage("ERROR: No filename provided for delete");
            echo json_encode(['status' => 'error', 'message' => 'No filename provided']);
            exit();
        }
        
        $filename = basename($filename);
        $filepath = $reportsDir . '/' . $filename;
        
        if (!file_exists($filepath)) {
            logMessage("ERROR: File not found for delete: $filename");
            echo json_encode(['status' => 'error', 'message' => 'File not found']);
            exit();
        }
        
        if (unlink($filepath)) {
            logMessage("Report deleted successfully: $filename");
            echo json_encode(['status' => 'success', 'message' => 'Report deleted successfully']);
        } else {
            logMessage("ERROR deleting report: $filename");
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete report']);
        }
        exit();
    }
    
    if ($action === 'cleanup') {
        $olderThan = $data['older_than_days'] ?? 30;
        $cutoffTime = time() - ($olderThan * 24 * 60 * 60);
        
        try {
            if (!is_dir($reportsDir)) {
                echo json_encode(['status' => 'success', 'message' => 'No reports directory found', 'deleted_count' => 0]);
                exit();
            }
            
            $files = glob($reportsDir . '/*.{json,csv}', GLOB_BRACE);
            $deletedCount = 0;
            
            foreach ($files as $file) {
                if (filemtime($file) < $cutoffTime) {
                    if (unlink($file)) {
                        $deletedCount++;
                        logMessage("Cleaned up old report: " . basename($file));
                    }
                }
            }
            
            logMessage("Cleanup completed - Deleted $deletedCount old reports");
            echo json_encode([
                'status' => 'success', 
                'message' => "Cleanup completed - Deleted $deletedCount old reports",
                'deleted_count' => $deletedCount
            ]);
            
        } catch (Exception $e) {
            logMessage("ERROR during cleanup: " . $e->getMessage());
            echo json_encode([
                'status' => 'error',
                'message' => 'Cleanup failed: ' . $e->getMessage()
            ]);
        }
        exit();
    }
}

logMessage("ERROR: Invalid request - Method: " . $_SERVER['REQUEST_METHOD'] . ", Action: " . ($action ?? 'none'));
echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
?>

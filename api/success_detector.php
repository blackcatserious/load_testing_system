<?php

require_once 'database.php';

class SuccessDetector {
    private $db;
    private $config;
    
    public function __construct($database, $config = []) {
        $this->db = $database;
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }
    
    public function getDefaultConfig() {
        return [
            'min_requests_threshold' => 100,
            'error_rate_threshold' => 0.85,
            'latency_threshold_ms' => 20000,
            'zero_byte_threshold' => 0.5,
            'permanent_failure_codes' => ['404', '410', '429', '503', '524'],
            'protection_codes' => ['403', '429'],
            'consecutive_failures_required' => 10,
            'time_window_seconds' => 300
        ];
    }
    
    public function isTargetDisabled($targetUrl, $metrics) {
        $this->logMessage("Analyzing target status for: $targetUrl");
        
        $totalRequests = $metrics['total_requests'] ?? 0;
        if ($totalRequests < $this->config['min_requests_threshold']) {
            $this->logMessage("Insufficient data for analysis: $totalRequests requests");
            return [
                'disabled' => false,
                'reason' => 'insufficient_data',
                'requests_analyzed' => $totalRequests
            ];
        }
        
        $disabledReasons = [];
        
        $permanentFailureRate = $this->calculatePermanentFailureRate($metrics);
        if ($permanentFailureRate > $this->config['error_rate_threshold']) {
            $disabledReasons[] = "permanent_failure_rate: {$permanentFailureRate}";
            $this->logMessage("Target disabled due to high permanent failure rate: {$permanentFailureRate}");
        }
        
        $highLatency = $this->checkHighLatency($metrics);
        if ($highLatency['disabled']) {
            $disabledReasons[] = "high_latency: {$highLatency['avg_latency']}ms";
            $this->logMessage("Target disabled due to high latency: {$highLatency['avg_latency']}ms");
        }
        
        $zeroByteRate = $this->calculateZeroByteRate($metrics);
        if ($zeroByteRate > $this->config['zero_byte_threshold']) {
            $disabledReasons[] = "zero_byte_rate: {$zeroByteRate}";
            $this->logMessage("Target disabled due to high zero-byte response rate: {$zeroByteRate}");
        }
        
        $consecutiveFailures = $this->checkConsecutiveFailures($metrics);
        if ($consecutiveFailures['disabled']) {
            $disabledReasons[] = "consecutive_failures: {$consecutiveFailures['count']}";
            $this->logMessage("Target disabled due to consecutive failures: {$consecutiveFailures['count']}");
        }
        
        $isDisabled = !empty($disabledReasons);
        
        $protectionRate = $this->calculateProtectionRate($metrics);
        if ($protectionRate > 0.3) {
            $this->logMessage("Protection activated for target (403/429 rate: {$protectionRate}) - continuing escalation");
        }
        
        return [
            'disabled' => $isDisabled,
            'reasons' => $disabledReasons,
            'protection_rate' => $protectionRate,
            'permanent_failure_rate' => $permanentFailureRate,
            'zero_byte_rate' => $zeroByteRate,
            'avg_latency' => $highLatency['avg_latency'] ?? 0,
            'requests_analyzed' => $totalRequests,
            'timestamp' => time()
        ];
    }
    
    private function calculatePermanentFailureRate($metrics) {
        $totalRequests = $metrics['total_requests'] ?? 0;
        if ($totalRequests === 0) return 0;
        
        $permanentFailures = 0;
        $statusCodes = $metrics['status_codes'] ?? [];
        
        foreach ($this->config['permanent_failure_codes'] as $code) {
            $permanentFailures += $statusCodes[$code] ?? 0;
        }
        
        return $permanentFailures / $totalRequests;
    }
    
    private function calculateProtectionRate($metrics) {
        $totalRequests = $metrics['total_requests'] ?? 0;
        if ($totalRequests === 0) return 0;
        
        $protectionResponses = 0;
        $statusCodes = $metrics['status_codes'] ?? [];
        
        foreach ($this->config['protection_codes'] as $code) {
            $protectionResponses += $statusCodes[$code] ?? 0;
        }
        
        return $protectionResponses / $totalRequests;
    }
    
    private function calculateZeroByteRate($metrics) {
        $totalRequests = $metrics['total_requests'] ?? 0;
        if ($totalRequests === 0) return 0;
        
        $zeroByteResponses = $metrics['zero_byte_responses'] ?? 0;
        return $zeroByteResponses / $totalRequests;
    }
    
    private function checkHighLatency($metrics) {
        $latencyMetrics = $metrics['latency'] ?? [];
        
        $avgLatency = $latencyMetrics['avg'] ?? 0;
        $p95Latency = $latencyMetrics['p95'] ?? 0;
        $p99Latency = $latencyMetrics['p99'] ?? 0;
        
        $highLatencyDisabled = (
            $avgLatency > $this->config['latency_threshold_ms'] ||
            ($p95Latency > $this->config['latency_threshold_ms'] && $p99Latency > $this->config['latency_threshold_ms'])
        );
        
        return [
            'disabled' => $highLatencyDisabled,
            'avg_latency' => $avgLatency,
            'p95_latency' => $p95Latency,
            'p99_latency' => $p99Latency
        ];
    }
    
    private function checkConsecutiveFailures($metrics) {
        $recentResponses = $metrics['recent_responses'] ?? [];
        
        if (count($recentResponses) < $this->config['consecutive_failures_required']) {
            return ['disabled' => false, 'count' => 0];
        }
        
        $consecutiveFailures = 0;
        $maxConsecutiveFailures = 0;
        
        foreach ($recentResponses as $response) {
            $statusCode = $response['status_code'] ?? 0;
            
            if (in_array((string)$statusCode, $this->config['permanent_failure_codes'])) {
                $consecutiveFailures++;
                $maxConsecutiveFailures = max($maxConsecutiveFailures, $consecutiveFailures);
            } else {
                $consecutiveFailures = 0;
            }
        }
        
        return [
            'disabled' => $maxConsecutiveFailures >= $this->config['consecutive_failures_required'],
            'count' => $maxConsecutiveFailures
        ];
    }
    
    public function updateTargetStatus($targetUrl, $status, $reason = '') {
        if (!$this->db) {
            $this->logMessage("Database not available for status update");
            return false;
        }
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO target_status (target_url, status, reason, updated_at) 
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                status = VALUES(status), 
                reason = VALUES(reason), 
                updated_at = VALUES(updated_at)
            ");
            
            $stmt->execute([$targetUrl, $status, $reason]);
            $this->logMessage("Updated target status: $targetUrl -> $status ($reason)");
            return true;
            
        } catch (Exception $e) {
            $this->logMessage("Error updating target status: " . $e->getMessage());
            return false;
        }
    }
    
    public function getTargetStatus($targetUrl) {
        if (!$this->db) {
            return null;
        }
        
        try {
            $stmt = $this->db->prepare("
                SELECT status, reason, updated_at 
                FROM target_status 
                WHERE target_url = ? 
                ORDER BY updated_at DESC 
                LIMIT 1
            ");
            
            $stmt->execute([$targetUrl]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $this->logMessage("Error getting target status: " . $e->getMessage());
            return null;
        }
    }
    
    public function analyzeGroupTargets($groupId, $targets) {
        $this->logMessage("Analyzing group targets for group: $groupId");
        
        $results = [];
        $disabledCount = 0;
        $protectionCount = 0;
        
        foreach ($targets as $target) {
            $metrics = $this->getTargetMetrics($target, $groupId);
            $analysis = $this->isTargetDisabled($target, $metrics);
            
            if ($analysis['disabled']) {
                $disabledCount++;
                $this->updateTargetStatus($target, 'disabled', implode(', ', $analysis['reasons']));
            } elseif ($analysis['protection_rate'] > 0.3) {
                $protectionCount++;
                $this->updateTargetStatus($target, 'protected', 'protection_activated');
            } else {
                $this->updateTargetStatus($target, 'active', 'normal_operation');
            }
            
            $results[$target] = $analysis;
        }
        
        return [
            'group_id' => $groupId,
            'total_targets' => count($targets),
            'disabled_targets' => $disabledCount,
            'protected_targets' => $protectionCount,
            'active_targets' => count($targets) - $disabledCount,
            'target_analysis' => $results,
            'timestamp' => time()
        ];
    }
    
    private function getTargetMetrics($target, $groupId) {
        return [
            'total_requests' => 0,
            'status_codes' => [],
            'latency' => ['avg' => 0, 'p95' => 0, 'p99' => 0],
            'zero_byte_responses' => 0,
            'recent_responses' => []
        ];
    }
    
    public function shouldContinueEscalation($targetUrl, $metrics) {
        $analysis = $this->isTargetDisabled($targetUrl, $metrics);
        
        if (!$analysis['disabled']) {
            if ($analysis['protection_rate'] > 0.3) {
                $this->logMessage("Protection detected for $targetUrl - continuing escalation");
                return [
                    'continue' => true,
                    'reason' => 'protection_activated',
                    'escalation_factor' => 1.5 // Increase escalation when protection is detected
                ];
            }
            
            return [
                'continue' => true,
                'reason' => 'target_active',
                'escalation_factor' => 1.0
            ];
        }
        
        $this->logMessage("Target permanently disabled: $targetUrl - stopping escalation");
        return [
            'continue' => false,
            'reason' => 'target_disabled',
            'escalation_factor' => 0
        ];
    }
    
    private function logMessage($message) {
        $logFile = '/home/ftcceelg/load_testing_system/logs/backend.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] SUCCESS_DETECTOR: $message" . PHP_EOL;
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

?>

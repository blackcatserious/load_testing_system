<?php

require_once 'database.php';

class SuccessDetector {
    private $db;
    private $config;
    private $targetSuccessTimers = [];
    private $groupSuccessTimers = [];
    
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
            'permanent_failure_codes' => ['404', '410', '503', '524'],
            'protection_codes' => ['403', '429'],
            'success_threshold' => 0.75,
            'success_duration_seconds' => 300,
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
        $successRate = $this->calculateSuccessRate($metrics);
        
        if ($successRate >= $this->config['success_threshold']) {
            $this->updateSuccessTimer($targetUrl, $successRate);
            
            if ($this->hasMetSuccessDuration($targetUrl)) {
                $disabledReasons[] = "success_condition_met: {$successRate} for {$this->config['success_duration_seconds']}s";
                $this->logMessage("Target disabled due to sustained success condition: {$successRate} for {$this->config['success_duration_seconds']}s");
            }
        } else {
            $this->resetSuccessTimer($targetUrl);
        }
        
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
            'success_rate' => $successRate,
            'zero_byte_rate' => $zeroByteRate,
            'avg_latency' => $highLatency['avg_latency'] ?? 0,
            'requests_analyzed' => $totalRequests,
            'success_timer_active' => isset($this->targetSuccessTimers[$targetUrl]),
            'success_duration_remaining' => $this->getSuccessDurationRemaining($targetUrl),
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
    
    private function calculateSuccessRate($metrics) {
        $totalRequests = $metrics['total_requests'] ?? 0;
        if ($totalRequests === 0) return 0;
        
        $successResponses = 0;
        $statusCodes = $metrics['status_codes'] ?? [];
        
        foreach ($this->config['permanent_failure_codes'] as $code) {
            $successResponses += $statusCodes[$code] ?? 0;
        }
        
        return $successResponses / $totalRequests;
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
            $stmt = $this->db->getPDO()->prepare("
                INSERT OR REPLACE INTO target_status (target_url, status, reason, updated_at) 
                VALUES (?, ?, ?, datetime('now'))
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
            $stmt = $this->db->getPDO()->prepare("
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
        
        $allTargetsDisabled = ($disabledCount === count($targets));
        
        if ($allTargetsDisabled) {
            $this->updateGroupSuccessTimer($groupId);
            if ($this->hasGroupMetSuccessDuration($groupId)) {
                $this->logMessage("ALL TARGETS DISABLED for group $groupId - AUTO-STOP condition met");
            }
        } else {
            $this->resetGroupSuccessTimer($groupId);
        }
        
        return [
            'group_id' => $groupId,
            'total_targets' => count($targets),
            'disabled_targets' => $disabledCount,
            'protected_targets' => $protectionCount,
            'active_targets' => count($targets) - $disabledCount,
            'target_analysis' => $results,
            'all_targets_disabled' => $allTargetsDisabled,
            'auto_stop_ready' => $allTargetsDisabled && $this->hasGroupMetSuccessDuration($groupId),
            'group_success_timer_remaining' => $this->getGroupSuccessDurationRemaining($groupId),
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
    
    private function updateSuccessTimer($targetUrl, $successRate) {
        if (!isset($this->targetSuccessTimers[$targetUrl])) {
            $this->targetSuccessTimers[$targetUrl] = time();
            $this->logMessage("Started success timer for target: $targetUrl (success rate: $successRate)");
        }
    }
    
    private function resetSuccessTimer($targetUrl) {
        if (isset($this->targetSuccessTimers[$targetUrl])) {
            unset($this->targetSuccessTimers[$targetUrl]);
            $this->logMessage("Reset success timer for target: $targetUrl");
        }
    }
    
    private function hasMetSuccessDuration($targetUrl) {
        if (!isset($this->targetSuccessTimers[$targetUrl])) {
            return false;
        }
        
        $elapsed = time() - $this->targetSuccessTimers[$targetUrl];
        return $elapsed >= $this->config['success_duration_seconds'];
    }
    
    private function getSuccessDurationRemaining($targetUrl) {
        if (!isset($this->targetSuccessTimers[$targetUrl])) {
            return 0;
        }
        
        $elapsed = time() - $this->targetSuccessTimers[$targetUrl];
        return max(0, $this->config['success_duration_seconds'] - $elapsed);
    }
    
    private function updateGroupSuccessTimer($groupId) {
        if (!isset($this->groupSuccessTimers[$groupId])) {
            $this->groupSuccessTimers[$groupId] = time();
            $this->logMessage("Started group success timer for: $groupId - ALL TARGETS DISABLED");
        }
    }
    
    private function resetGroupSuccessTimer($groupId) {
        if (isset($this->groupSuccessTimers[$groupId])) {
            unset($this->groupSuccessTimers[$groupId]);
            $this->logMessage("Reset group success timer for: $groupId");
        }
    }
    
    private function hasGroupMetSuccessDuration($groupId) {
        if (!isset($this->groupSuccessTimers[$groupId])) {
            return false;
        }
        
        $elapsed = time() - $this->groupSuccessTimers[$groupId];
        return $elapsed >= $this->config['success_duration_seconds'];
    }
    
    private function getGroupSuccessDurationRemaining($groupId) {
        if (!isset($this->groupSuccessTimers[$groupId])) {
            return 0;
        }
        
        $elapsed = time() - $this->groupSuccessTimers[$groupId];
        return max(0, $this->config['success_duration_seconds'] - $elapsed);
    }
    
    public function shouldAutoStop($groupId, $targets) {
        $analysis = $this->analyzeGroupTargets($groupId, $targets);
        return $analysis['auto_stop_ready'];
    }
    
    private function logMessage($message) {
        $logFile = __DIR__ . '/../logs/backend.log';
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

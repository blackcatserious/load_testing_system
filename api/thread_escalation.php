<?php

require_once 'database.php';
require_once 'escalation_modes.php';

class ThreadEscalation {
    private $db;
    private $escalationModes;
    private $config;
    private $escalationHistory;
    
    public function __construct($database, $config = []) {
        $this->db = $database;
        $this->escalationModes = new EscalationModes($database, $config);
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->escalationHistory = [];
    }
    
    public function getDefaultConfig() {
        return [
            'min_threads' => 1,
            'max_threads' => PHP_INT_MAX,
            'escalation_factor' => 1.5,
            'deescalation_factor' => 0.8,
            'resistance_threshold_low' => 2,
            'resistance_threshold_high' => 7,
            'min_requests_for_escalation' => 50,
            'escalation_cooldown' => 30,
            'max_escalations_per_hour' => 10,
            'success_rate_threshold' => 0.8,
            'error_rate_threshold' => 0.3,
            'timeout_rate_threshold' => 0.2,
            'auto_escalation_enabled' => true,
            'conservative_mode' => false
        ];
    }
    
    public function analyzeAndEscalate($groupId, $currentMetrics, $currentThreads, $startTime) {
        if (!$this->config['auto_escalation_enabled']) {
            return [
                'action' => 'maintain',
                'new_threads' => $currentThreads,
                'reason' => 'auto_escalation_disabled'
            ];
        }
        
        $resistanceLevel = $this->calculateResistanceLevel($currentMetrics);
        $escalationDecision = $this->makeEscalationDecision($groupId, $currentMetrics, $currentThreads, $resistanceLevel, $startTime);
        
        $this->logEscalationDecision($groupId, $escalationDecision, $currentMetrics, $resistanceLevel);
        
        return $escalationDecision;
    }
    
    private function makeEscalationDecision($groupId, $metrics, $currentThreads, $resistanceLevel, $startTime) {
        $elapsedTime = time() - $startTime;
        
        if ($metrics['total_requests'] < $this->config['min_requests_for_escalation']) {
            return [
                'action' => 'maintain',
                'new_threads' => $currentThreads,
                'reason' => 'insufficient_data',
                'message' => 'Not enough requests to make escalation decision'
            ];
        }
        
        if ($this->isInCooldown($groupId)) {
            return [
                'action' => 'maintain',
                'new_threads' => $currentThreads,
                'reason' => 'cooldown_active',
                'message' => 'Escalation cooldown period active'
            ];
        }
        
        if ($this->hasExceededEscalationLimit($groupId)) {
            return [
                'action' => 'maintain',
                'new_threads' => $currentThreads,
                'reason' => 'escalation_limit_reached',
                'message' => 'Maximum escalations per hour reached'
            ];
        }
        
        $successRate = $metrics['success_rate'] ?? 0;
        $errorRates = $this->calculateErrorRates($metrics);
        
        if ($resistanceLevel <= $this->config['resistance_threshold_low'] && 
            $successRate >= $this->config['success_rate_threshold']) {
            
            return $this->escalateAggressive($currentThreads, $resistanceLevel, 'weak_resistance');
        }
        
        if ($errorRates['5xx_rate'] >= $this->config['error_rate_threshold']) {
            return $this->escalateModerate($currentThreads, $resistanceLevel, 'sustained_5xx_errors');
        }
        
        if ($errorRates['timeout_rate'] >= $this->config['timeout_rate_threshold']) {
            return $this->escalateAggressive($currentThreads, $resistanceLevel, 'timeout_errors');
        }
        
        if ($errorRates['4xx_rate'] >= 0.5 && $resistanceLevel >= $this->config['resistance_threshold_high']) {
            return $this->escalateWithStealth($currentThreads, $resistanceLevel, 'high_resistance_4xx');
        }
        
        if ($this->shouldDeescalate($metrics, $resistanceLevel, $elapsedTime)) {
            return $this->deescalate($currentThreads, $resistanceLevel, 'high_resistance_detected');
        }
        
        return [
            'action' => 'maintain',
            'new_threads' => $currentThreads,
            'reason' => 'no_escalation_needed',
            'message' => 'Current performance within acceptable parameters'
        ];
    }
    
    private function escalateAggressive($currentThreads, $resistanceLevel, $reason) {
        $escalationFactor = $this->config['escalation_factor'] * 1.3;
        
        if ($resistanceLevel === 0) {
            $escalationFactor *= 1.5;
        }
        
        $newThreads = min(
            round($currentThreads * $escalationFactor),
            $this->config['max_threads']
        );
        
        return [
            'action' => 'escalate_aggressive',
            'new_threads' => $newThreads,
            'escalation_factor' => $escalationFactor,
            'reason' => $reason,
            'message' => "Aggressive escalation: {$currentThreads} -> {$newThreads} threads",
            'stealth_required' => false,
            'resistance_level' => $resistanceLevel
        ];
    }
    
    private function escalateModerate($currentThreads, $resistanceLevel, $reason) {
        $escalationFactor = $this->config['escalation_factor'];
        
        if ($this->config['conservative_mode']) {
            $escalationFactor *= 0.8;
        }
        
        $newThreads = min(
            round($currentThreads * $escalationFactor),
            $this->config['max_threads']
        );
        
        return [
            'action' => 'escalate_moderate',
            'new_threads' => $newThreads,
            'escalation_factor' => $escalationFactor,
            'reason' => $reason,
            'message' => "Moderate escalation: {$currentThreads} -> {$newThreads} threads",
            'stealth_required' => false,
            'resistance_level' => $resistanceLevel
        ];
    }
    
    private function escalateWithStealth($currentThreads, $resistanceLevel, $reason) {
        $escalationFactor = $this->config['escalation_factor'] * 0.9;
        
        $newThreads = round($currentThreads * $escalationFactor);
        
        return [
            'action' => 'escalate_with_stealth',
            'new_threads' => $newThreads,
            'escalation_factor' => $escalationFactor,
            'reason' => $reason,
            'message' => "Stealth escalation: {$currentThreads} -> {$newThreads} threads with enhanced stealth",
            'stealth_required' => true,
            'stealth_enhancements' => [
                'proxy_rotation_increased' => true,
                'ua_rotation_increased' => true,
                'request_spacing_increased' => true,
                'tls_fingerprint_rotation' => true
            ],
            'resistance_level' => $resistanceLevel
        ];
    }
    
    private function deescalate($currentThreads, $resistanceLevel, $reason) {
        $deescalationFactor = $this->config['deescalation_factor'];
        
        $newThreads = max(
            round($currentThreads * $deescalationFactor),
            $this->config['min_threads']
        );
        
        return [
            'action' => 'deescalate',
            'new_threads' => $newThreads,
            'deescalation_factor' => $deescalationFactor,
            'reason' => $reason,
            'message' => "Deescalation: {$currentThreads} -> {$newThreads} threads due to high resistance",
            'stealth_required' => true,
            'resistance_level' => $resistanceLevel
        ];
    }
    
    private function calculateResistanceLevel($metrics) {
        $totalRequests = $metrics['total_requests'] ?? 0;
        
        if ($totalRequests === 0) {
            return 0;
        }
        
        $blockingCodes = ['403', '406', '429', '503', '524'];
        $blockingCount = 0;
        
        foreach ($blockingCodes as $code) {
            $blockingCount += $metrics['codes'][$code] ?? 0;
        }
        
        $blockingRate = $blockingCount / $totalRequests;
        
        $resistanceLevel = 0;
        if ($blockingRate > 0.8) {
            $resistanceLevel = 10;
        } elseif ($blockingRate > 0.6) {
            $resistanceLevel = 8;
        } elseif ($blockingRate > 0.4) {
            $resistanceLevel = 6;
        } elseif ($blockingRate > 0.2) {
            $resistanceLevel = 4;
        } elseif ($blockingRate > 0.1) {
            $resistanceLevel = 2;
        }
        
        $successRate = $metrics['success_rate'] ?? 1;
        if ($successRate < 0.3) {
            $resistanceLevel = min($resistanceLevel + 2, 10);
        }
        
        $avgLatency = ($metrics['latency_ms']['p95'] ?? 0);
        if ($avgLatency > 5000) {
            $resistanceLevel = min($resistanceLevel + 1, 10);
        }
        
        return $resistanceLevel;
    }
    
    private function calculateErrorRates($metrics) {
        $totalRequests = $metrics['total_requests'] ?? 0;
        
        if ($totalRequests === 0) {
            return [
                '5xx_rate' => 0,
                '4xx_rate' => 0,
                'timeout_rate' => 0,
                'blocking_rate' => 0
            ];
        }
        
        $codes5xx = ($metrics['codes']['500'] ?? 0) + 
                   ($metrics['codes']['502'] ?? 0) + 
                   ($metrics['codes']['503'] ?? 0) + 
                   ($metrics['codes']['504'] ?? 0);
        
        $codes4xx = ($metrics['codes']['400'] ?? 0) + 
                   ($metrics['codes']['403'] ?? 0) + 
                   ($metrics['codes']['404'] ?? 0) + 
                   ($metrics['codes']['429'] ?? 0);
        
        $timeouts = $metrics['codes']['524'] ?? 0;
        
        $blockingCodes = ($metrics['codes']['403'] ?? 0) + 
                        ($metrics['codes']['406'] ?? 0) + 
                        ($metrics['codes']['429'] ?? 0);
        
        return [
            '5xx_rate' => $codes5xx / $totalRequests,
            '4xx_rate' => $codes4xx / $totalRequests,
            'timeout_rate' => $timeouts / $totalRequests,
            'blocking_rate' => $blockingCodes / $totalRequests
        ];
    }
    
    private function shouldDeescalate($metrics, $resistanceLevel, $elapsedTime) {
        if ($resistanceLevel >= $this->config['resistance_threshold_high']) {
            return true;
        }
        
        $errorRates = $this->calculateErrorRates($metrics);
        
        if ($errorRates['blocking_rate'] > 0.7) {
            return true;
        }
        
        $successRate = $metrics['success_rate'] ?? 1;
        if ($successRate < 0.2) {
            return true;
        }
        
        return false;
    }
    
    private function isInCooldown($groupId) {
        $lastEscalation = $this->getLastEscalationTime($groupId);
        
        if (!$lastEscalation) {
            return false;
        }
        
        $timeSinceLastEscalation = time() - $lastEscalation;
        return $timeSinceLastEscalation < $this->config['escalation_cooldown'];
    }
    
    private function hasExceededEscalationLimit($groupId) {
        $escalationsInLastHour = $this->getEscalationsInLastHour($groupId);
        return $escalationsInLastHour >= $this->config['max_escalations_per_hour'];
    }
    
    private function getLastEscalationTime($groupId) {
        if ($this->db) {
            $history = $this->db->getEscalationHistory($groupId, 1);
            if (!empty($history)) {
                return strtotime($history[0]['created_at']);
            }
        }
        
        return null;
    }
    
    private function getEscalationsInLastHour($groupId) {
        if (!$this->db) {
            return 0;
        }
        
        $oneHourAgo = date('Y-m-d H:i:s', time() - 3600);
        $stmt = $this->db->getPDO()->prepare(
            "SELECT COUNT(*) as count FROM escalation_events WHERE group_id = ? AND created_at > ?"
        );
        $stmt->execute([$groupId, $oneHourAgo]);
        $result = $stmt->fetch();
        
        return $result['count'] ?? 0;
    }
    
    public function getEscalationThresholds() {
        return [
            'resistance_levels' => [
                'low' => $this->config['resistance_threshold_low'],
                'high' => $this->config['resistance_threshold_high']
            ],
            'error_rates' => [
                'success_rate_min' => $this->config['success_rate_threshold'],
                'error_rate_max' => $this->config['error_rate_threshold'],
                'timeout_rate_max' => $this->config['timeout_rate_threshold']
            ],
            'thread_limits' => [
                'min' => $this->config['min_threads'],
                'max' => $this->config['max_threads']
            ],
            'escalation_factors' => [
                'escalation' => $this->config['escalation_factor'],
                'deescalation' => $this->config['deescalation_factor']
            ],
            'timing' => [
                'cooldown_seconds' => $this->config['escalation_cooldown'],
                'max_per_hour' => $this->config['max_escalations_per_hour'],
                'min_requests' => $this->config['min_requests_for_escalation']
            ]
        ];
    }
    
    public function updateEscalationConfig($newConfig) {
        $this->config = array_merge($this->config, $newConfig);
        
        $this->logMessage("Escalation configuration updated: " . json_encode($newConfig));
        
        return $this->config;
    }
    
    public function getEscalationStats($groupId) {
        if (!$this->db) {
            return null;
        }
        
        $resistanceMetrics = $this->db->getResistanceMetrics($groupId);
        $escalationHistory = $this->db->getEscalationHistory($groupId, 20);
        
        $escalationCounts = [
            'escalate_aggressive' => 0,
            'escalate_moderate' => 0,
            'escalate_with_stealth' => 0,
            'deescalate' => 0,
            'maintain' => 0
        ];
        
        foreach ($escalationHistory as $event) {
            $recommendation = json_decode($event['recommendation'], true);
            $action = $recommendation['action'] ?? 'unknown';
            
            if (isset($escalationCounts[$action])) {
                $escalationCounts[$action]++;
            }
        }
        
        return [
            'group_id' => $groupId,
            'resistance_metrics' => $resistanceMetrics,
            'escalation_counts' => $escalationCounts,
            'total_escalation_events' => count($escalationHistory),
            'recent_escalations' => array_slice($escalationHistory, 0, 5),
            'current_config' => $this->config
        ];
    }
    
    public function simulateEscalation($metrics, $currentThreads, $scenarios = []) {
        $simulations = [];
        
        $defaultScenarios = [
            'weak_resistance' => ['resistance_level' => 1, 'success_rate' => 0.9],
            'moderate_resistance' => ['resistance_level' => 5, 'success_rate' => 0.6],
            'high_resistance' => ['resistance_level' => 8, 'success_rate' => 0.3],
            'server_overload' => ['5xx_rate' => 0.4, 'success_rate' => 0.4],
            'timeout_errors' => ['timeout_rate' => 0.3, 'success_rate' => 0.5]
        ];
        
        $scenariosToTest = !empty($scenarios) ? $scenarios : $defaultScenarios;
        
        foreach ($scenariosToTest as $scenarioName => $scenarioMetrics) {
            $testMetrics = array_merge($metrics, $scenarioMetrics);
            $testMetrics['total_requests'] = 100;
            
            $decision = $this->makeEscalationDecision(
                'simulation_' . $scenarioName,
                $testMetrics,
                $currentThreads,
                $scenarioMetrics['resistance_level'] ?? $this->calculateResistanceLevel($testMetrics),
                time() - 300
            );
            
            $simulations[$scenarioName] = $decision;
        }
        
        return $simulations;
    }
    
    private function logEscalationDecision($groupId, $decision, $metrics, $resistanceLevel) {
        if ($this->db && $decision['action'] !== 'maintain') {
            $this->db->insertEscalationEvent(
                $groupId,
                $resistanceLevel,
                $decision,
                json_encode($metrics['codes'] ?? [])
            );
        }
        
        $this->logMessage(
            "Escalation decision for group {$groupId}: {$decision['action']} " .
            "(threads: {$decision['new_threads']}, resistance: {$resistanceLevel}, reason: {$decision['reason']})"
        );
    }
    
    private function logMessage($message) {
        $logFile = './logs/backend.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] THREAD_ESCALATION: $message\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}
?>

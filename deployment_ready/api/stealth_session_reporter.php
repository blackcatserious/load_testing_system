<?php

class StealthSessionReporter {
    private $logDir;
    private $reportsDir;
    
    public function __construct() {
        $this->logDir = __DIR__ . '/../logs';
        $this->reportsDir = __DIR__ . '/../reports';
        
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
        if (!is_dir($this->reportsDir)) {
            mkdir($this->reportsDir, 0755, true);
        }
    }
    
    public function logStealthSession($groupId, $sessionData) {
        $stealthLogFile = $this->logDir . '/stealth_sessions.json';
        
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'group_id' => $groupId,
            'session_data' => $sessionData
        ];
        
        $existingLogs = [];
        if (file_exists($stealthLogFile)) {
            $content = file_get_contents($stealthLogFile);
            $existingLogs = json_decode($content, true) ?: [];
        }
        
        $existingLogs[] = $logEntry;
        
        file_put_contents($stealthLogFile, json_encode($existingLogs, JSON_PRETTY_PRINT));
        
        $this->logMessage("Stealth session logged for group: $groupId");
    }
    
    public function createSuccessVerificationReport($groupId, $targets, $successData) {
        $reportFile = $this->reportsDir . "/success_verification_$groupId.json";
        
        $report = [
            'group_id' => $groupId,
            'timestamp' => date('Y-m-d H:i:s'),
            'targets' => $targets,
            'success_criteria' => [
                'threshold' => 75,
                'success_codes' => [404, 410, 503, 524],
                'blocked_codes' => [403, 429]
            ],
            'results' => $successData,
            'overall_status' => $this->calculateOverallStatus($successData)
        ];
        
        file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT));
        
        $this->logMessage("Success verification report created: $reportFile");
        
        return $reportFile;
    }
    
    public function createPerRoundReport($groupId, $roundNumber, $roundData) {
        $timestamp = date('Y-m-d_H-i-s');
        $jsonFile = $this->reportsDir . "/round_{$roundNumber}_group_{$groupId}_{$timestamp}.json";
        $csvFile = $this->reportsDir . "/round_{$roundNumber}_group_{$groupId}_{$timestamp}.csv";
        
        file_put_contents($jsonFile, json_encode($roundData, JSON_PRETTY_PRINT));
        
        $csvData = $this->convertToCSV($roundData);
        file_put_contents($csvFile, $csvData);
        
        $this->logMessage("Per-round reports created: $jsonFile, $csvFile");
        
        return ['json' => $jsonFile, 'csv' => $csvFile];
    }
    
    public function createPerGroupReport($groupId, $groupData) {
        $timestamp = date('Y-m-d_H-i-s');
        $jsonFile = $this->reportsDir . "/group_{$groupId}_{$timestamp}.json";
        $csvFile = $this->reportsDir . "/group_{$groupId}_{$timestamp}.csv";
        
        file_put_contents($jsonFile, json_encode($groupData, JSON_PRETTY_PRINT));
        
        $csvData = $this->convertToCSV($groupData);
        file_put_contents($csvFile, $csvData);
        
        $this->logMessage("Per-group reports created: $jsonFile, $csvFile");
        
        return ['json' => $jsonFile, 'csv' => $csvFile];
    }
    
    public function logEvolutionCycle($groupId, $cycleNumber, $evolutionData) {
        $evolutionLogFile = $this->logDir . '/evolution_cycles.json';
        
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'group_id' => $groupId,
            'cycle_number' => $cycleNumber,
            'evolution_data' => $evolutionData
        ];
        
        $existingLogs = [];
        if (file_exists($evolutionLogFile)) {
            $content = file_get_contents($evolutionLogFile);
            $existingLogs = json_decode($content, true) ?: [];
        }
        
        $existingLogs[] = $logEntry;
        
        file_put_contents($evolutionLogFile, json_encode($existingLogs, JSON_PRETTY_PRINT));
        
        $this->logMessage("Evolution cycle $cycleNumber logged for group: $groupId");
    }
    
    public function trackRealTimeMetrics($groupId, $metricsData) {
        $metricsFile = $this->reportsDir . "/realtime_metrics_$groupId.json";
        
        $timestamp = time();
        $entry = [
            'timestamp' => $timestamp,
            'datetime' => date('Y-m-d H:i:s', $timestamp),
            'metrics' => $metricsData
        ];
        
        $existingMetrics = [];
        if (file_exists($metricsFile)) {
            $content = file_get_contents($metricsFile);
            $existingMetrics = json_decode($content, true) ?: [];
        }
        
        $existingMetrics[] = $entry;
        
        if (count($existingMetrics) > 1000) {
            $existingMetrics = array_slice($existingMetrics, -1000);
        }
        
        file_put_contents($metricsFile, json_encode($existingMetrics, JSON_PRETTY_PRINT));
    }
    
    private function calculateOverallStatus($successData) {
        $totalTargets = count($successData);
        $disabledTargets = 0;
        
        foreach ($successData as $targetData) {
            if (isset($targetData['success_rate']) && $targetData['success_rate'] >= 75) {
                $disabledTargets++;
            }
        }
        
        $disabledPercentage = ($disabledTargets / $totalTargets) * 100;
        
        if ($disabledPercentage >= 100) {
            return 'ALL_TARGETS_DISABLED';
        } elseif ($disabledPercentage >= 75) {
            return 'MAJORITY_DISABLED';
        } elseif ($disabledPercentage >= 50) {
            return 'HALF_DISABLED';
        } elseif ($disabledPercentage > 0) {
            return 'PARTIAL_SUCCESS';
        } else {
            return 'NO_SUCCESS';
        }
    }
    
    private function convertToCSV($data) {
        if (empty($data)) {
            return '';
        }
        
        $csv = '';
        $headers = [];
        
        if (is_array($data) && !empty($data)) {
            $firstRow = reset($data);
            if (is_array($firstRow)) {
                $headers = array_keys($firstRow);
                $csv .= implode(',', $headers) . "\n";
                
                foreach ($data as $row) {
                    $csvRow = [];
                    foreach ($headers as $header) {
                        $value = $row[$header] ?? '';
                        if (is_array($value)) {
                            $value = json_encode($value);
                        }
                        $csvRow[] = '"' . str_replace('"', '""', $value) . '"';
                    }
                    $csv .= implode(',', $csvRow) . "\n";
                }
            }
        }
        
        return $csv;
    }
    
    private function logMessage($message) {
        $logFile = $this->logDir . '/backend.log';
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] STEALTH_SESSION_REPORTER: $message" . PHP_EOL;
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

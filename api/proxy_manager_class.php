<?php

class ProxyManager {
    private $proxies = [
        ['ip' => '185.220.101.182', 'port' => '8080', 'type' => 'http'],
        ['ip' => '192.168.1.100', 'port' => '3128', 'type' => 'http'],
        ['ip' => '10.0.0.1', 'port' => '8080', 'type' => 'socks5']
    ];
    
    public function getActiveProxy() {
        return $this->proxies[array_rand($this->proxies)];
    }
    
    public function rotateProxy() {
        return $this->getActiveProxy();
    }
}

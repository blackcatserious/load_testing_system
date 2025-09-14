<?php

class TLSProfile {
    private $profiles = [
        [
            'name' => 'Chrome_120',
            'ja3_fingerprint' => '771,4865-4866-4867-49195-49199-49196-49200-52393-52392-49171-49172-49161-49162-49-50-156-157-47-53,0-23-65281-10-11-35-16-5-13-18-51-45-43-27-21,29-23-24-25,0',
            'tls_version' => '1.3'
        ],
        [
            'name' => 'Firefox_121',
            'ja3_fingerprint' => '772,4865-4866-4867-49195-49199-49196-49200-52393-52392-49171-49172-49161-49162-49-50-156-157-47-53,0-23-65281-10-11-35-16-5-13-18-51-45-43-27-21,29-23-24-25,0',
            'tls_version' => '1.3'
        ],
        [
            'name' => 'Safari_17',
            'ja3_fingerprint' => '773,4865-4866-4867-49196-49200-49195-49199-52393-52392-49162-49172-49161-49171-50-49-157-156-53-47,0-23-65281-10-11-35-16-5-13-18-51-45-43-27-21,29-23-24-25,0',
            'tls_version' => '1.3'
        ],
        [
            'name' => 'Edge_120',
            'ja3_fingerprint' => '774,4865-4866-4867-49195-49199-49196-49200-52393-52392-49171-49172-49161-49162-49-50-156-157-47-53,0-23-65281-10-11-35-16-5-13-18-51-45-43-27-21,29-23-24-25,0',
            'tls_version' => '1.3'
        ]
    ];
    
    private $currentProfileIndex = 0;
    private $lastRotation = 0;
    private $rotationInterval = 15;
    
    public function getCurrentProfile() {
        return $this->profiles[$this->currentProfileIndex];
    }
    
    public function rotateProfile() {
        return $this->getCurrentProfile();
    }
    
    public function rotateTLSProfile() {
        $currentTime = time();
        if (($currentTime - $this->lastRotation) >= $this->rotationInterval) {
            $this->currentProfileIndex = ($this->currentProfileIndex + 1) % count($this->profiles);
            $this->lastRotation = $currentTime;
            
            $currentProfile = $this->profiles[$this->currentProfileIndex];
            $this->logMessage("TLS profile rotated to: " . $currentProfile['name']);
            return true;
        }
        return false;
    }
    
    public function setRotationInterval($seconds) {
        $this->rotationInterval = $seconds;
    }
    
    public function generateRandomJA3() {
        $tlsVersion = rand(771, 774);
        $cipherSuites = $this->generateRandomCipherSuites();
        $extensions = $this->generateRandomExtensions();
        $supportedGroups = '29-23-24-25';
        $ecPointFormats = '0';
        
        return "$tlsVersion,$cipherSuites,$extensions,$supportedGroups,$ecPointFormats";
    }
    
    private function generateRandomCipherSuites() {
        $baseCiphers = [4865, 4866, 4867, 49195, 49199, 49196, 49200, 52393, 52392, 49171, 49172, 49161, 49162, 49, 50, 156, 157, 47, 53];
        shuffle($baseCiphers);
        $count = rand(8, count($baseCiphers));
        $selectedCiphers = array_slice($baseCiphers, 0, $count);
        return implode('-', $selectedCiphers);
    }
    
    private function generateRandomExtensions() {
        $baseExtensions = [0, 23, 65281, 10, 11, 35, 16, 5, 13, 18, 51, 45, 43, 27, 21];
        shuffle($baseExtensions);
        $count = rand(10, count($baseExtensions));
        $selectedExtensions = array_slice($baseExtensions, 0, $count);
        return implode('-', $selectedExtensions);
    }
    
    private function logMessage($message) {
        $logFile = __DIR__ . '/../logs/backend.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] TLS_PROFILE_CLASS: $message" . PHP_EOL;
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

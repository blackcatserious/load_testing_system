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
        ]
    ];
    
    public function getCurrentProfile() {
        return $this->profiles[array_rand($this->profiles)];
    }
    
    public function rotateProfile() {
        return $this->getCurrentProfile();
    }
}

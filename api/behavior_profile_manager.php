<?php

require_once 'stealth_session_reporter.php';

class BehaviorProfileManager {
    private $profilesFile;
    private $profiles;
    private $cookieJars = [];
    private $sessionData = [];
    
    public function __construct($profilesFile = '../attack_profiles.json') {
        $this->profilesFile = $profilesFile;
        $this->loadProfiles();
    }
    
    private function loadProfiles() {
        if (file_exists($this->profilesFile)) {
            $content = file_get_contents($this->profilesFile);
            $data = json_decode($content, true);
            $this->profiles = $data['behavior_profiles'] ?? [];
        } else {
            $this->profiles = [];
        }
    }
    
    public function getProfile($profileId) {
        return $this->profiles[$profileId] ?? null;
    }
    
    public function getAllProfiles() {
        return $this->profiles;
    }
    
    public function initializeThreadCookies($threadId, $profileId) {
        $profile = $this->getProfile($profileId);
        if (!$profile || !($profile['cookie_management']['per_thread_cookies'] ?? false)) {
            return false;
        }
        
        $cookieJarSize = $profile['cookie_management']['cookie_jar_size'] ?? 100;
        $this->cookieJars[$threadId] = [
            'cookies' => [],
            'max_size' => $cookieJarSize,
            'session_id' => uniqid("session_$threadId"),
            'created_at' => time()
        ];
        
        $this->logMessage("Initialized cookie jar for thread $threadId with profile $profileId (max size: $cookieJarSize)");
        return true;
    }
    
    public function setCookie($threadId, $name, $value, $domain = '', $path = '/', $expires = 0) {
        if (!isset($this->cookieJars[$threadId])) {
            return false;
        }
        
        $cookie = [
            'name' => $name,
            'value' => $value,
            'domain' => $domain,
            'path' => $path,
            'expires' => $expires,
            'created_at' => time()
        ];
        
        $this->cookieJars[$threadId]['cookies'][$name] = $cookie;
        
        if (count($this->cookieJars[$threadId]['cookies']) > $this->cookieJars[$threadId]['max_size']) {
            $oldestCookie = array_keys($this->cookieJars[$threadId]['cookies'])[0];
            unset($this->cookieJars[$threadId]['cookies'][$oldestCookie]);
        }
        
        return true;
    }
    
    public function getCookies($threadId, $domain = '') {
        if (!isset($this->cookieJars[$threadId])) {
            return [];
        }
        
        $cookies = $this->cookieJars[$threadId]['cookies'];
        
        if ($domain) {
            $cookies = array_filter($cookies, function($cookie) use ($domain) {
                return empty($cookie['domain']) || $cookie['domain'] === $domain || 
                       str_ends_with($domain, $cookie['domain']);
            });
        }
        
        $validCookies = array_filter($cookies, function($cookie) {
            return $cookie['expires'] === 0 || $cookie['expires'] > time();
        });
        
        return $validCookies;
    }
    
    public function getCookieHeader($threadId, $domain = '') {
        $cookies = $this->getCookies($threadId, $domain);
        
        if (empty($cookies)) {
            return '';
        }
        
        $cookieStrings = [];
        foreach ($cookies as $cookie) {
            $cookieStrings[] = $cookie['name'] . '=' . $cookie['value'];
        }
        
        return implode('; ', $cookieStrings);
    }
    
    public function rotateCookies($threadId, $profileId) {
        $profile = $this->getProfile($profileId);
        if (!$profile || !($profile['cookie_management']['cookie_rotation'] ?? false)) {
            return false;
        }
        
        if (!isset($this->cookieJars[$threadId])) {
            return false;
        }
        
        $rotationPercentage = 0.3;
        $cookies = $this->cookieJars[$threadId]['cookies'];
        $cookieCount = count($cookies);
        $rotateCount = max(1, intval($cookieCount * $rotationPercentage));
        
        $cookieKeys = array_keys($cookies);
        shuffle($cookieKeys);
        
        for ($i = 0; $i < $rotateCount && $i < count($cookieKeys); $i++) {
            unset($this->cookieJars[$threadId]['cookies'][$cookieKeys[$i]]);
        }
        
        $this->logMessage("Rotated $rotateCount cookies for thread $threadId");
        return true;
    }
    
    public function executeHumanBehavior($threadId, $profileId, $target) {
        $profile = $this->getProfile($profileId);
        if (!$profile || !($profile['human_behavior']['enabled'] ?? false)) {
            return ['success' => false, 'reason' => 'human_behavior_disabled'];
        }
        
        $humanBehavior = $profile['human_behavior'];
        $clickPatterns = $humanBehavior['click_patterns'] ?? [];
        $scrollBehavior = $humanBehavior['scroll_behavior'] ?? [];
        $waitTimes = $humanBehavior['wait_times'] ?? ['min' => 100, 'max' => 500];
        $intensity = $humanBehavior['interaction_intensity'] ?? 'moderate';
        
        $actions = [];
        
        if (!empty($clickPatterns)) {
            $clickPattern = $clickPatterns[array_rand($clickPatterns)];
            $actions[] = $this->executeClickPattern($clickPattern, $intensity);
        }
        
        if (!empty($scrollBehavior)) {
            $scrollType = $scrollBehavior[array_rand($scrollBehavior)];
            $actions[] = $this->executeScrollBehavior($scrollType, $intensity);
        }
        
        $waitTime = rand($waitTimes['min'], $waitTimes['max']);
        usleep($waitTime * 1000);
        $actions[] = ['action' => 'wait', 'duration_ms' => $waitTime];
        
        $this->sessionData[$threadId] = [
            'profile_id' => $profileId,
            'target' => $target,
            'actions' => $actions,
            'timestamp' => time()
        ];
        
        return [
            'success' => true,
            'actions_executed' => count($actions),
            'wait_time_ms' => $waitTime,
            'click_pattern' => $clickPattern ?? null,
            'scroll_type' => $scrollType ?? null
        ];
    }
    
    private function executeClickPattern($pattern, $intensity) {
        $clickCounts = [
            'natural' => rand(1, 3),
            'moderate' => rand(2, 5),
            'maximum' => rand(5, 10)
        ];
        
        $clickCount = $clickCounts[$intensity] ?? 2;
        
        return [
            'action' => 'click_pattern',
            'pattern' => $pattern,
            'click_count' => $clickCount,
            'intensity' => $intensity
        ];
    }
    
    private function executeScrollBehavior($scrollType, $intensity) {
        $scrollAmounts = [
            'natural' => rand(100, 300),
            'moderate' => rand(300, 600),
            'maximum' => rand(600, 1200)
        ];
        
        $scrollAmount = $scrollAmounts[$intensity] ?? 300;
        
        return [
            'action' => 'scroll',
            'type' => $scrollType,
            'amount_px' => $scrollAmount,
            'intensity' => $intensity
        ];
    }
    
    public function getThreadSessionData($threadId) {
        return $this->sessionData[$threadId] ?? null;
    }
    
    public function clearThreadData($threadId) {
        unset($this->cookieJars[$threadId]);
        unset($this->sessionData[$threadId]);
        $this->logMessage("Cleared thread data for thread $threadId");
    }
    
    public function getProfileStats($profileId) {
        $profile = $this->getProfile($profileId);
        if (!$profile) {
            return null;
        }
        
        $activeThreads = count($this->cookieJars);
        $totalCookies = 0;
        
        foreach ($this->cookieJars as $jar) {
            $totalCookies += count($jar['cookies']);
        }
        
        return [
            'profile_id' => $profileId,
            'active_threads' => $activeThreads,
            'total_cookies' => $totalCookies,
            'human_behavior_enabled' => $profile['human_behavior']['enabled'] ?? false,
            'cookie_management_enabled' => $profile['cookie_management']['per_thread_cookies'] ?? false,
            'escalation_factor' => $profile['escalation_factor'] ?? 1.0,
            'thread_scaling' => $profile['thread_scaling'] ?? []
        ];
    }
    
    private function logMessage($message) {
        $logFile = '/home/ftcceelg/load_testing_system/logs/backend.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] BEHAVIOR_PROFILE_MANAGER: $message" . PHP_EOL;
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    public function detectProtection($threadId, $responseData) {
        if (!isset($this->sessionData[$threadId])) {
            return false;
        }
        
        $protectionSignals = [
            'captcha_detected' => false,
            'rate_limiting' => false,
            'browser_verification' => false,
            'ip_blocking' => false
        ];
        
        if (isset($responseData['body']) && 
            (strpos($responseData['body'], 'captcha') !== false || 
             strpos($responseData['body'], 'recaptcha') !== false ||
             strpos($responseData['body'], 'hcaptcha') !== false)) {
            $protectionSignals['captcha_detected'] = true;
        }
        
        if (isset($responseData['status_code']) && ($responseData['status_code'] == 429 || $responseData['status_code'] == 403)) {
            $protectionSignals['rate_limiting'] = true;
        }
        
        if (isset($responseData['body']) && 
            (strpos($responseData['body'], 'browser check') !== false || 
             strpos($responseData['body'], 'javascript required') !== false ||
             strpos($responseData['body'], 'checking your browser') !== false ||
             strpos($responseData['body'], 'DDoS protection by') !== false ||
             strpos($responseData['body'], 'Cloudflare') !== false)) {
            $protectionSignals['browser_verification'] = true;
        }
        
        if (isset($responseData['status_code']) && ($responseData['status_code'] == 403 || $responseData['status_code'] == 401)) {
            if (isset($responseData['body']) && 
                (strpos($responseData['body'], 'blocked') !== false || 
                 strpos($responseData['body'], 'access denied') !== false ||
                 strpos($responseData['body'], 'forbidden') !== false)) {
                $protectionSignals['ip_blocking'] = true;
            }
        }
        
        $this->logMessage("Protection detection for thread $threadId: " . json_encode($protectionSignals));
        
        return array_filter($protectionSignals) ? $protectionSignals : false;
    }
}

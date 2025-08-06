<?php
class Database {
    private $pdo;
    private $dbPath;
    
    public function __construct() {
        $this->dbPath = '/home/ftcceelg/load_testing_system/data/results.db';
        $this->ensureDirectoryExists();
        $this->connect();
        $this->initializeTables();
    }
    
    private function ensureDirectoryExists() {
        $dir = dirname($this->dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
    
    private function connect() {
        try {
            $this->pdo = new PDO("sqlite:" . $this->dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function initializeTables() {
        $sql = "
        CREATE TABLE IF NOT EXISTS groups (
            group_id TEXT PRIMARY KEY,
            targets TEXT,
            profile_id TEXT,
            threads INTEGER,
            duration INTEGER,
            engine TEXT,
            behavior_profile_id TEXT,
            started_at TEXT,
            finished_at TEXT,
            status TEXT,
            unlimited_mode BOOLEAN DEFAULT 0,
            stealth_profile TEXT DEFAULT 'medium',
            attack_method TEXT DEFAULT 'standard',
            proxy_profile TEXT DEFAULT 'rotating'
        );
        
        CREATE TABLE IF NOT EXISTS runs (
            run_id TEXT PRIMARY KEY,
            group_id TEXT,
            target TEXT,
            status TEXT,
            started_at TEXT,
            finished_at TEXT,
            target_status TEXT DEFAULT 'active',
            target_url TEXT,
            stealth_session_id TEXT,
            success_detection_triggered BOOLEAN DEFAULT 0,
            permanent_failure_achieved BOOLEAN DEFAULT 0,
            protection_rate REAL DEFAULT 0.0,
            escalation_count INTEGER DEFAULT 0,
            FOREIGN KEY (group_id) REFERENCES groups(group_id)
        );
        
        CREATE TABLE IF NOT EXISTS metrics (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            timestamp TEXT,
            rps REAL,
            threads INTEGER,
            total_requests INTEGER,
            success_rate REAL,
            latency_p50 REAL,
            latency_p95 REAL,
            latency_p99 REAL,
            codes TEXT
        );
        
        CREATE TABLE IF NOT EXISTS stealth_profiles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            profile_name TEXT UNIQUE,
            user_agents TEXT,
            ja3_fingerprints TEXT,
            tls_configs TEXT,
            created_at TEXT,
            enabled BOOLEAN DEFAULT 1
        );
        
        CREATE TABLE IF NOT EXISTS proxy_pool (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip_address TEXT,
            port INTEGER,
            protocol TEXT,
            status TEXT,
            last_check TEXT,
            response_time INTEGER,
            success_count INTEGER,
            failure_count INTEGER,
            consecutive_failures INTEGER DEFAULT 0,
            country TEXT,
            provider TEXT,
            health_check_passed BOOLEAN DEFAULT 0,
            tls_handshake_success BOOLEAN DEFAULT 0,
            last_used TEXT,
            rotation_count INTEGER DEFAULT 0
        );
        
        CREATE TABLE IF NOT EXISTS attack_methods (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            method_name TEXT UNIQUE,
            engine_class TEXT,
            config_schema TEXT,
            enabled BOOLEAN DEFAULT 1,
            description TEXT,
            created_at TEXT
        );
        
        CREATE TABLE IF NOT EXISTS stealth_sessions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            session_id TEXT UNIQUE,
            group_id TEXT,
            run_id TEXT,
            stealth_profile_id INTEGER,
            proxy_rotations INTEGER DEFAULT 0,
            ua_rotations INTEGER DEFAULT 0,
            tls_rotations INTEGER DEFAULT 0,
            ja3_rotations INTEGER DEFAULT 0,
            cookie_rotations INTEGER DEFAULT 0,
            detection_events INTEGER DEFAULT 0,
            parent_session TEXT,
            stealth_config TEXT,
            rotation_settings TEXT,
            started_at TEXT,
            FOREIGN KEY (group_id) REFERENCES groups(group_id),
            FOREIGN KEY (run_id) REFERENCES runs(run_id),
            FOREIGN KEY (stealth_profile_id) REFERENCES stealth_profiles(id)
        );
        
        CREATE TABLE IF NOT EXISTS escalation_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            group_id TEXT NOT NULL,
            run_id TEXT,
            resistance_level INTEGER NOT NULL,
            recommendation TEXT,
            error_codes TEXT,
            escalation_decision TEXT,
            thread_count INTEGER,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (run_id) REFERENCES runs(run_id)
        );
        
        CREATE TABLE IF NOT EXISTS success_detection_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            run_id TEXT NOT NULL,
            group_id TEXT NOT NULL,
            target_url TEXT NOT NULL,
            event_type TEXT NOT NULL,
            detection_criteria TEXT NOT NULL,
            success_metrics TEXT,
            permanent_failure_rate REAL,
            latency_threshold_exceeded BOOLEAN DEFAULT 0,
            zero_byte_responses BOOLEAN DEFAULT 0,
            protection_activated BOOLEAN DEFAULT 0,
            escalation_successful BOOLEAN DEFAULT 0,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (run_id) REFERENCES runs(run_id),
            FOREIGN KEY (group_id) REFERENCES groups(group_id)
        );";
        
        $this->pdo->exec($sql);
    }
    
    public function getPDO() {
        return $this->pdo;
    }
    
    public function insertGroup($groupId, $targets, $profileId, $threads, $duration, $engine, $behaviorProfileId) {
        $stmt = $this->pdo->prepare("INSERT INTO groups (group_id, targets, profile_id, threads, duration, engine, behavior_profile_id, started_at, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([
            $groupId,
            json_encode($targets),
            $profileId,
            $threads,
            $duration,
            $engine,
            $behaviorProfileId,
            date('Y-m-d H:i:s'),
            'running'
        ]);
    }
    
    public function insertRun($runId, $groupId, $target) {
        $stmt = $this->pdo->prepare("INSERT INTO runs (run_id, group_id, target, target_url, status, started_at, target_status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([
            $runId,
            $groupId,
            $target,
            $target,
            'running',
            date('Y-m-d H:i:s'),
            'active'
        ]);
    }
    
    public function getGroup($groupId) {
        $stmt = $this->pdo->prepare("SELECT * FROM groups WHERE group_id = ?");
        $stmt->execute([$groupId]);
        return $stmt->fetch();
    }
    
    public function getGroupRuns($groupId) {
        $stmt = $this->pdo->prepare("SELECT * FROM runs WHERE group_id = ?");
        $stmt->execute([$groupId]);
        return $stmt->fetchAll();
    }
    
    public function getAllGroups($limit = 50) {
        $stmt = $this->pdo->prepare("SELECT * FROM groups ORDER BY started_at DESC LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
    
    public function updateGroupStatus($groupId, $status) {
        $stmt = $this->pdo->prepare("UPDATE groups SET status = ?, finished_at = ? WHERE group_id = ?");
        return $stmt->execute([$status, date('Y-m-d H:i:s'), $groupId]);
    }
    
    public function updateRunsStatus($groupId, $status) {
        $stmt = $this->pdo->prepare("UPDATE runs SET status = ?, finished_at = ? WHERE group_id = ?");
        return $stmt->execute([$status, date('Y-m-d H:i:s'), $groupId]);
    }
    
    public function getActiveGroupsCount() {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM groups WHERE status = 'running'");
        $stmt->execute();
        return $stmt->fetch()['count'];
    }
    
    public function getActiveRunsCount() {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM runs WHERE status = 'running'");
        $stmt->execute();
        return $stmt->fetch()['count'];
    }
    
    public function insertMetrics($rps, $threads, $totalRequests, $successRate, $latencyP50, $latencyP95, $latencyP99, $codes) {
        $stmt = $this->pdo->prepare("INSERT INTO metrics (timestamp, rps, threads, total_requests, success_rate, latency_p50, latency_p95, latency_p99, codes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([
            date('Y-m-d H:i:s'),
            $rps,
            $threads,
            $totalRequests,
            $successRate,
            $latencyP50,
            $latencyP95,
            $latencyP99,
            json_encode($codes)
        ]);
    }
    
    public function getLatestMetrics() {
        $stmt = $this->pdo->prepare("SELECT * FROM metrics ORDER BY timestamp DESC LIMIT 1");
        $stmt->execute();
        return $stmt->fetch();
    }
    
    public function insertStealthProfile($profileName, $userAgents, $ja3Fingerprints, $tlsConfigs) {
        $stmt = $this->pdo->prepare("INSERT INTO stealth_profiles (profile_name, user_agents, ja3_fingerprints, tls_configs, created_at) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([
            $profileName,
            json_encode($userAgents),
            json_encode($ja3Fingerprints),
            json_encode($tlsConfigs),
            date('Y-m-d H:i:s')
        ]);
    }
    
    public function getStealthProfile($profileId) {
        $stmt = $this->pdo->prepare("SELECT * FROM stealth_profiles WHERE id = ? AND enabled = 1");
        $stmt->execute([$profileId]);
        return $stmt->fetch();
    }
    
    public function getAllStealthProfiles() {
        $stmt = $this->pdo->prepare("SELECT * FROM stealth_profiles WHERE enabled = 1 ORDER BY created_at DESC");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function insertProxy($ipAddress, $port, $protocol, $country = null, $provider = null) {
        $stmt = $this->pdo->prepare("INSERT INTO proxy_pool (ip_address, port, protocol, status, last_check, response_time, success_count, failure_count, country, provider) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([
            $ipAddress,
            $port,
            $protocol,
            'unchecked',
            date('Y-m-d H:i:s'),
            0,
            0,
            0,
            $country,
            $provider
        ]);
    }
    
    public function updateProxyStatus($proxyId, $status, $responseTime) {
        $stmt = $this->pdo->prepare("UPDATE proxy_pool SET status = ?, last_check = ?, response_time = ?, success_count = success_count + ?, failure_count = failure_count + ? WHERE id = ?");
        $successIncrement = ($status === 'alive') ? 1 : 0;
        $failureIncrement = ($status === 'dead') ? 1 : 0;
        return $stmt->execute([$status, date('Y-m-d H:i:s'), $responseTime, $successIncrement, $failureIncrement, $proxyId]);
    }
    
    public function getActiveProxies($limit = 100) {
        $stmt = $this->pdo->prepare("SELECT * FROM proxy_pool WHERE status = 'alive' ORDER BY response_time ASC LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
    
    public function getProxyStats() {
        $stmt = $this->pdo->prepare("SELECT status, COUNT(*) as count FROM proxy_pool GROUP BY status");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function removeDeadProxy($proxyId) {
        $stmt = $this->pdo->prepare("DELETE FROM proxy_pool WHERE id = ?");
        return $stmt->execute([$proxyId]);
    }
    
    public function getProxyRotationPool($limit = 10000) {
        $stmt = $this->pdo->prepare("SELECT * FROM proxy_pool WHERE status = 'alive' AND failure_count < 3 ORDER BY RANDOM() LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
    
    public function markProxyAsUsed($proxyId) {
        $stmt = $this->pdo->prepare("UPDATE proxy_pool SET success_count = success_count + 1, last_check = ? WHERE id = ?");
        return $stmt->execute([date('Y-m-d H:i:s'), $proxyId]);
    }
    
    public function getProxyPoolSize() {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'alive' THEN 1 ELSE 0 END) as alive FROM proxy_pool");
        $stmt->execute();
        return $stmt->fetch();
    }
    
    public function insertAttackMethod($methodName, $engineClass, $configSchema, $description) {
        $stmt = $this->pdo->prepare("INSERT INTO attack_methods (method_name, engine_class, config_schema, description, created_at) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([
            $methodName,
            $engineClass,
            json_encode($configSchema),
            $description,
            date('Y-m-d H:i:s')
        ]);
    }
    
    public function getAttackMethod($methodName) {
        $stmt = $this->pdo->prepare("SELECT * FROM attack_methods WHERE method_name = ? AND enabled = 1");
        $stmt->execute([$methodName]);
        return $stmt->fetch();
    }
    
    public function getAllAttackMethods() {
        $stmt = $this->pdo->prepare("SELECT * FROM attack_methods WHERE enabled = 1 ORDER BY method_name");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function insertStealthSession($sessionId, $runId, $stealthConfig, $groupId = null, $stealthProfileId = null) {
        $stmt = $this->pdo->prepare("INSERT INTO stealth_sessions (session_id, group_id, run_id, stealth_profile_id, stealth_config, rotation_settings, started_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([
            $sessionId,
            $groupId,
            $runId,
            $stealthProfileId,
            json_encode($stealthConfig),
            json_encode($stealthConfig['rotation_settings'] ?? []),
            date('Y-m-d H:i:s')
        ]);
    }
    
    public function updateStealthSessionStats($groupId, $proxyRotations = 0, $uaRotations = 0, $tlsRotations = 0, $detectionEvents = 0) {
        $stmt = $this->pdo->prepare("UPDATE stealth_sessions SET proxy_rotations = proxy_rotations + ?, ua_rotations = ua_rotations + ?, tls_rotations = tls_rotations + ?, detection_events = detection_events + ? WHERE group_id = ?");
        return $stmt->execute([$proxyRotations, $uaRotations, $tlsRotations, $detectionEvents, $groupId]);
    }
    
    public function getStealthSessionStats($groupId) {
        $stmt = $this->pdo->prepare("SELECT * FROM stealth_sessions WHERE group_id = ?");
        $stmt->execute([$groupId]);
        return $stmt->fetch();
    }
    
    public function insertEscalationEvent($groupId, $resistanceLevel, $recommendation, $errorCodes) {
        $stmt = $this->pdo->prepare("INSERT INTO escalation_events (group_id, resistance_level, recommendation, error_codes, created_at) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$groupId, $resistanceLevel, json_encode($recommendation), $errorCodes, date('Y-m-d H:i:s')]);
    }
    
    public function getEscalationHistory($groupId, $limit = 10) {
        $stmt = $this->pdo->prepare("SELECT * FROM escalation_events WHERE group_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$groupId, $limit]);
        return $stmt->fetchAll();
    }
    
    public function getResistanceMetrics($groupId) {
        $stmt = $this->pdo->prepare("SELECT AVG(resistance_level) as avg_resistance, MAX(resistance_level) as max_resistance, COUNT(*) as total_events FROM escalation_events WHERE group_id = ?");
        $stmt->execute([$groupId]);
        return $stmt->fetch();
    }
    
    public function insertSuccessDetectionEvent($runId, $groupId, $targetUrl, $eventType, $detectionCriteria, $successMetrics = []) {
        $stmt = $this->pdo->prepare("INSERT INTO success_detection_events (run_id, group_id, target_url, event_type, detection_criteria, success_metrics, permanent_failure_rate, latency_threshold_exceeded, zero_byte_responses, protection_activated, escalation_successful, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([
            $runId,
            $groupId,
            $targetUrl,
            $eventType,
            $detectionCriteria,
            json_encode($successMetrics),
            $successMetrics['permanent_failure_rate'] ?? 0.0,
            $successMetrics['latency_threshold_exceeded'] ?? false,
            $successMetrics['zero_byte_responses'] ?? false,
            $successMetrics['protection_activated'] ?? false,
            $successMetrics['escalation_successful'] ?? false,
            date('Y-m-d H:i:s')
        ]);
    }
    
    public function updateRunTargetStatus($runId, $targetStatus, $successDetection = false, $permanentFailure = false) {
        $stmt = $this->pdo->prepare("UPDATE runs SET target_status = ?, success_detection_triggered = ?, permanent_failure_achieved = ? WHERE run_id = ?");
        return $stmt->execute([$targetStatus, $successDetection, $permanentFailure, $runId]);
    }
    
    public function updateProxyConsecutiveFailures($proxyId, $failures) {
        $stmt = $this->pdo->prepare("UPDATE proxy_pool SET consecutive_failures = ?, last_check = ? WHERE id = ?");
        return $stmt->execute([$failures, date('Y-m-d H:i:s'), $proxyId]);
    }
    
    public function removeProxiesWithConsecutiveFailures($threshold = 3) {
        $stmt = $this->pdo->prepare("DELETE FROM proxy_pool WHERE consecutive_failures >= ?");
        return $stmt->execute([$threshold]);
    }
    
    public function getSuccessDetectionHistory($groupId, $limit = 50) {
        $stmt = $this->pdo->prepare("SELECT * FROM success_detection_events WHERE group_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$groupId, $limit]);
        return $stmt->fetchAll();
    }
    
    public function getDisabledTargetsCount($groupId) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM runs WHERE group_id = ? AND target_status = 'disabled'");
        $stmt->execute([$groupId]);
        return $stmt->fetch()['count'];
    }
    
    public function updateStealthSessionRotations($sessionId, $proxyRotations = 0, $uaRotations = 0, $tlsRotations = 0, $ja3Rotations = 0, $cookieRotations = 0) {
        $stmt = $this->pdo->prepare("UPDATE stealth_sessions SET proxy_rotations = proxy_rotations + ?, ua_rotations = ua_rotations + ?, tls_rotations = tls_rotations + ?, ja3_rotations = ja3_rotations + ?, cookie_rotations = cookie_rotations + ? WHERE session_id = ?");
        return $stmt->execute([$proxyRotations, $uaRotations, $tlsRotations, $ja3Rotations, $cookieRotations, $sessionId]);
    }
}
?>

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
            status TEXT
        );
        
        CREATE TABLE IF NOT EXISTS runs (
            run_id TEXT PRIMARY KEY,
            group_id TEXT,
            target TEXT,
            status TEXT,
            started_at TEXT,
            finished_at TEXT,
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
            country TEXT,
            provider TEXT
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
            group_id TEXT,
            stealth_profile_id INTEGER,
            proxy_rotations INTEGER DEFAULT 0,
            ua_rotations INTEGER DEFAULT 0,
            tls_rotations INTEGER DEFAULT 0,
            detection_events INTEGER DEFAULT 0,
            started_at TEXT,
            FOREIGN KEY (group_id) REFERENCES groups(group_id),
            FOREIGN KEY (stealth_profile_id) REFERENCES stealth_profiles(id)
        );
        
        CREATE TABLE IF NOT EXISTS escalation_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            group_id TEXT NOT NULL,
            resistance_level INTEGER NOT NULL,
            recommendation TEXT,
            error_codes TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
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
        $stmt = $this->pdo->prepare("INSERT INTO runs (run_id, group_id, target, status, started_at) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([
            $runId,
            $groupId,
            $target,
            'running',
            date('Y-m-d H:i:s')
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
    
    public function insertStealthSession($groupId, $stealthProfileId) {
        $stmt = $this->pdo->prepare("INSERT INTO stealth_sessions (group_id, stealth_profile_id, started_at) VALUES (?, ?, ?)");
        return $stmt->execute([
            $groupId,
            $stealthProfileId,
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
}
?>

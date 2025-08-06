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
}
?>

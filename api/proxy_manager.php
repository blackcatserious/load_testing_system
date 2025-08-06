<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'database.php';

function logMessage($message) {
    $logFile = '/home/ftcceelg/load_testing_system/logs/backend.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] PROXY_MANAGER: $message\n", FILE_APPEND | LOCK_EX);
}

function logProxyStatus($message) {
    $logFile = '/home/ftcceelg/load_testing_system/logs/proxy_health.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

try {
    $db = new Database();
    $method = $_SERVER['REQUEST_METHOD'];
    
    logMessage("Request received: $method " . $_SERVER['REQUEST_URI']);
    
    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'stats';
        
        switch ($action) {
            case 'stats':
                $stats = $db->getProxyStats();
                $totalProxies = 0;
                $aliveProxies = 0;
                $deadProxies = 0;
                
                foreach ($stats as $stat) {
                    $totalProxies += $stat['count'];
                    if ($stat['status'] === 'alive') {
                        $aliveProxies = $stat['count'];
                    } elseif ($stat['status'] === 'dead') {
                        $deadProxies = $stat['count'];
                    }
                }
                
                logMessage("Retrieved proxy stats: $totalProxies total, $aliveProxies alive, $deadProxies dead");
                echo json_encode([
                    'success' => true,
                    'stats' => [
                        'total_proxies' => $totalProxies,
                        'active_proxies' => $aliveProxies,
                        'dead_proxies' => $deadProxies,
                        'rotation_count' => rand(1000, 5000),
                        'success_rate' => $totalProxies > 0 ? round(($aliveProxies / $totalProxies) * 100, 2) : 0
                    ]
                ]);
                break;
                
            case 'list':
                $limit = $_GET['limit'] ?? 100;
                $status = $_GET['status'] ?? 'alive';
                
                if ($status === 'alive') {
                    $proxies = $db->getActiveProxies($limit);
                } else {
                    $proxies = getAllProxiesByStatus($db, $status, $limit);
                }
                
                logMessage("Retrieved " . count($proxies) . " proxies with status: $status");
                echo json_encode([
                    'success' => true,
                    'proxies' => $proxies,
                    'count' => count($proxies)
                ]);
                break;
                
            case 'health_check':
                $proxyId = $_GET['proxy_id'] ?? null;
                if ($proxyId) {
                    $result = performHealthCheck($db, $proxyId);
                    echo json_encode([
                        'success' => true,
                        'health_check' => $result
                    ]);
                } else {
                    $results = performBulkHealthCheck($db);
                    echo json_encode([
                        'success' => true,
                        'health_checks' => $results
                    ]);
                }
                break;
                
            case 'random':
                $count = $_GET['count'] ?? 10;
                $proxies = $db->getActiveProxies($count);
                shuffle($proxies);
                
                logMessage("Retrieved $count random active proxies");
                echo json_encode([
                    'success' => true,
                    'proxies' => array_slice($proxies, 0, $count)
                ]);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? 'add';
        
        switch ($action) {
            case 'add':
                $ipAddress = $input['ip_address'] ?? null;
                $port = $input['port'] ?? 8080;
                $protocol = $input['protocol'] ?? 'http';
                $country = $input['country'] ?? null;
                $provider = $input['provider'] ?? null;
                
                if (!$ipAddress) {
                    throw new Exception('IP address is required');
                }
                
                $result = $db->insertProxy($ipAddress, $port, $protocol, $country, $provider);
                
                if ($result) {
                    logMessage("Added proxy: $ipAddress:$port ($protocol)");
                    logProxyStatus("ADDED: $ipAddress:$port - Status: unchecked");
                    echo json_encode([
                        'success' => true,
                        'message' => 'Proxy added successfully'
                    ]);
                } else {
                    throw new Exception('Failed to add proxy');
                }
                break;
                
            case 'bulk_import':
                $proxies = $input['proxies'] ?? [];
                $source = $input['source'] ?? 'manual';
                
                if (empty($proxies)) {
                    throw new Exception('No proxies provided');
                }
                
                $imported = 0;
                $failed = 0;
                
                foreach ($proxies as $proxy) {
                    try {
                        $result = $db->insertProxy(
                            $proxy['ip_address'],
                            $proxy['port'] ?? 8080,
                            $proxy['protocol'] ?? 'http',
                            $proxy['country'] ?? null,
                            $proxy['provider'] ?? $source
                        );
                        
                        if ($result) {
                            $imported++;
                            logProxyStatus("IMPORTED: {$proxy['ip_address']}:{$proxy['port']} - Source: $source");
                        } else {
                            $failed++;
                        }
                    } catch (Exception $e) {
                        $failed++;
                        logMessage("Failed to import proxy {$proxy['ip_address']}: " . $e->getMessage());
                    }
                }
                
                logMessage("Bulk import completed: $imported imported, $failed failed from source: $source");
                echo json_encode([
                    'success' => true,
                    'imported' => $imported,
                    'failed' => $failed,
                    'message' => "Imported $imported proxies, $failed failed"
                ]);
                break;
                
            case 'api_import':
                $apiUrl = $input['api_url'] ?? null;
                $apiKey = $input['api_key'] ?? null;
                $format = $input['format'] ?? 'json';
                
                if (!$apiUrl) {
                    throw new Exception('API URL is required');
                }
                
                $result = importProxiesFromAPI($db, $apiUrl, $apiKey, $format);
                echo json_encode($result);
                break;
                
            case 'health_check_all':
                $maxProxies = $input['max_proxies'] ?? 1000;
                $timeout = $input['timeout'] ?? 5;
                
                $results = performBulkHealthCheck($db, $maxProxies, $timeout);
                
                logMessage("Performed health check on " . count($results) . " proxies");
                echo json_encode([
                    'success' => true,
                    'results' => $results
                ]);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    }
    
} catch (Exception $e) {
    logMessage("Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function getAllProxiesByStatus($db, $status, $limit) {
    $pdo = $db->getPDO();
    $stmt = $pdo->prepare("SELECT * FROM proxy_pool WHERE status = ? ORDER BY last_check DESC LIMIT ?");
    $stmt->execute([$status, $limit]);
    return $stmt->fetchAll();
}

function performHealthCheck($db, $proxyId) {
    $pdo = $db->getPDO();
    $stmt = $pdo->prepare("SELECT * FROM proxy_pool WHERE id = ?");
    $stmt->execute([$proxyId]);
    $proxy = $stmt->fetch();
    
    if (!$proxy) {
        throw new Exception('Proxy not found');
    }
    
    $startTime = microtime(true);
    $status = 'dead';
    $responseTime = 0;
    
    try {
        $proxyUrl = "{$proxy['protocol']}://{$proxy['ip_address']}:{$proxy['port']}";
        $testUrl = 'http://httpbin.org/ip';
        
        $context = stream_context_create([
            'http' => [
                'proxy' => $proxyUrl,
                'request_fulluri' => true,
                'timeout' => 5,
                'method' => 'GET',
                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n"
            ]
        ]);
        
        $response = @file_get_contents($testUrl, false, $context);
        $responseTime = round((microtime(true) - $startTime) * 1000);
        
        if ($response !== false && $responseTime < 500) {
            $status = 'alive';
            logProxyStatus("ALIVE: {$proxy['ip_address']}:{$proxy['port']} - Response time: {$responseTime}ms");
        } else {
            logProxyStatus("DEAD: {$proxy['ip_address']}:{$proxy['port']} - Response time: {$responseTime}ms");
        }
        
    } catch (Exception $e) {
        $responseTime = round((microtime(true) - $startTime) * 1000);
        logProxyStatus("DEAD: {$proxy['ip_address']}:{$proxy['port']} - Error: " . $e->getMessage());
    }
    
    $db->updateProxyStatus($proxyId, $status, $responseTime);
    
    return [
        'proxy_id' => $proxyId,
        'ip_address' => $proxy['ip_address'],
        'port' => $proxy['port'],
        'status' => $status,
        'response_time' => $responseTime
    ];
}

function performBulkHealthCheck($db, $maxProxies = 1000, $timeout = 5) {
    $pdo = $db->getPDO();
    $stmt = $pdo->prepare("SELECT * FROM proxy_pool WHERE status != 'checking' ORDER BY last_check ASC LIMIT ?");
    $stmt->execute([$maxProxies]);
    $proxies = $stmt->fetchAll();
    
    $results = [];
    $aliveCount = 0;
    $deadCount = 0;
    
    foreach ($proxies as $proxy) {
        $result = performHealthCheck($db, $proxy['id']);
        $results[] = $result;
        
        if ($result['status'] === 'alive') {
            $aliveCount++;
        } else {
            $deadCount++;
        }
    }
    
    logMessage("Bulk health check completed: $aliveCount alive, $deadCount dead out of " . count($proxies) . " checked");
    logProxyStatus("BULK_CHECK: Checked " . count($proxies) . " proxies - $aliveCount alive, $deadCount dead");
    
    return [
        'checked' => count($proxies),
        'alive' => $aliveCount,
        'dead' => $deadCount,
        'results' => $results
    ];
}

function importProxiesFromAPI($db, $apiUrl, $apiKey = null, $format = 'json') {
    $headers = [
        'User-Agent: LoadTestingSystem/1.1.0',
        'Accept: application/json'
    ];
    
    if ($apiKey) {
        $headers[] = "Authorization: Bearer $apiKey";
    }
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers),
            'timeout' => 30
        ]
    ]);
    
    $response = @file_get_contents($apiUrl, false, $context);
    
    if ($response === false) {
        throw new Exception('Failed to fetch proxies from API');
    }
    
    $proxies = [];
    
    if ($format === 'json') {
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from API');
        }
        
        if (isset($data['proxies'])) {
            $proxies = $data['proxies'];
        } elseif (is_array($data)) {
            $proxies = $data;
        }
    } elseif ($format === 'text') {
        $lines = explode("\n", trim($response));
        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/^(\d+\.\d+\.\d+\.\d+):(\d+)$/', $line, $matches)) {
                $proxies[] = [
                    'ip_address' => $matches[1],
                    'port' => (int)$matches[2],
                    'protocol' => 'http'
                ];
            }
        }
    }
    
    if (empty($proxies)) {
        throw new Exception('No valid proxies found in API response');
    }
    
    $imported = 0;
    $failed = 0;
    
    foreach ($proxies as $proxy) {
        try {
            $result = $db->insertProxy(
                $proxy['ip_address'],
                $proxy['port'] ?? 8080,
                $proxy['protocol'] ?? 'http',
                $proxy['country'] ?? null,
                'api_import'
            );
            
            if ($result) {
                $imported++;
                logProxyStatus("API_IMPORT: {$proxy['ip_address']}:{$proxy['port']} - Source: $apiUrl");
            } else {
                $failed++;
            }
        } catch (Exception $e) {
            $failed++;
        }
    }
    
    logMessage("API import completed: $imported imported, $failed failed from: $apiUrl");
    
    return [
        'success' => true,
        'imported' => $imported,
        'failed' => $failed,
        'source' => $apiUrl,
        'message' => "Imported $imported proxies from API, $failed failed"
    ];
}

function generateXForwardedForHeaders($realIp = null) {
    if (!$realIp) {
        $realIp = generateRandomIP();
    }
    
    $fakeIps = [
        generateRandomIP(),
        generateRandomIP(),
        generateRandomIP()
    ];
    
    return [
        'X-Forwarded-For' => implode(', ', $fakeIps) . ', ' . $realIp,
        'X-Real-IP' => $realIp,
        'X-Originating-IP' => generateRandomIP(),
        'X-Remote-IP' => generateRandomIP(),
        'X-Remote-Addr' => generateRandomIP()
    ];
}

function generateRandomIP() {
    return rand(1, 254) . '.' . rand(1, 254) . '.' . rand(1, 254) . '.' . rand(1, 254);
}
?>

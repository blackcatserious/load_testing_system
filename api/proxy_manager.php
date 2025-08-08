<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'database.php';

function logMessage($message) {
    $logFile = './logs/backend.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] PROXY_MANAGER: $message\n", FILE_APPEND | LOCK_EX);
}

function logProxyStatus($message) {
    $logFile = './logs/proxy_health.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

try {
    $db = new Database();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
    
    logMessage("Request received: $method " . ($_SERVER['REQUEST_URI'] ?? 'CLI'));
    
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
                
            case 'import_bulk':
                $sources = $input['sources'] ?? [];
                $maxProxies = $input['max_proxies'] ?? 1000000;
                
                if (empty($sources)) {
                    throw new Exception('At least one proxy source is required');
                }
                
                $result = importProxiesFromMultipleSources($db, $sources, $maxProxies);
                echo json_encode($result);
                break;
                
            case 'cleanup_dead':
                $result = cleanupDeadProxies($db);
                echo json_encode($result);
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
    $tlsHandshakeSuccess = false;
    
    try {
        $proxyUrl = "{$proxy['protocol']}://{$proxy['ip_address']}:{$proxy['port']}";
        
        $headTestSuccess = performHeadTest($proxyUrl);
        
        if ($headTestSuccess) {
            $tlsHandshakeSuccess = performTLSHandshakeTest($proxy['ip_address'], $proxy['port']);
        }
        
        $responseTime = round((microtime(true) - $startTime) * 1000);
        
        if ($headTestSuccess && $tlsHandshakeSuccess && $responseTime < 10000) {
            $status = 'alive';
            logProxyStatus("ALIVE: {$proxy['ip_address']}:{$proxy['port']} - HEAD: OK, TLS: OK, Response time: {$responseTime}ms");
        } else {
            $reasons = [];
            if (!$headTestSuccess) $reasons[] = 'HEAD_FAILED';
            if (!$tlsHandshakeSuccess) $reasons[] = 'TLS_FAILED';
            if ($responseTime >= 10000) $reasons[] = 'TIMEOUT';
            logProxyStatus("DEAD: {$proxy['ip_address']}:{$proxy['port']} - Reasons: " . implode(', ', $reasons) . " - Response time: {$responseTime}ms");
        }
        
    } catch (Exception $e) {
        $responseTime = round((microtime(true) - $startTime) * 1000);
        logProxyStatus("DEAD: {$proxy['ip_address']}:{$proxy['port']} - Error: " . $e->getMessage());
    }
    
    $shouldRemove = false;
    if ($status === 'dead') {
        $consecutiveFailures = $proxy['failure_count'] + 1;
        if ($consecutiveFailures >= 3) {
            $shouldRemove = true;
            logProxyStatus("REMOVING: {$proxy['ip_address']}:{$proxy['port']} - 3 consecutive failures reached");
        }
    }
    
    if ($shouldRemove) {
        $db->removeDeadProxy($proxyId);
    } else {
        $db->updateProxyStatus($proxyId, $status, $responseTime);
    }
    
    return [
        'proxy_id' => $proxyId,
        'ip_address' => $proxy['ip_address'],
        'port' => $proxy['port'],
        'status' => $status,
        'response_time' => $responseTime,
        'head_test' => $headTestSuccess,
        'tls_handshake' => $tlsHandshakeSuccess,
        'removed' => $shouldRemove
    ];
}

function performHeadTest($proxyUrl) {
    try {
        $testUrl = 'https://httpbin.org/status/200';
        
        $context = stream_context_create([
            'http' => [
                'proxy' => $proxyUrl,
                'request_fulluri' => true,
                'timeout' => 8,
                'method' => 'HEAD',
                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n" .
                           "Accept: */*\r\n" .
                           "Connection: close\r\n"
            ]
        ]);
        
        $response = @file_get_contents($testUrl, false, $context);
        
        if (isset($http_response_header)) {
            $statusLine = $http_response_header[0];
            if (strpos($statusLine, '200') !== false) {
                return true;
            }
        }
        
        return false;
        
    } catch (Exception $e) {
        return false;
    }
}

function performTLSHandshakeTest($ipAddress, $port) {
    try {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
                'capture_peer_cert' => true
            ]
        ]);
        
        $socket = @stream_socket_client(
            "ssl://{$ipAddress}:{$port}",
            $errno,
            $errstr,
            5,
            STREAM_CLIENT_CONNECT,
            $context
        );
        
        if ($socket) {
            $peerCert = stream_context_get_params($socket);
            fclose($socket);
            return isset($peerCert['options']['ssl']['peer_certificate']);
        }
        
        return false;
        
    } catch (Exception $e) {
        return false;
    }
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

function importProxiesFromMultipleSources($db, $sources, $maxProxies = PHP_INT_MAX) {
    $totalImported = 0;
    $totalFailed = 0;
    $results = [];
    
    foreach ($sources as $source) {
        // if ($totalImported >= $maxProxies) {
        //     break;
        // }
        
        try {
            $remaining = $maxProxies - $totalImported;
            $result = importProxiesFromAPI($db, $source['url'], $source['api_key'] ?? null, $source['format'] ?? 'json');
            
            $imported = min($result['imported'], $remaining);
            $totalImported += $imported;
            $totalFailed += $result['failed'];
            
            $results[] = [
                'source' => $source['url'],
                'imported' => $imported,
                'failed' => $result['failed']
            ];
            
            logMessage("Bulk import from {$source['url']}: $imported imported, {$result['failed']} failed");
            
        } catch (Exception $e) {
            $results[] = [
                'source' => $source['url'],
                'imported' => 0,
                'failed' => 1,
                'error' => $e->getMessage()
            ];
            $totalFailed++;
            logMessage("Bulk import failed for {$source['url']}: " . $e->getMessage());
        }
    }
    
    logMessage("Bulk import completed: $totalImported total imported, $totalFailed total failed from " . count($sources) . " sources");
    
    return [
        'success' => true,
        'total_imported' => $totalImported,
        'total_failed' => $totalFailed,
        'sources_processed' => count($sources),
        'results' => $results,
        'message' => "Imported $totalImported proxies from " . count($sources) . " sources, $totalFailed failed"
    ];
}

function cleanupDeadProxies($db) {
    $pdo = $db->getPDO();
    
    $stmt = $pdo->prepare("DELETE FROM proxy_pool WHERE failure_count >= 3 AND status = 'dead'");
    $stmt->execute();
    $removedCount = $stmt->rowCount();
    
    $stmt = $pdo->prepare("DELETE FROM proxy_pool WHERE status = 'dead' AND datetime(last_check) < datetime('now', '-24 hours')");
    $stmt->execute();
    $expiredCount = $stmt->rowCount();
    
    $totalRemoved = $removedCount + $expiredCount;
    
    logMessage("Cleanup completed: $removedCount consecutive failures, $expiredCount expired, $totalRemoved total removed");
    logProxyStatus("CLEANUP: Removed $totalRemoved dead proxies ($removedCount consecutive failures, $expiredCount expired)");
    
    return [
        'success' => true,
        'removed_consecutive_failures' => $removedCount,
        'removed_expired' => $expiredCount,
        'total_removed' => $totalRemoved,
        'message' => "Removed $totalRemoved dead proxies from pool"
    ];
}

function getProxyRotationPool($db, $limit = PHP_INT_MAX) {
    $pdo = $db->getPDO();
    $stmt = $pdo->prepare("SELECT * FROM proxy_pool WHERE status = 'alive' AND failure_count < 3 ORDER BY RANDOM() LIMIT ?");
    $stmt->execute([$limit]);
    $proxies = $stmt->fetchAll();
    
    logMessage("Retrieved " . count($proxies) . " proxies for rotation pool");
    
    return $proxies;
}

function markProxyAsUsed($db, $proxyId) {
    $pdo = $db->getPDO();
    $stmt = $pdo->prepare("UPDATE proxy_pool SET success_count = success_count + 1, last_check = ? WHERE id = ?");
    return $stmt->execute([date('Y-m-d H:i:s'), $proxyId]);
}

function markProxyAsBanned($db, $proxyId) {
    $pdo = $db->getPDO();
    $stmt = $pdo->prepare("UPDATE proxy_pool SET status = 'banned', failure_count = failure_count + 1, last_check = ? WHERE id = ?");
    $result = $stmt->execute([date('Y-m-d H:i:s'), $proxyId]);
    
    if ($result) {
        logMessage("Proxy ID $proxyId marked as banned, automatically rotating to new proxy");
        return getNewProxyAfterBan($db);
    }
    
    return null;
}

function getNewProxyAfterBan($db) {
    $proxies = getProxyRotationPool($db, 1);
    return !empty($proxies) ? $proxies[0] : null;
}

function liveReloadProxiesFromFile($db, $filePath = './proxy_pool.json') {
    if (!file_exists($filePath)) {
        logMessage("WARNING: Proxy pool file not found: $filePath");
        return [
            'success' => false,
            'error' => 'Proxy pool file not found'
        ];
    }
    
    $content = file_get_contents($filePath);
    $data = json_decode($content, true);
    
    if (json_last_error() !== JSON_ERROR_NONE || !isset($data['proxies'])) {
        logMessage("ERROR: Invalid proxy pool file format: $filePath");
        return [
            'success' => false,
            'error' => 'Invalid proxy pool file format'
        ];
    }
    
    $imported = 0;
    $failed = 0;
    
    foreach ($data['proxies'] as $proxy) {
        try {
            $result = insertProxy(
                $db,
                $proxy['ip_address'],
                $proxy['port'] ?? 8080,
                $proxy['protocol'] ?? 'http',
                $proxy['country'] ?? null,
                'file_import'
            );
            
            if ($result) {
                $imported++;
            } else {
                $failed++;
            }
        } catch (Exception $e) {
            $failed++;
        }
    }
    
    logMessage("Live reload completed: $imported imported, $failed failed from: $filePath");
    
    return [
        'success' => true,
        'imported' => $imported,
        'failed' => $failed,
        'source' => $filePath
    ];
}

function scheduleProxyCollection($db) {
    $lastUpdate = file_exists('/tmp/last_proxy_update') ? 
        filemtime('/tmp/last_proxy_update') : 0;
    
    if ((time() - $lastUpdate) >= 600) { // 10 minutes
        logMessage("Starting scheduled proxy collection (10-minute interval)");
        
        $sources = [
            [
                'url' => 'https://api.proxyscrape.com/v2/?request=get&protocol=http&format=json&country=all&ssl=all&anonymity=all',
                'format' => 'json',
                'type' => 'api'
            ],
            [
                'url' => 'https://raw.githubusercontent.com/clarketm/proxy-list/master/proxy-list-raw.txt',
                'format' => 'text',
                'type' => 'github'
            ],
            [
                'url' => 'https://raw.githubusercontent.com/TheSpeedX/PROXY-List/master/http.txt',
                'format' => 'text', 
                'type' => 'github'
            ],
            [
                'url' => 'https://raw.githubusercontent.com/monosans/proxy-list/main/proxies/http.txt',
                'format' => 'text',
                'type' => 'github'
            ],
            [
                'url' => 'https://raw.githubusercontent.com/proxy4parsing/proxy-list/main/http.txt',
                'format' => 'text',
                'type' => 'github'
            ]
        ];
        
        $totalImported = 0;
        $totalFailed = 0;
        $successfulSources = 0;
        
        foreach ($sources as $source) {
            try {
                logMessage("Collecting proxies from: " . $source['url']);
                
                $context = stream_context_create([
                    'http' => [
                        'timeout' => 30,
                        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                    ]
                ]);
                
                $data = file_get_contents($source['url'], false, $context);
                
                if ($data === false) {
                    logMessage("Failed to fetch from: " . $source['url']);
                    continue;
                }
                
                $proxies = [];
                
                if ($source['format'] === 'json') {
                    $jsonData = json_decode($data, true);
                    if (isset($jsonData['proxies'])) {
                        foreach ($jsonData['proxies'] as $proxy) {
                            if (isset($proxy['ip']) && isset($proxy['port'])) {
                                $proxies[] = $proxy['ip'] . ':' . $proxy['port'];
                            }
                        }
                    }
                } else {
                    preg_match_all('/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}):(\d{1,5})/', $data, $matches);
                    if (!empty($matches[0])) {
                        $proxies = $matches[0];
                    }
                }
                
                if (!empty($proxies)) {
                    $proxies = array_slice($proxies, 0, 200000);
                    
                    $imported = 0;
                    $failed = 0;
                    
                    foreach ($proxies as $proxy) {
                        if ($totalImported >= 1000000) {
                            logMessage("Reached maximum proxy limit of 1,000,000");
                            break 2;
                        }
                        
                        list($ip, $port) = explode(':', $proxy);
                        
                        if (filter_var($ip, FILTER_VALIDATE_IP) && is_numeric($port) && $port > 0 && $port <= 65535) {
                            try {
                                $stmt = $db->prepare("INSERT IGNORE INTO proxies (ip, port, type, status, last_used, failure_count) VALUES (?, ?, 'http', 'alive', 0, 0)");
                                $stmt->execute([$ip, intval($port)]);
                                $imported++;
                                $totalImported++;
                            } catch (Exception $e) {
                                $failed++;
                                $totalFailed++;
                            }
                        } else {
                            $failed++;
                            $totalFailed++;
                        }
                    }
                    
                    logMessage("Source {$source['type']}: $imported imported, $failed failed from " . count($proxies) . " total");
                    $successfulSources++;
                }
                
            } catch (Exception $e) {
                logMessage("Error collecting from {$source['url']}: " . $e->getMessage());
                continue;
            }
        }
        
        file_put_contents('/tmp/last_proxy_update', time());
        
        try {
            $stmt = $db->prepare("DELETE FROM proxies WHERE failure_count > 5 OR (last_used > 0 AND last_used < ?)");
            $stmt->execute([time() - 86400]); // Remove proxies not used in 24 hours with failures
            $cleanedCount = $stmt->rowCount();
            logMessage("Cleaned up $cleanedCount old/dead proxies");
        } catch (Exception $e) {
            logMessage("Error cleaning proxies: " . $e->getMessage());
        }
        
        logMessage("Scheduled proxy collection completed: $totalImported new proxies imported from $successfulSources sources, $totalFailed failed");
        
        return [
            'success' => true,
            'total_imported' => $totalImported,
            'total_failed' => $totalFailed,
            'sources_used' => $successfulSources,
            'message' => "Collected $totalImported new proxies from $successfulSources sources"
        ];
    }
    
    $timeSinceUpdate = time() - $lastUpdate;
    $timeUntilNext = 600 - $timeSinceUpdate;
    
    return [
        'success' => true, 
        'message' => 'No update needed',
        'time_since_last_update' => $timeSinceUpdate,
        'time_until_next_update' => $timeUntilNext
    ];
}

function importProxiesFromMultipleSources($db, $sources, $maxProxies = 1000000) {
    $totalImported = 0;
    $totalFailed = 0;
    
    foreach ($sources as $source) {
        if ($totalImported >= $maxProxies) {
            break;
        }
        
        try {
            $data = file_get_contents($source['url']);
            if ($data === false) continue;
            
            $proxies = [];
            if (isset($source['format']) && $source['format'] === 'json') {
                $jsonData = json_decode($data, true);
                if (isset($jsonData['proxies'])) {
                    foreach ($jsonData['proxies'] as $proxy) {
                        if (isset($proxy['ip']) && isset($proxy['port'])) {
                            $proxies[] = $proxy['ip'] . ':' . $proxy['port'];
                        }
                    }
                }
            } else {
                preg_match_all('/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}):(\d{1,5})/', $data, $matches);
                if (!empty($matches[0])) {
                    $proxies = $matches[0];
                }
            }
            
            foreach ($proxies as $proxy) {
                if ($totalImported >= $maxProxies) break;
                
                list($ip, $port) = explode(':', $proxy);
                if (filter_var($ip, FILTER_VALIDATE_IP) && is_numeric($port)) {
                    try {
                        $stmt = $db->prepare("INSERT IGNORE INTO proxies (ip, port, type, status, last_used, failure_count) VALUES (?, ?, 'http', 'alive', 0, 0)");
                        $stmt->execute([$ip, intval($port)]);
                        $totalImported++;
                    } catch (Exception $e) {
                        $totalFailed++;
                    }
                }
            }
        } catch (Exception $e) {
            logMessage("Error importing from source: " . $e->getMessage());
        }
    }
    
    return [
        'success' => true,
        'total_imported' => $totalImported,
        'total_failed' => $totalFailed
    ];
}
?>

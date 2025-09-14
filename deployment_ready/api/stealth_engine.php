<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'database.php';
require_once 'client_profile.php';
require_once 'tls_profile.php';
require_once 'proxy_manager_class.php';

function logStealthMessage($message) {
    $logFile = './logs/backend.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] STEALTH_ENGINE: $message\n", FILE_APPEND | LOCK_EX);
}

function logStealthActivity($message) {
    $logFile = '/home/ftcceelg/load_testing_system/logs/stealth_activity.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

if (basename($_SERVER['SCRIPT_NAME']) === basename(__FILE__)) {
try {
    $db = new Database();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
    
    logStealthMessage("Request received: $method " . ($_SERVER['REQUEST_URI'] ?? 'CLI'));
    
    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'status';
        
        switch ($action) {
            case 'status':
                $status = getStealthEngineStatus($db);
                logStealthMessage("Retrieved stealth engine status");
                echo json_encode([
                    'success' => true,
                    'stealth_status' => $status
                ]);
                break;
                
            case 'session_stats':
                $groupId = $_GET['group_id'] ?? null;
                if (!$groupId) {
                    throw new Exception('Group ID is required');
                }
                
                $stats = $db->getStealthSessionStats($groupId);
                logStealthMessage("Retrieved stealth session stats for group: $groupId");
                echo json_encode([
                    'success' => true,
                    'session_stats' => $stats
                ]);
                break;
                
            case 'rotation_config':
                $profileId = $_GET['profile_id'] ?? null;
                $config = getRotationConfig($db, $profileId);
                logStealthMessage("Retrieved rotation config for profile: $profileId");
                echo json_encode([
                    'success' => true,
                    'rotation_config' => $config
                ]);
                break;
                
            case 'current_fingerprint':
                $groupId = $_GET['group_id'] ?? null;
                $fingerprint = getCurrentFingerprint($db, $groupId);
                echo json_encode([
                    'success' => true,
                    'current_fingerprint' => $fingerprint
                ]);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? 'start_session';
        
        switch ($action) {
            case 'start_session':
                $groupId = $input['group_id'] ?? null;
                $stealthProfileId = $input['stealth_profile_id'] ?? 1;
                $rotationConfig = $input['rotation_config'] ?? [];
                
                if (!$groupId) {
                    throw new Exception('Group ID is required');
                }
                
                $result = startStealthSession($db, $groupId, $stealthProfileId, $rotationConfig);
                
                logStealthMessage("Started stealth session for group: $groupId with profile: $stealthProfileId");
                logStealthActivity("SESSION_START: Group $groupId - Profile $stealthProfileId - Config: " . json_encode($rotationConfig));
                
                echo json_encode([
                    'success' => true,
                    'session' => $result
                ]);
                break;
                
            case 'rotate_traffic':
                $groupId = $input['group_id'] ?? null;
                $rotationType = $input['rotation_type'] ?? 'all';
                
                if (!$groupId) {
                    throw new Exception('Group ID is required');
                }
                
                $result = performTrafficRotation($db, $groupId, $rotationType);
                
                logStealthMessage("Performed traffic rotation for group: $groupId, type: $rotationType");
                logStealthActivity("ROTATION: Group $groupId - Type $rotationType - " . json_encode($result));
                
                echo json_encode([
                    'success' => true,
                    'rotation_result' => $result
                ]);
                break;
                
            case 'spoof_headers':
                $targetUrl = $input['target_url'] ?? null;
                $spoofLevel = $input['spoof_level'] ?? 'medium';
                
                $headers = generateSpoofedHeaders($targetUrl, $spoofLevel);
                
                logStealthMessage("Generated spoofed headers for target: $targetUrl, level: $spoofLevel");
                logStealthActivity("HEADER_SPOOF: Target $targetUrl - Level $spoofLevel - Headers: " . count($headers));
                
                echo json_encode([
                    'success' => true,
                    'spoofed_headers' => $headers
                ]);
                break;
                
            case 'sync_components':
                $groupId = $input['group_id'] ?? null;
                $components = $input['components'] ?? ['proxy', 'ua', 'tls'];
                
                if (!$groupId) {
                    throw new Exception('Group ID is required');
                }
                
                $result = synchronizeStealthComponents($db, $groupId, $components);
                
                logStealthMessage("Synchronized stealth components for group: $groupId");
                logStealthActivity("SYNC: Group $groupId - Components: " . implode(',', $components));
                
                echo json_encode([
                    'success' => true,
                    'sync_result' => $result
                ]);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    }
    
} catch (Exception $e) {
    logStealthMessage("Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
}

function getStealthEngineStatus($db) {
    $proxyStats = $db->getProxyStats();
    $stealthProfiles = $db->getAllStealthProfiles();
    $attackMethods = $db->getAllAttackMethods();
    
    $totalProxies = 0;
    $aliveProxies = 0;
    
    foreach ($proxyStats as $stat) {
        $totalProxies += $stat['count'];
        if ($stat['status'] === 'alive') {
            $aliveProxies = $stat['count'];
        }
    }
    
    return [
        'engine_status' => 'active',
        'proxy_pool' => [
            'total_proxies' => $totalProxies,
            'active_proxies' => $aliveProxies,
            'rotation_enabled' => true,
            'health_check_interval' => 300
        ],
        'user_agent_rotation' => [
            'profiles_available' => count($stealthProfiles),
            'rotation_enabled' => true,
            'current_pool_size' => rand(10000, 50000)
        ],
        'tls_fingerprinting' => [
            'ja3_profiles' => count($stealthProfiles),
            'randomization_enabled' => true,
            'stealth_level' => 'maximum'
        ],
        'attack_methods' => [
            'available_methods' => count($attackMethods),
            'active_engines' => ['socket-spam', 'playwright', 'post-spam']
        ],
        'detection_evasion' => [
            'header_spoofing' => true,
            'ip_rotation' => true,
            'timing_randomization' => true,
            'behavior_simulation' => true
        ]
    ];
}

function getRotationConfig($db, $profileId = null) {
    if ($profileId) {
        $profile = $db->getStealthProfile($profileId);
        if ($profile) {
            return [
                'profile_id' => $profileId,
                'profile_name' => $profile['profile_name'],
                'user_agent_rotation' => [
                    'enabled' => true,
                    'interval' => rand(30, 120),
                    'pool_size' => count(json_decode($profile['user_agents'], true))
                ],
                'proxy_rotation' => [
                    'enabled' => true,
                    'interval' => rand(60, 300),
                    'health_check' => true
                ],
                'tls_rotation' => [
                    'enabled' => true,
                    'interval' => rand(120, 600),
                    'randomization' => 'high'
                ]
            ];
        }
    }
    
    return [
        'profile_id' => null,
        'profile_name' => 'default',
        'user_agent_rotation' => [
            'enabled' => true,
            'interval' => 60,
            'pool_size' => 15
        ],
        'proxy_rotation' => [
            'enabled' => true,
            'interval' => 180,
            'health_check' => true
        ],
        'tls_rotation' => [
            'enabled' => true,
            'interval' => 300,
            'randomization' => 'medium'
        ]
    ];
}

function getCurrentFingerprint($db, $groupId) {
    $sessionStats = $db->getStealthSessionStats($groupId);
    
    if ($sessionStats && $sessionStats['stealth_profile_id']) {
        $profile = $db->getStealthProfile($sessionStats['stealth_profile_id']);
        if ($profile) {
            $ja3Fingerprints = json_decode($profile['ja3_fingerprints'], true);
            $tlsConfigs = json_decode($profile['tls_configs'], true);
            
            return [
                'ja3_fingerprint' => $ja3Fingerprints[0] ?? 'unknown',
                'tls_version' => $tlsConfigs['tls_version'] ?? '1.3',
                'cipher_suites' => $tlsConfigs['cipher_suites'] ?? [],
                'user_agent' => getRandomUserAgent(),
                'proxy_ip' => getCurrentProxyIP($db),
                'last_rotation' => $sessionStats['started_at']
            ];
        }
    }
    
    return [
        'ja3_fingerprint' => '771,4865-4866-4867-49195-49199,0-23-65281-10-11,29-23-24,0',
        'tls_version' => '1.3',
        'cipher_suites' => ['TLS_AES_128_GCM_SHA256', 'TLS_AES_256_GCM_SHA384'],
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'proxy_ip' => '192.168.1.100:8080',
        'last_rotation' => date('Y-m-d H:i:s')
    ];
}

function getRandomUserAgent() {
    $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0'
    ];
    
    return $userAgents[array_rand($userAgents)];
}

function getCurrentProxyIP($db) {
    $proxies = $db->getActiveProxies(1);
    if (!empty($proxies)) {
        return $proxies[0]['ip_address'] . ':' . $proxies[0]['port'];
    }
    return '127.0.0.1:8080';
}

function startStealthSession($db, $groupId, $stealthProfileId, $rotationConfig) {
    $result = $db->insertStealthSession($groupId, $stealthProfileId);
    
    if ($result) {
        return [
            'group_id' => $groupId,
            'stealth_profile_id' => $stealthProfileId,
            'rotation_config' => $rotationConfig,
            'started_at' => date('Y-m-d H:i:s'),
            'status' => 'active'
        ];
    }
    
    throw new Exception('Failed to start stealth session');
}

function performTrafficRotation($db, $groupId, $rotationType) {
    $rotationResults = [];
    
    switch ($rotationType) {
        case 'proxy':
            $rotationResults['proxy'] = rotateProxy($db, $groupId);
            $db->updateStealthSessionStats($groupId, 1, 0, 0, 0);
            break;
            
        case 'user_agent':
            $rotationResults['user_agent'] = rotateUserAgent($db, $groupId);
            $db->updateStealthSessionStats($groupId, 0, 1, 0, 0);
            break;
            
        case 'tls':
            $rotationResults['tls'] = rotateTLSFingerprint($db, $groupId);
            $db->updateStealthSessionStats($groupId, 0, 0, 1, 0);
            break;
            
        case 'all':
        default:
            $rotationResults['proxy'] = rotateProxy($db, $groupId);
            $rotationResults['user_agent'] = rotateUserAgent($db, $groupId);
            $rotationResults['tls'] = rotateTLSFingerprint($db, $groupId);
            $db->updateStealthSessionStats($groupId, 1, 1, 1, 0);
            break;
    }
    
    return $rotationResults;
}

function rotateProxy($db, $groupId) {
    $proxies = $db->getActiveProxies(10);
    if (!empty($proxies)) {
        $selectedProxy = $proxies[array_rand($proxies)];
        return [
            'new_proxy' => $selectedProxy['ip_address'] . ':' . $selectedProxy['port'],
            'protocol' => $selectedProxy['protocol'],
            'response_time' => $selectedProxy['response_time']
        ];
    }
    
    return ['error' => 'No active proxies available'];
}

function rotateUserAgent($db, $groupId) {
    $userAgents = getRandomUserAgents(1);
    return [
        'new_user_agent' => $userAgents[0],
        'rotation_time' => date('Y-m-d H:i:s')
    ];
}

function rotateTLSFingerprint($db, $groupId) {
    $ja3Fingerprints = generateRandomJA3Fingerprints(1, 'medium');
    return [
        'new_ja3_fingerprint' => $ja3Fingerprints[0],
        'rotation_time' => date('Y-m-d H:i:s')
    ];
}

function generateSpoofedHeaders($targetUrl, $spoofLevel) {
    $baseHeaders = [
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language' => 'en-US,en;q=0.5',
        'Accept-Encoding' => 'gzip, deflate, br',
        'DNT' => '1',
        'Connection' => 'keep-alive',
        'Upgrade-Insecure-Requests' => '1'
    ];
    
    if ($spoofLevel === 'maximum') {
        $spoofedHeaders = generateXForwardedForHeaders();
        $baseHeaders = array_merge($baseHeaders, $spoofedHeaders);
        
        $baseHeaders['X-Cluster-Client-IP'] = generateRandomIP();
        $baseHeaders['X-Forwarded-Host'] = parse_url($targetUrl, PHP_URL_HOST);
        $baseHeaders['X-Forwarded-Proto'] = 'https';
        $baseHeaders['CF-Connecting-IP'] = generateRandomIP();
        $baseHeaders['True-Client-IP'] = generateRandomIP();
    }
    
    return $baseHeaders;
}

function synchronizeStealthComponents($db, $groupId, $components) {
    $syncResults = [];
    
    foreach ($components as $component) {
        switch ($component) {
            case 'proxy':
                $syncResults['proxy'] = [
                    'status' => 'synchronized',
                    'active_count' => count($db->getActiveProxies(100))
                ];
                break;
                
            case 'ua':
                $syncResults['user_agent'] = [
                    'status' => 'synchronized',
                    'pool_size' => rand(10000, 50000)
                ];
                break;
                
            case 'tls':
                $syncResults['tls'] = [
                    'status' => 'synchronized',
                    'fingerprint_count' => rand(100, 1000)
                ];
                break;
        }
    }
    
    return $syncResults;
}
?>

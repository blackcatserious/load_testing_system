<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'database.php';

function logClientProfileMessage($message) {
    $logFile = __DIR__ . '/../logs/backend.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] CLIENT_PROFILE: $message\n", FILE_APPEND | LOCK_EX);
}

if (basename($_SERVER['SCRIPT_NAME']) === basename(__FILE__)) {
try {
    $db = new Database();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
    
    logClientProfileMessage("Request received: $method " . ($_SERVER['REQUEST_URI'] ?? 'CLI'));
    
    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'list';
        
        switch ($action) {
            case 'list':
                $profiles = $db->getAllStealthProfiles();
                logClientProfileMessage("Retrieved " . count($profiles) . " stealth profiles");
                echo json_encode([
                    'success' => true,
                    'profiles' => $profiles
                ]);
                break;
                
            case 'get':
                $profileId = $_GET['profile_id'] ?? null;
                if (!$profileId) {
                    throw new Exception('Profile ID is required');
                }
                
                $profile = $db->getStealthProfile($profileId);
                if (!$profile) {
                    throw new Exception('Profile not found');
                }
                
                $profile['user_agents'] = json_decode($profile['user_agents'], true);
                $profile['ja3_fingerprints'] = json_decode($profile['ja3_fingerprints'], true);
                $profile['tls_configs'] = json_decode($profile['tls_configs'], true);
                
                logClientProfileMessage("Retrieved stealth profile: " . $profile['profile_name']);
                echo json_encode([
                    'success' => true,
                    'profile' => $profile
                ]);
                break;
                
            case 'random_ua':
                $count = $_GET['count'] ?? 1;
                $userAgents = getRandomUserAgents($count);
                logClientProfileMessage("Generated $count random User-Agents");
                echo json_encode([
                    'success' => true,
                    'user_agents' => $userAgents
                ]);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? 'create';
        
        switch ($action) {
            case 'create':
                $profileName = $input['profile_name'] ?? null;
                $userAgents = $input['user_agents'] ?? [];
                $ja3Fingerprints = $input['ja3_fingerprints'] ?? [];
                $tlsConfigs = $input['tls_configs'] ?? [];
                
                if (!$profileName) {
                    throw new Exception('Profile name is required');
                }
                
                if (empty($userAgents)) {
                    $userAgents = getDefaultUserAgents();
                }
                
                if (empty($ja3Fingerprints)) {
                    $ja3Fingerprints = getDefaultJA3Fingerprints();
                }
                
                if (empty($tlsConfigs)) {
                    $tlsConfigs = getDefaultTLSConfigs();
                }
                
                $result = $db->insertStealthProfile($profileName, $userAgents, $ja3Fingerprints, $tlsConfigs);
                
                if ($result) {
                    logClientProfileMessage("Created stealth profile: $profileName with " . count($userAgents) . " User-Agents");
                    echo json_encode([
                        'success' => true,
                        'message' => 'Stealth profile created successfully',
                        'profile_name' => $profileName
                    ]);
                } else {
                    throw new Exception('Failed to create stealth profile');
                }
                break;
                
            case 'import_ua_file':
                $filePath = $input['file_path'] ?? null;
                if (!$filePath || !file_exists($filePath)) {
                    throw new Exception('User-Agent file not found');
                }
                
                $userAgents = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $userAgents = array_filter($userAgents, function($ua) {
                    return !empty(trim($ua)) && strpos($ua, 'Mozilla') !== false;
                });
                
                logClientProfileMessage("Imported " . count($userAgents) . " User-Agents from file: $filePath");
                echo json_encode([
                    'success' => true,
                    'user_agents' => array_values($userAgents),
                    'count' => count($userAgents)
                ]);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    }
    
} catch (Exception $e) {
    logClientProfileMessage("Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
}

function getRandomUserAgents($count = 1) {
    $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36 Edg/119.0.0.0',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1',
        'Mozilla/5.0 (iPad; CPU OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1',
        'Mozilla/5.0 (Android 14; Mobile; rv:121.0) Gecko/121.0 Firefox/121.0',
        'Mozilla/5.0 (Linux; Android 14; SM-G998B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36'
    ];
    
    $selected = [];
    for ($i = 0; $i < $count; $i++) {
        $selected[] = $userAgents[array_rand($userAgents)];
    }
    
    return $selected;
}

function getDefaultUserAgents() {
    return [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36 Edg/119.0.0.0',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1',
        'Mozilla/5.0 (iPad; CPU OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1',
        'Mozilla/5.0 (Android 14; Mobile; rv:121.0) Gecko/121.0 Firefox/121.0',
        'Mozilla/5.0 (Linux; Android 14; SM-G998B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/118.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 11.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
        'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:121.0) Gecko/20100101 Firefox/121.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36'
    ];
}

function getDefaultJA3Fingerprints() {
    return [
        '769,47-53-5-10-49171-49172-49161-49162-49-50-156-157-47-53,0-23-65281-10-11-35-16-5-13-18-51-45-43-27-21,29-23-24-25,0',
        '771,4865-4866-4867-49195-49199-49196-49200-52393-52392-49171-49172-49161-49162-49-50-156-157-47-53,0-23-65281-10-11-35-16-5-13-18-51-45-43-27-21,29-23-24-25,0',
        '772,4865-4866-4867-49195-49199-49196-49200-52393-52392-49171-49172-49161-49162-49-50-156-157-47-53,0-23-65281-10-11-35-16-5-13-18-51-45-43-27-21,29-23-24-25,0'
    ];
}

function getDefaultTLSConfigs() {
    return [
        'tls_version' => '1.3',
        'cipher_suites' => [
            'TLS_AES_128_GCM_SHA256',
            'TLS_AES_256_GCM_SHA384',
            'TLS_CHACHA20_POLY1305_SHA256'
        ],
        'extensions' => [
            'server_name',
            'supported_groups',
            'signature_algorithms',
            'application_layer_protocol_negotiation'
        ]
    ];
}
?>

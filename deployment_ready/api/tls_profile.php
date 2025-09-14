<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'database.php';

function logTLSProfileMessage($message) {
    $logFile = __DIR__ . '/../logs/backend.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] TLS_PROFILE: $message\n", FILE_APPEND | LOCK_EX);
}

if (basename($_SERVER['SCRIPT_NAME']) === basename(__FILE__)) {
try {
    $db = new Database();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
    
    logMessage("Request received: $method " . ($_SERVER['REQUEST_URI'] ?? 'CLI'));
    
    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'list';
        
        switch ($action) {
            case 'list':
                $profiles = getAllTLSProfiles();
                logMessage("Retrieved " . count($profiles) . " TLS profiles");
                echo json_encode([
                    'success' => true,
                    'profiles' => $profiles
                ]);
                break;
                
            case 'get':
                $profileName = $_GET['profile_name'] ?? null;
                if (!$profileName) {
                    throw new Exception('Profile name is required');
                }
                
                $profile = getTLSProfile($profileName);
                if (!$profile) {
                    throw new Exception('TLS profile not found');
                }
                
                logMessage("Retrieved TLS profile: $profileName");
                echo json_encode([
                    'success' => true,
                    'profile' => $profile
                ]);
                break;
                
            case 'generate_ja3':
                $count = $_GET['count'] ?? 1;
                $stealthLevel = $_GET['stealth_level'] ?? 'medium';
                $ja3Fingerprints = generateRandomJA3Fingerprints($count, $stealthLevel);
                logMessage("Generated $count JA3 fingerprints with stealth level: $stealthLevel");
                echo json_encode([
                    'success' => true,
                    'ja3_fingerprints' => $ja3Fingerprints
                ]);
                break;
                
            case 'cipher_suites':
                $tlsVersion = $_GET['tls_version'] ?? '1.3';
                $cipherSuites = getCipherSuites($tlsVersion);
                logMessage("Retrieved cipher suites for TLS $tlsVersion");
                echo json_encode([
                    'success' => true,
                    'cipher_suites' => $cipherSuites
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
                $tlsVersion = $input['tls_version'] ?? '1.3';
                $cipherSuites = $input['cipher_suites'] ?? [];
                $extensions = $input['extensions'] ?? [];
                $stealthLevel = $input['stealth_level'] ?? 'medium';
                
                if (!$profileName) {
                    throw new Exception('Profile name is required');
                }
                
                $tlsConfig = createTLSConfig($tlsVersion, $cipherSuites, $extensions, $stealthLevel);
                $ja3Fingerprint = generateJA3FromConfig($tlsConfig);
                
                $result = $db->insertStealthProfile(
                    $profileName,
                    [],
                    [$ja3Fingerprint],
                    $tlsConfig
                );
                
                if ($result) {
                    logMessage("Created TLS profile: $profileName with stealth level: $stealthLevel");
                    echo json_encode([
                        'success' => true,
                        'message' => 'TLS profile created successfully',
                        'profile_name' => $profileName,
                        'ja3_fingerprint' => $ja3Fingerprint
                    ]);
                } else {
                    throw new Exception('Failed to create TLS profile');
                }
                break;
                
            case 'customize':
                $tlsVersion = $input['tls_version'] ?? '1.3';
                $customCiphers = $input['custom_ciphers'] ?? [];
                $customExtensions = $input['custom_extensions'] ?? [];
                $stealthLevel = $input['stealth_level'] ?? 'maximum';
                
                $customConfig = [
                    'tls_version' => $tlsVersion,
                    'cipher_suites' => $customCiphers,
                    'extensions' => $customExtensions,
                    'stealth_level' => $stealthLevel,
                    'custom' => true
                ];
                
                $ja3Fingerprint = generateJA3FromConfig($customConfig);
                
                logMessage("Generated custom TLS config with stealth level: $stealthLevel");
                echo json_encode([
                    'success' => true,
                    'tls_config' => $customConfig,
                    'ja3_fingerprint' => $ja3Fingerprint
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
}

function getAllTLSProfiles() {
    return [
        [
            'name' => 'chrome-modern',
            'description' => 'Modern Chrome TLS profile',
            'tls_version' => '1.3',
            'cipher_suites' => [
                'TLS_AES_128_GCM_SHA256',
                'TLS_AES_256_GCM_SHA384',
                'TLS_CHACHA20_POLY1305_SHA256',
                'TLS_ECDHE_ECDSA_WITH_AES_128_GCM_SHA256',
                'TLS_ECDHE_RSA_WITH_AES_128_GCM_SHA256'
            ],
            'extensions' => [
                'server_name',
                'extended_master_secret',
                'renegotiation_info',
                'supported_groups',
                'ec_point_formats',
                'signature_algorithms',
                'application_layer_protocol_negotiation',
                'status_request',
                'key_share',
                'supported_versions'
            ],
            'ja3_fingerprint' => '771,4865-4866-4867-49195-49199-49196-49200-52393-52392-49171-49172-49161-49162-49-50-156-157-47-53,0-23-65281-10-11-35-16-5-13-18-51-45-43-27-21,29-23-24-25,0',
            'compatibility' => 'high',
            'security_level' => 'high',
            'performance' => 'high',
            'stealth_level' => 'medium'
        ],
        [
            'name' => 'firefox-modern',
            'description' => 'Modern Firefox TLS profile',
            'tls_version' => '1.3',
            'cipher_suites' => [
                'TLS_AES_128_GCM_SHA256',
                'TLS_CHACHA20_POLY1305_SHA256',
                'TLS_AES_256_GCM_SHA384',
                'TLS_ECDHE_ECDSA_WITH_AES_128_GCM_SHA256',
                'TLS_ECDHE_RSA_WITH_AES_128_GCM_SHA256'
            ],
            'extensions' => [
                'server_name',
                'extended_master_secret',
                'renegotiation_info',
                'supported_groups',
                'ec_point_formats',
                'signature_algorithms',
                'application_layer_protocol_negotiation',
                'status_request',
                'key_share',
                'supported_versions'
            ],
            'ja3_fingerprint' => '772,4865-4866-4867-49195-49199-49196-49200-52393-52392-49171-49172-49161-49162-49-50-156-157-47-53,0-23-65281-10-11-35-16-5-13-18-51-45-43-27-21,29-23-24-25,0',
            'compatibility' => 'high',
            'security_level' => 'high',
            'performance' => 'high',
            'stealth_level' => 'medium'
        ],
        [
            'name' => 'safari-modern',
            'description' => 'Modern Safari TLS profile',
            'tls_version' => '1.3',
            'cipher_suites' => [
                'TLS_AES_128_GCM_SHA256',
                'TLS_AES_256_GCM_SHA384',
                'TLS_CHACHA20_POLY1305_SHA256',
                'TLS_ECDHE_ECDSA_WITH_AES_256_GCM_SHA384',
                'TLS_ECDHE_ECDSA_WITH_AES_128_GCM_SHA256'
            ],
            'extensions' => [
                'server_name',
                'extended_master_secret',
                'renegotiation_info',
                'supported_groups',
                'ec_point_formats',
                'signature_algorithms',
                'application_layer_protocol_negotiation',
                'status_request',
                'key_share',
                'supported_versions'
            ],
            'ja3_fingerprint' => '773,4865-4866-4867-49196-49200-49195-49199-52393-52392-49162-49172-49161-49171-50-49-157-156-53-47,0-23-65281-10-11-35-16-5-13-18-51-45-43-27-21,29-23-24-25,0',
            'compatibility' => 'high',
            'security_level' => 'high',
            'performance' => 'high',
            'stealth_level' => 'medium'
        ],
        [
            'name' => 'stealth-maximum',
            'description' => 'Maximum stealth TLS profile with randomized fingerprints',
            'tls_version' => '1.3',
            'cipher_suites' => 'randomized',
            'extensions' => 'randomized',
            'ja3_fingerprint' => 'randomized',
            'compatibility' => 'variable',
            'security_level' => 'variable',
            'performance' => 'medium',
            'stealth_level' => 'maximum'
        ]
    ];
}

function getTLSProfile($profileName) {
    $profiles = getAllTLSProfiles();
    foreach ($profiles as $profile) {
        if ($profile['name'] === $profileName) {
            return $profile;
        }
    }
    return null;
}

function generateRandomJA3Fingerprints($count, $stealthLevel) {
    $fingerprints = [];
    
    for ($i = 0; $i < $count; $i++) {
        $tlsVersion = rand(771, 773);
        $cipherSuites = generateRandomCipherSuites($stealthLevel);
        $extensions = generateRandomExtensions($stealthLevel);
        $supportedGroups = '29-23-24-25';
        $ecPointFormats = '0';
        
        $ja3 = "$tlsVersion,$cipherSuites,$extensions,$supportedGroups,$ecPointFormats";
        $fingerprints[] = $ja3;
    }
    
    return $fingerprints;
}

function generateRandomCipherSuites($stealthLevel) {
    $baseCiphers = [4865, 4866, 4867, 49195, 49199, 49196, 49200, 52393, 52392, 49171, 49172, 49161, 49162, 49, 50, 156, 157, 47, 53];
    
    if ($stealthLevel === 'maximum') {
        shuffle($baseCiphers);
        $count = rand(8, count($baseCiphers));
        $selectedCiphers = array_slice($baseCiphers, 0, $count);
    } else {
        $selectedCiphers = $baseCiphers;
    }
    
    return implode('-', $selectedCiphers);
}

function generateRandomExtensions($stealthLevel) {
    $baseExtensions = [0, 23, 65281, 10, 11, 35, 16, 5, 13, 18, 51, 45, 43, 27, 21];
    
    if ($stealthLevel === 'maximum') {
        shuffle($baseExtensions);
        $count = rand(10, count($baseExtensions));
        $selectedExtensions = array_slice($baseExtensions, 0, $count);
    } else {
        $selectedExtensions = $baseExtensions;
    }
    
    return implode('-', $selectedExtensions);
}

function getCipherSuites($tlsVersion) {
    if ($tlsVersion === '1.3') {
        return [
            'TLS_AES_128_GCM_SHA256',
            'TLS_AES_256_GCM_SHA384',
            'TLS_CHACHA20_POLY1305_SHA256'
        ];
    } elseif ($tlsVersion === '1.2') {
        return [
            'TLS_ECDHE_ECDSA_WITH_AES_128_GCM_SHA256',
            'TLS_ECDHE_RSA_WITH_AES_128_GCM_SHA256',
            'TLS_ECDHE_ECDSA_WITH_AES_256_GCM_SHA384',
            'TLS_ECDHE_RSA_WITH_AES_256_GCM_SHA384',
            'TLS_ECDHE_ECDSA_WITH_CHACHA20_POLY1305_SHA256',
            'TLS_ECDHE_RSA_WITH_CHACHA20_POLY1305_SHA256'
        ];
    }
    
    return [];
}

function createTLSConfig($tlsVersion, $cipherSuites, $extensions, $stealthLevel) {
    return [
        'tls_version' => $tlsVersion,
        'cipher_suites' => empty($cipherSuites) ? getCipherSuites($tlsVersion) : $cipherSuites,
        'extensions' => empty($extensions) ? getDefaultExtensions() : $extensions,
        'stealth_level' => $stealthLevel,
        'created_at' => date('Y-m-d H:i:s')
    ];
}

function getDefaultExtensions() {
    return [
        'server_name',
        'extended_master_secret',
        'renegotiation_info',
        'supported_groups',
        'ec_point_formats',
        'signature_algorithms',
        'application_layer_protocol_negotiation',
        'status_request',
        'key_share',
        'supported_versions'
    ];
}

function generateJA3FromConfig($tlsConfig) {
    $tlsVersion = ($tlsConfig['tls_version'] === '1.3') ? 771 : 770;
    
    $cipherMap = [
        'TLS_AES_128_GCM_SHA256' => 4865,
        'TLS_AES_256_GCM_SHA384' => 4866,
        'TLS_CHACHA20_POLY1305_SHA256' => 4867,
        'TLS_ECDHE_ECDSA_WITH_AES_128_GCM_SHA256' => 49195,
        'TLS_ECDHE_RSA_WITH_AES_128_GCM_SHA256' => 49199
    ];
    
    $cipherSuites = [];
    foreach ($tlsConfig['cipher_suites'] as $cipher) {
        if (isset($cipherMap[$cipher])) {
            $cipherSuites[] = $cipherMap[$cipher];
        }
    }
    
    $extensionMap = [
        'server_name' => 0,
        'extended_master_secret' => 23,
        'renegotiation_info' => 65281,
        'supported_groups' => 10,
        'ec_point_formats' => 11,
        'signature_algorithms' => 13,
        'application_layer_protocol_negotiation' => 16,
        'status_request' => 5,
        'key_share' => 51,
        'supported_versions' => 43
    ];
    
    $extensions = [];
    foreach ($tlsConfig['extensions'] as $ext) {
        if (isset($extensionMap[$ext])) {
            $extensions[] = $extensionMap[$ext];
        }
    }
    
    $cipherString = implode('-', $cipherSuites);
    $extensionString = implode('-', $extensions);
    
    return "$tlsVersion,$cipherString,$extensionString,29-23-24-25,0";
}
?>

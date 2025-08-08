<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $includeAntiDetect = $_GET['include_anti_detect'] ?? false;
    
    $metrics = [
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'metrics' => [
            'requests_per_second' => rand(50000, 100000),
            'active_threads' => rand(80000, 100000),
            'avg_latency_ms' => rand(50, 200),
            'error_rate_percent' => rand(0, 15),
            'total_requests' => rand(1000000, 5000000),
            'success_rate' => rand(85, 100),
            'active_proxies' => rand(50000, 100000),
            'targets_under_attack' => 9
        ],
        'status_codes' => [
            '200' => rand(60, 80),
            '403' => rand(5, 15),
            '503' => rand(5, 20),
            '524' => rand(0, 10),
            'timeout' => rand(0, 5)
        ],
        'attack_status' => [
            'mode' => 'MAXIMUM_DESTRUCTION',
            'unlimited_enabled' => true,
            'stealth_level' => 'maximum',
            'targets_degraded' => rand(6, 9),
            'infrastructure_attacks' => [
                'dns_flood' => 'active',
                'cdn_bypass' => 'active',
                'tls_exhaustion' => 'active'
            ]
        ]
    ];
    
    if ($includeAntiDetect) {
        $metrics['anti_detect'] = [
            'user_agents_rotated' => rand(1000, 5000),
            'tls_fingerprints_active' => rand(50, 200),
            'proxy_rotation_rate' => rand(100, 500),
            'stealth_score' => rand(85, 98)
        ];
    }
    
    echo json_encode($metrics);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>

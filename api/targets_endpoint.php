<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

echo json_encode([
    'success' => true,
    'message' => 'API is working',
    'targets' => [
        [
            'id' => 1,
            'label' => 'Test Target',
            'url' => 'https://example.com',
            'tags' => ['test'],
            'status' => 'idle',
            'success_rate' => '100%',
            'last_tested' => '2024-08-08 11:23:00',
            'attack_method' => 'auto-bypass',
            'engine' => 'playwright',
            'proxy_profile' => 'rotating',
            'stealth_profile' => 'high'
        ]
    ],
    'count' => 1
]);
?>

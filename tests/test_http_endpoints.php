<?php
$ch = curl_init('http://127.0.0.1:8088/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, __DIR__ . '/cookie.txt');
curl_setopt($ch, CURLOPT_COOKIEFILE, __DIR__ . '/cookie.txt');
$loginHtml = curl_exec($ch);

preg_match('/name="csrf_token" value="([^"]+)"/', $loginHtml, $m);
$token = $m[1] ?? '';

curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8088/login');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'csrf_token' => $token,
    'username' => 'superadmin',
    'password' => 'kaldis123',
]));
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_exec($ch);

// Explicitly request /dashboard on port 8088
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8088/dashboard');
curl_setopt($ch, CURLOPT_HTTPGET, true);
$dashHtml = curl_exec($ch);

echo "Dashboard status: " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . "\n";
echo "Has Executive Operations: " . (str_contains($dashHtml, 'Executive Operations') ? "YES" : "NO") . "\n";
echo "Has Monitored Groups Matrix: " . (str_contains($dashHtml, 'Monitored Groups Compliance Matrix') ? "YES" : "NO") . "\n";

curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8088/messages');
curl_setopt($ch, CURLOPT_HTTPGET, true);
$msgHtml = curl_exec($ch);

echo "Messages status: " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . "\n";
echo "Has Tracked Group Messages: " . (str_contains($msgHtml, 'Tracked Group Messages') ? "YES" : "NO") . "\n";
echo "Has Filter Toolbar: " . (str_contains($msgHtml, 'Search message text or #ref...') ? "YES" : "NO") . "\n";
echo "Pages are distinct: " . ($dashHtml !== $msgHtml ? "YES" : "NO") . "\n";

@unlink(__DIR__ . '/cookie.txt');

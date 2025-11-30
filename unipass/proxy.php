<?php
// CORS 헤더 설정
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/xml; charset=utf-8');

// OPTIONS 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// URL 파라미터 가져오기
$url = isset($_GET['url']) ? $_GET['url'] : '';

if (empty($url)) {
    // URL이 없으면 직접 파라미터로 API 호출
    $date = isset($_GET['date']) ? $_GET['date'] : date('Ymd');
    $weekFxrtTpcd = isset($_GET['weekFxrtTpcd']) ? $_GET['weekFxrtTpcd'] : '2';
    $serviceKey = isset($_GET['serviceKey']) ? $_GET['serviceKey'] : '';

    if (empty($serviceKey)) {
        http_response_code(400);
        die('Service key is required');
    }

    // API URL 구성
    $apiUrl = 'http://apis.data.go.kr/1220000/retrieveTrifFxrtInfo/getRetrieveTrifFxrtInfo';
    $queryParams = http_build_query([
        'serviceKey' => $serviceKey,
        'aplyBgnDt' => $date,
        'weekFxrtTpcd' => $weekFxrtTpcd
    ]);

    $url = $apiUrl . '?' . $queryParams;
}

// cURL로 GET 요청
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
curl_setopt($ch, CURLOPT_ENCODING, '');

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

curl_close($ch);

if ($error) {
    http_response_code(500);
    die('Proxy Error: ' . $error);
}

http_response_code($httpCode);
echo $response;
?>
<?php
// CORS 헤더 설정
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// OPTIONS 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 날짜 파라미터 가져오기
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$trdeYmd = str_replace('-', '', $date); // YYYYMMDD 형식

// 디버그 모드
$debug = isset($_GET['debug']) ? true : false;

// UNIPASS 주간환율 조회 URL (웹페이지 API)
$pageIndex = 1;
$pageUnit = 100;
$weekFxrtTpcd = '2'; // 2: 수입
$timestamp = round(microtime(true) * 1000);

// 실제 UNIPASS 웹사이트가 사용하는 AJAX URL
$url = "https://unipass.customs.go.kr/csp/myc/bsopspptinfo/dclrSpptInfo/WeekFxrtQryCtr/retrieveWeekFxrt.do";
$url .= "?pageIndex={$pageIndex}";
$url .= "&pageUnit={$pageUnit}";
$url .= "&aplyDt={$date}";
$url .= "&weekFxrtTpcd={$weekFxrtTpcd}";
$url .= "&undefined={$date}";
$url .= "&_={$timestamp}";

// cURL 초기화
$ch = curl_init();

// cURL 옵션 설정
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

// SSL 검증 비활성화 (호스팅 환경)
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

// FOLLOWLOCATION 설정 (open_basedir 제한 확인)
if (!ini_get('open_basedir')) {
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
}

// 헤더 설정 (AJAX 요청처럼 보이게)
$headers = array(
    'Accept: application/json, text/javascript, */*; q=0.01',
    'Accept-Language: ko-KR,ko;q=0.9,en-US;q=0.8,en;q=0.7',
    'Cache-Control: no-cache',
    'Pragma: no-cache',
    'Referer: https://unipass.customs.go.kr/csp/myc/bsopspptinfo/dclrSpptInfo/WeekFxrtQrytr/selectWeekFxrt.do',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
    'X-Requested-With: XMLHttpRequest'
);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// 쿠키 처리 (세션 유지)
$cookieFile = sys_get_temp_dir() . '/unipass_cookie_' . md5($date) . '.txt';
if (is_writable(sys_get_temp_dir())) {
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
}

// 인코딩 자동 처리
curl_setopt($ch, CURLOPT_ENCODING, '');

// 실행
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
$errno = curl_errno($ch);
$info = curl_getinfo($ch);

curl_close($ch);

// 쿠키 파일 정리
if (file_exists($cookieFile)) {
    @unlink($cookieFile);
}

// 디버그 정보
if ($debug) {
    echo json_encode(array(
        'debug' => true,
        'url' => $url,
        'httpCode' => $httpCode,
        'error' => $error,
        'errno' => $errno,
        'responseLength' => strlen($response),
        'responsePreview' => substr($response, 0, 500),
        'curlInfo' => $info
    ));
    exit;
}

// cURL 에러 처리
if ($error) {
    echo json_encode(array(
        'error' => true,
        'message' => 'Connection error: ' . $error,
        'errno' => $errno
    ));
    exit;
}

// HTTP 상태 확인
if ($httpCode !== 200) {
    echo json_encode(array(
        'error' => true,
        'message' => 'HTTP Error: ' . $httpCode,
        'responsePreview' => substr($response, 0, 200)
    ));
    exit;
}

// 응답 확인
if (empty($response)) {
    echo json_encode(array(
        'error' => true,
        'message' => 'Empty response from server'
    ));
    exit;
}

// JSON 파싱 시도
$jsonData = @json_decode($response, true);

if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
    // JSON 데이터 구조 확인 및 정리
    if (isset($jsonData['items']) && is_array($jsonData['items'])) {
        // items 배열이 있는 경우 (웹 파싱 형식)
        echo json_encode($jsonData);
    } else if (isset($jsonData['recordList']) && is_array($jsonData['recordList'])) {
        // recordList가 있는 경우 (API 형식)
        echo json_encode($jsonData);
    } else {
        // 다른 형식이면 그대로 반환
        echo json_encode($jsonData);
    }
} else {
    // JSON이 아닌 경우 HTML 응답일 가능성
    // HTML에서 JSON 데이터 추출 시도

    // JavaScript 변수에서 JSON 추출 패턴
    if (preg_match('/var\s+gridData\s*=\s*(\[.*?\]);/s', $response, $matches) ||
        preg_match('/JSON\.parse\(\'(.*?)\'\)/s', $response, $matches) ||
        preg_match('/"items"\s*:\s*(\[.*?\])/s', $response, $matches)) {

        $jsonStr = isset($matches[1]) ? $matches[1] : '';
        $extractedData = @json_decode($jsonStr, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            echo json_encode(array(
                'items' => $extractedData
            ));
            exit;
        }
    }

    // 추출 실패 시 에러 반환
    echo json_encode(array(
        'error' => true,
        'message' => 'Could not parse response as JSON',
        'responseLength' => strlen($response),
        'responsePreview' => substr($response, 0, 500)
    ));
}
?>
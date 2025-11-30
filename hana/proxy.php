<?php
// CORS 헤더 설정
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: text/html; charset=utf-8');

// OPTIONS 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 날짜 파라미터 가져오기 (YYYY-MM-DD 형식)
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// 페이지 파라미터 가져오기 (mall1501 or mall1502)
$page = isset($_GET['page']) ? $_GET['page'] : 'mall1501';

// 전주 월~금 계산 함수
function getLastWeekDates($baseDate) {
    $timestamp = strtotime($baseDate);
    $dayOfWeek = date('N', $timestamp); // 1 (월) ~ 7 (일)

    // 지난주 월요일 = 오늘 - (현재요일 + 6) 일
    $lastMonday = date('Y-m-d', strtotime("-" . ($dayOfWeek + 6) . " days", $timestamp));

    // 지난주 금요일 = 지난주 월요일 + 4일
    $lastFriday = date('Y-m-d', strtotime("+4 days", strtotime($lastMonday)));

    return array(
        'start' => $lastMonday,
        'end' => $lastFriday
    );
}

// 날짜 형식 변환
$dateStr = str_replace('-', '', $date); // 20251111
$year = substr($dateStr, 0, 4);         // 2025
$month = substr($dateStr, 4, 2);        // 11

// 페이지별로 다른 URL, pbldDvCd, 조회구분 사용
if ($page === 'mall1502') {
    // 대미환산율 - 기간평균으로 조회
    $url = 'https://www.kebhana.com/cms/rate/wpfxd651_06i_01.do';  // 대미환산율 페이지
    $pbldDvCd = '1';  // 최초
    $referer = 'https://www.kebhana.com/cms/rate/index.do?contentUrl=/cms/rate/wpfxd651_06i.do';

    // 전주 월~금 날짜 계산
    $weekDates = getLastWeekDates($date);
    $startDate = $weekDates['start'];
    $endDate = $weekDates['end'];
    $startDateStr = str_replace('-', '', $startDate);
    $endDateStr = str_replace('-', '', $endDate);

    // 대미환산율 기간평균 POST 데이터 구성
    $postData = array(
        'ajax' => 'true',
        'curCd' => '',
        'inqDvCd' => '4',  // 기간평균
        'tmpInqStrDt_p' => $startDate,  // 시작일 (YYYY-MM-DD)
        'tmpInqEndDt_p' => $endDate,    // 종료일 (YYYY-MM-DD)
        'tmpPbldDvCd' => $pbldDvCd,
        'hid_key_data' => '',
        'inqStrDt' => $startDateStr,  // 시작일 (YYYYMMDD)
        'inqEndDt' => $endDateStr,    // 종료일 (YYYYMMDD)
        'pbldDvCd' => $pbldDvCd,
        'hid_enc_data' => '',
        'requestTarget' => 'searchContentDiv'
    );
} else {
    // 현재환율 (mall1501) - 송금환율
    $url = 'https://www.kebhana.com/cms/rate/wpfxd651_01i_01.do';
    $pbldDvCd = '1';
    $referer = 'https://www.kebhana.com/cms/rate/index.do?contentUrl=/cms/rate/wpfxd651_01i.do';

    // 송금환율 POST 데이터 구성
    $postData = array(
        'ajax' => 'true',
        'curCd' => '',  // 빈 값
        'tmpInqStrDt' => $date,  // YYYY-MM-DD 형식
        'pbldDvCd' => $pbldDvCd,
        'pbldSqn' => '',  // 빈 값 (고시회차)
        'hid_key_data' => '',
        'inqStrDt' => $dateStr,  // YYYYMMDD 형식
        'inqKindCd' => '1',  // 조회 종류
        'hid_enc_data' => '',
        'requestTarget' => 'searchContentDiv'
    );
}

// cURL로 POST 요청
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
curl_setopt($ch, CURLOPT_REFERER, $referer);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
    'X-Requested-With: XMLHttpRequest',
    'X-Prototype-Version: 1.5.1.1',
    'Accept: text/javascript, text/html, application/xml, text/xml, */*'
));
curl_setopt($ch, CURLOPT_ENCODING, '');

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

curl_close($ch);

if ($error) {
    http_response_code(500);
    die('Proxy Error: ' . $error);
}

// 디버깅: 요청 정보를 HTML 주석으로 추가
$debugInfo = "\n<!-- Debug Info:\n";
$debugInfo .= "Page: " . $page . "\n";
$debugInfo .= "URL: " . $url . "\n";
$debugInfo .= "POST Data: " . print_r($postData, true) . "\n";
if ($page === 'mall1502') {
    $debugInfo .= "Period: " . $startDate . " ~ " . $endDate . "\n";
}
$debugInfo .= "Response Length: " . strlen($response) . "\n";
$debugInfo .= "-->\n";

http_response_code($httpCode);
echo $debugInfo . $response;
?>

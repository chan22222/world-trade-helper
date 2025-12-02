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

// 파라미터 받기
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$method = isset($_GET['method']) ? $_GET['method'] : 'auto'; // auto, web, api
$serviceKey = isset($_GET['serviceKey']) ? $_GET['serviceKey'] : '';

// 날짜 형식 변환
$dateStr = str_replace('-', '', $date); // YYYYMMDD

// 방법 1: UNIPASS 웹페이지 AJAX API
function fetchFromWeb($date) {
    $pageIndex = 1;
    $pageUnit = 100;
    $weekFxrtTpcd = '2'; // 수입
    $timestamp = round(microtime(true) * 1000);

    $url = "https://unipass.customs.go.kr/csp/myc/bsopspptinfo/dclrSpptInfo/WeekFxrtQryCtr/retrieveWeekFxrt.do";
    $url .= "?pageIndex={$pageIndex}";
    $url .= "&pageUnit={$pageUnit}";
    $url .= "&aplyDt={$date}";
    $url .= "&weekFxrtTpcd={$weekFxrtTpcd}";
    $url .= "&undefined={$date}";
    $url .= "&_={$timestamp}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    if (!ini_get('open_basedir')) {
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    }

    $headers = array(
        'Accept: application/json, text/javascript, */*; q=0.01',
        'Accept-Language: ko-KR,ko;q=0.9,en-US;q=0.8,en;q=0.7',
        'Referer: https://unipass.customs.go.kr/csp/index.do',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'X-Requested-With: XMLHttpRequest'
    );
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $cookieFile = sys_get_temp_dir() . '/unipass_' . md5($date) . '.txt';
    if (is_writable(sys_get_temp_dir())) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }

    curl_setopt($ch, CURLOPT_ENCODING, '');

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    curl_close($ch);

    if (file_exists($cookieFile)) {
        @unlink($cookieFile);
    }

    if ($error || $httpCode !== 200) {
        return null;
    }

    // JSON 파싱 시도
    $data = @json_decode($response, true);

    if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
        $data['source'] = 'unipass_web';
        return $data;
    }

    return null;
}

// 방법 2: 공공데이터 API
function fetchFromAPI($date, $serviceKey) {
    if (empty($serviceKey)) {
        // config.php에서 키 읽기 시도
        if (file_exists('config.php')) {
            include_once 'config.php';
            if (defined('SERVICE_KEY')) {
                $serviceKey = SERVICE_KEY;
            }
        }

        if (empty($serviceKey)) {
            return null;
        }
    }

    $aplyBgnDt = str_replace('-', '', $date);

    $ch = curl_init();
    $url = 'http://apis.data.go.kr/1220000/retrieveTrifFxrtInfo/getRetrieveTrifFxrtInfo';

    $queryParams = '?' . urlencode('serviceKey') . '=' . urlencode($serviceKey);
    $queryParams .= '&' . urlencode('aplyBgnDt') . '=' . urlencode($aplyBgnDt);
    $queryParams .= '&' . urlencode('weekFxrtTpcd') . '=' . urlencode('2');

    curl_setopt($ch, CURLOPT_URL, $url . $queryParams);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    curl_close($ch);

    if ($error || $httpCode !== 200 || empty($response)) {
        return null;
    }

    // XML 파싱
    try {
        $xml = @simplexml_load_string($response);
        if ($xml === false) {
            return null;
        }

        $json = json_encode($xml);
        $array = json_decode($json, TRUE);

        // 에러 체크
        if (isset($array['header']['resultCode']) && $array['header']['resultCode'] !== '00') {
            return null;
        }

        // 데이터 변환
        $result = array(
            'source' => 'public_data_api',
            'recordList' => array()
        );

        if (isset($array['body']['items']['item'])) {
            $items = $array['body']['items']['item'];

            // 단일 항목 처리
            if (isset($items['currSgn'])) {
                $items = array($items);
            }

            foreach ($items as $item) {
                if (isset($item['currSgn']) && isset($item['fxrt'])) {
                    $result['recordList'][] = array(
                        'currSgn' => $item['currSgn'],
                        'curr' => isset($item['curr']) ? $item['curr'] : $item['currSgn'],
                        'fxrt' => $item['fxrt']
                    );
                }
            }
        }

        return $result;

    } catch (Exception $e) {
        return null;
    }
}

// 메인 처리
$result = null;
$attempts = array();

if ($method === 'auto' || $method === 'web') {
    // 웹 파싱 시도
    $webResult = fetchFromWeb($date);
    if ($webResult) {
        $result = $webResult;
    } else {
        $attempts[] = 'web_failed';
    }
}

if (!$result && ($method === 'auto' || $method === 'api')) {
    // API 시도
    $apiResult = fetchFromAPI($date, $serviceKey);
    if ($apiResult) {
        $result = $apiResult;
    } else {
        $attempts[] = 'api_failed';
    }
}

// 결과 반환
if ($result) {
    $result['success'] = true;
    $result['date'] = $date;
    $result['attempts'] = $attempts;
    echo json_encode($result);
} else {
    echo json_encode(array(
        'error' => true,
        'message' => 'Failed to fetch exchange rates',
        'attempts' => $attempts,
        'date' => $date
    ));
}
?>
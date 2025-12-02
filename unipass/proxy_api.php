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
$serviceKey = isset($_GET['serviceKey']) ? $_GET['serviceKey'] : '';
$weekFxrtTpcd = isset($_GET['weekFxrtTpcd']) ? $_GET['weekFxrtTpcd'] : '2'; // 2: 수입

// 서비스 키 확인
if (empty($serviceKey)) {
    // config.php 파일에서 서비스 키 읽기 시도
    if (file_exists('config.php')) {
        include 'config.php';
        if (defined('SERVICE_KEY')) {
            $serviceKey = SERVICE_KEY;
        }
    }

    if (empty($serviceKey)) {
        echo json_encode(array(
            'error' => true,
            'message' => 'Service key is required'
        ));
        exit;
    }
}

// 날짜 형식 변환 (YYYY-MM-DD -> YYYYMMDD)
$aplyBgnDt = str_replace('-', '', $date);

// 공공데이터 API URL 구성
$ch = curl_init();
$url = 'http://apis.data.go.kr/1220000/retrieveTrifFxrtInfo/getRetrieveTrifFxrtInfo';

// 쿼리 파라미터 구성
$queryParams = '?' . urlencode('serviceKey') . '=' . urlencode($serviceKey);
$queryParams .= '&' . urlencode('aplyBgnDt') . '=' . urlencode($aplyBgnDt);
$queryParams .= '&' . urlencode('weekFxrtTpcd') . '=' . urlencode($weekFxrtTpcd);

// cURL 설정
curl_setopt($ch, CURLOPT_URL, $url . $queryParams);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_HEADER, FALSE);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

// SSL 설정 (필요한 경우)
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

// User-Agent 설정
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; PHP cURL)');

// 실행
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
$errno = curl_errno($ch);

curl_close($ch);

// cURL 에러 처리
if ($error) {
    echo json_encode(array(
        'error' => true,
        'message' => 'cURL Error: ' . $error,
        'errno' => $errno
    ));
    exit;
}

// HTTP 상태 확인
if ($httpCode !== 200) {
    echo json_encode(array(
        'error' => true,
        'message' => 'HTTP Error: ' . $httpCode,
        'response' => substr($response, 0, 500)
    ));
    exit;
}

// 응답이 비어있는지 확인
if (empty($response)) {
    echo json_encode(array(
        'error' => true,
        'message' => 'Empty response from API'
    ));
    exit;
}

// XML 응답을 JSON으로 변환
try {
    // XML 파싱
    $xml = simplexml_load_string($response);

    if ($xml === false) {
        throw new Exception('Failed to parse XML');
    }

    // XML을 배열로 변환
    $json = json_encode($xml);
    $array = json_decode($json, TRUE);

    // 응답 구조 확인 및 변환
    $result = array(
        'success' => true,
        'source' => 'public_data_api',
        'date' => $date
    );

    // 에러 체크
    if (isset($array['header']['resultCode']) && $array['header']['resultCode'] !== '00') {
        $result['error'] = true;
        $result['message'] = isset($array['header']['resultMsg']) ? $array['header']['resultMsg'] : 'API Error';
        $result['errorCode'] = $array['header']['resultCode'];
    } else {
        // 데이터 추출
        $items = array();

        // body > items > item 구조 확인
        if (isset($array['body']['items']['item'])) {
            $itemData = $array['body']['items']['item'];

            // 단일 항목인지 배열인지 확인
            if (isset($itemData['currSgn'])) {
                // 단일 항목
                $items[] = $itemData;
            } else {
                // 배열
                $items = $itemData;
            }
        }

        // recordList 형식으로 변환 (기존 JavaScript와 호환)
        $result['recordList'] = array();
        foreach ($items as $item) {
            if (isset($item['currSgn']) && isset($item['fxrt'])) {
                $result['recordList'][] = array(
                    'currSgn' => $item['currSgn'],     // 통화 코드
                    'curr' => isset($item['curr']) ? $item['curr'] : $item['currSgn'],  // 통화명
                    'fxrt' => $item['fxrt'],           // 환율
                    'aplyBgnDt' => isset($item['aplyBgnDt']) ? $item['aplyBgnDt'] : $aplyBgnDt
                );
            }
        }

        // 추가 정보
        $result['totalCount'] = count($result['recordList']);
        $result['rawXml'] = false; // 디버깅이 필요한 경우 $response로 변경
    }

    echo json_encode($result);

} catch (Exception $e) {
    // XML 파싱 실패 시 원본 응답 반환
    echo json_encode(array(
        'error' => true,
        'message' => 'XML parsing error: ' . $e->getMessage(),
        'response' => substr($response, 0, 1000)
    ));
}
?>
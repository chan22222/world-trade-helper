// API 설정 파일 템플릿
// 이 파일을 복사하여 config.js를 만들고 실제 인증키를 입력하세요

const CONFIG = {
    // 관세청 UNIPASS API 서비스 키
    // 발급 방법: https://www.data.go.kr/data/15101230/openapi.do 에서 활용신청
    SERVICE_KEY: 'YOUR_SERVICE_KEY_HERE',

    // API 엔드포인트
    API_URL: 'https://apis.data.go.kr/1220000/retrieveTrifFxrtInfo/getRetrieveTrifFxrtInfo',

    // 기타 설정
    CACHE_PREFIX: 'exchangeRate_uni_',
    CURRENCY_NAMES_KEY: 'currencyNames_uni',
    SELECTED_CURRENCIES_KEY: 'selectedCurrencies_uni'
};

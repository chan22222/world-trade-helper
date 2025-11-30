# 수입 환율 계산기 (UNIPASS API)

관세청 UNIPASS API를 활용한 환율 계산기입니다.

## 🚀 설치 방법

### 1. API 인증키 발급

1. [공공데이터포털](https://www.data.go.kr/data/15101230/openapi.do)에 접속
2. 회원가입/로그인 후 "활용신청" 클릭
3. 발급받은 인증키를 복사

### 2. 설정 파일 생성

```bash
# config.example.js를 복사하여 config.js 생성
cp config.example.js config.js
```

또는 수동으로 `config.example.js` 파일을 복사하여 `config.js` 파일을 만듭니다.

### 3. 인증키 입력

`config.js` 파일을 열어서 `YOUR_SERVICE_KEY_HERE` 부분을 발급받은 인증키로 교체합니다:

```javascript
const CONFIG = {
    SERVICE_KEY: '여기에_발급받은_인증키_입력',
    // ...
};
```

### 4. 실행

웹 서버에서 `index.html` 파일을 실행합니다.

```bash
# 간단한 로컬 서버 실행 (Python 3)
python -m http.server 8000

# 브라우저에서 http://localhost:8000 접속
```

## ⚠️ 보안 주의사항

- **절대로 `config.js` 파일을 Git에 커밋하지 마세요!**
- `.gitignore`에 `config.js`가 포함되어 있는지 확인하세요
- 공개 저장소에 푸시하기 전에 인증키가 노출되지 않았는지 확인하세요

## 📁 파일 구조

```
unipass/
├── index.html              # 메인 HTML 파일
├── proxy.php               # PHP CORS 프록시 (선택사항)
├── config.js               # API 인증키 (Git에서 제외됨)
├── config.example.js       # 설정 파일 템플릿
├── .gitignore              # Git 제외 파일 목록
└── README.md               # 이 파일
```

## 🔧 문제 해결

### API 호출 실패 시

1. F12를 눌러 개발자 도구를 엽니다
2. Console 탭에서 상세한 에러 메시지를 확인합니다
3. 주요 체크사항:
   - 인증키가 올바르게 입력되었는지 확인
   - 네트워크 연결 상태 확인
   - 주말/공휴일은 환율 데이터가 없을 수 있음
   - API 트래픽 제한(1,000회/일) 초과 여부 확인

### 주요 원인

- **HTTP/HTTPS 프로토콜 문제**: 공식 API는 HTTP를 사용하지만 현재 HTTPS로 요청 중
- **CORS 문제**: 프록시 서버 필요 (PHP 프록시 또는 서버리스 함수)
- **API 서버 문제**: 관세청 API 서버가 다운되었거나 느릴 수 있음

## 💡 향후 개선 사항

- Netlify Functions 또는 Vercel Edge Functions로 서버리스 프록시 구축
- 한국은행 API 등 HTTPS를 지원하는 대체 API로 전환
- 에러 처리 및 재시도 로직 개선

## 📚 참고 자료

- [관세청 환율정보 API 문서](https://www.data.go.kr/data/15101230/openapi.do)
- [UNIPASS 공식 사이트](https://unipass.customs.go.kr/)

## 📄 라이선스

이 프로젝트는 개인 사용 및 학습 목적으로 만들어졌습니다.

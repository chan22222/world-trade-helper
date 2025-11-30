const expressionDisplay = document.querySelector('p.expression');
const currentDisplay = document.querySelector('p.current');
const buttons = document.querySelectorAll('.button');

let curNum = '0';
let firstOperand = null;
let secondOperand = null;
let operator = null;

function updateDisplay() {
  // 숫자에 쉼표 추가
  const formattedNum = formatNumberWithCommas(curNum);
  currentDisplay.textContent = formattedNum;

  if (firstOperand !== null && operator) {
    const formattedFirstOperand = formatNumberWithCommas(firstOperand);
    expressionDisplay.textContent = `${formattedFirstOperand} ${operator}`;
  } else {
    expressionDisplay.textContent = '';
  }
}

// 숫자에 쉼표 추가하는 헬퍼 함수
function formatNumberWithCommas(num) {
  if (!num || num === '0') return num;

  // 소수점이 있는지 확인
  const parts = String(num).split('.');
  const integerPart = parts[0];
  const decimalPart = parts[1];

  // 정수 부분에 쉼표 추가
  const formattedInteger = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

  // 소수점이 있으면 다시 결합
  if (decimalPart !== undefined) {
    return `${formattedInteger}.${decimalPart}`;
  }

  return formattedInteger;
}

buttons.forEach((btn) => {
  btn.addEventListener('click', (e) => {
    const clickedNum = e.target.textContent;
    // console.log(`Clicked: ${clickedNum}`);

    if (btn.classList.contains('number')) {
      if (curNum === '0') curNum = clickedNum;
      else curNum += clickedNum;

      updateDisplay();
    }

    if (clickedNum === '.') {
      if (!curNum.includes('.')) curNum += '.';
      updateDisplay();
    }

    if (clickedNum === 'C') {
      curNum = '0';
      firstOperand = null;
      secondOperand = null;
      operator = null;
      updateDisplay();
      return;
    }

    if (clickedNum === '←') {
      if (curNum.length > 1) {
        curNum = curNum.slice(0, -1);
      } else {
        curNum = '0';
      }
      updateDisplay();
      return;
    }

    if (clickedNum === '📋' || clickedNum.includes('📋')) {
      // 클립보드에 현재 숫자 복사
      const copyBtn = e.target;
      const originalText = copyBtn.textContent;

      // 쉼표 제거한 원본 숫자
      const numToCopy = curNum;

      let copySuccess = false;

      // 방법 1: Clipboard API 시도 (최신 브라우저)
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(numToCopy)
          .then(() => {
            showCopyFeedback(copyBtn, originalText);
          })
          .catch(err => {
            console.log('Clipboard API 실패, 폴백 시도:', err);
            // 폴백 방법 시도
            copyUsingExecCommand(numToCopy, copyBtn, originalText);
          });
      } else {
        // 방법 2: execCommand 폴백 (구형 브라우저 또는 HTTP 환경)
        copyUsingExecCommand(numToCopy, copyBtn, originalText);
      }
      return;
    }

    if (btn.classList.contains('operator')) {
      if (operator && curNum === '0') {
        operator = clickedNum;
        updateDisplay();
        return;
      }

      if (operator && firstOperand !== null) {
        secondOperand = curNum;
        const result = calculate(firstOperand, operator, secondOperand);
        firstOperand = result;
        curNum = '0';
      } else {
        firstOperand = curNum;
        curNum = '0';
      }

      operator = clickedNum;
      updateDisplay();
      return;
    }

    if (clickedNum === '=') {
      if (operator && firstOperand !== null) {
        secondOperand = curNum;
        const result = calculate(firstOperand, operator, secondOperand);

        curNum = result;
        firstOperand = null;
        secondOperand = null;
        operator = null;
        updateDisplay();
      }
      return;
    }

    if (clickedNum === '+/-' && curNum !== '0') {
      curNum = String(Number(curNum) * -1);
      updateDisplay();
      return;
    }

    if (clickedNum === '%' && curNum !== '0') {
      curNum = String(Number(curNum) / 100);
      updateDisplay();
      return;
    }
  });
});

function calculate(num1, op, num2) {
  num1 = Number(num1);
  num2 = Number(num2);
  let result;

  switch (op) {
    case '+':
      result = num1 + num2;
      break;
    case '-':
      result = num1 - num2;
      break;
    case 'X':
      result = num1 * num2;
      break;
    case '/':
      result = num1 / num2;
      break;
    default:
      console.log('ERROR');
  }

  return String(parseFloat(result.toFixed(10)));
}

// 복사 피드백 표시
function showCopyFeedback(btn, originalText) {
  btn.classList.add('success');
  btn.textContent = '✓';
  setTimeout(() => {
    btn.classList.remove('success');
    btn.textContent = originalText;
  }, 500);
}

// execCommand를 사용한 복사 폴백 (HTTP 환경 지원)
function copyUsingExecCommand(text, btn, originalText) {
  try {
    // 임시 textarea 생성
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.top = '-9999px';
    textarea.style.left = '-9999px';
    document.body.appendChild(textarea);

    // 선택 및 복사
    textarea.select();
    textarea.setSelectionRange(0, textarea.value.length);

    const successful = document.execCommand('copy');
    document.body.removeChild(textarea);

    if (successful) {
      showCopyFeedback(btn, originalText);
    } else {
      console.error('execCommand 복사 실패');
      btn.classList.add('error');
      btn.textContent = '✗';
      setTimeout(() => {
        btn.classList.remove('error');
        btn.textContent = originalText;
      }, 500);
    }
  } catch (err) {
    console.error('복사 실패:', err);
    btn.classList.add('error');
    btn.textContent = '✗';
    setTimeout(() => {
      btn.classList.remove('error');
      btn.textContent = originalText;
    }, 500);
  }
}

// 초기 디스플레이 업데이트
updateDisplay();

// 키보드 이벤트 리스너
document.addEventListener('keydown', (e) => {
  // 백슬래시(\) 키: 부모 창에 계산기 닫기 요청
  if (e.key === '\\') {
    e.preventDefault();
    // 부모 창(메인 페이지)에 메시지 전송
    window.parent.postMessage('toggleCalculator', '*');
    return;
  }

  // Ctrl+C는 브라우저 기본 복사 기능 사용
  if (e.ctrlKey && e.key === 'c') {
    return;
  }

  const key = e.key;

  // 숫자 입력 (0-9)
  if (key >= '0' && key <= '9') {
    e.preventDefault();
    if (curNum === '0') curNum = key;
    else curNum += key;
    updateDisplay();
    return;
  }

  // 소수점
  if (key === '.') {
    e.preventDefault();
    if (!curNum.includes('.')) curNum += '.';
    updateDisplay();
    return;
  }

  // 연산자
  if (key === '+' || key === '-') {
    e.preventDefault();
    handleOperator(key);
    return;
  }

  if (key === '*' || key === 'x' || key === 'X') {
    e.preventDefault();
    handleOperator('X');
    return;
  }

  if (key === '/') {
    e.preventDefault();
    handleOperator('/');
    return;
  }

  // Enter 또는 = : 계산 실행
  if (key === 'Enter' || key === '=') {
    e.preventDefault();
    if (operator && firstOperand !== null) {
      secondOperand = curNum;
      const result = calculate(firstOperand, operator, secondOperand);
      curNum = result;
      firstOperand = null;
      secondOperand = null;
      operator = null;
      updateDisplay();
    }
    return;
  }

  // Backspace: 한 글자 지우기
  if (key === 'Backspace') {
    e.preventDefault();
    if (curNum.length > 1) {
      curNum = curNum.slice(0, -1);
    } else {
      curNum = '0';
    }
    updateDisplay();
    return;
  }

  // Escape 또는 C: 전체 클리어
  if (key === 'Escape' || key === 'c' || key === 'C') {
    e.preventDefault();
    curNum = '0';
    firstOperand = null;
    secondOperand = null;
    operator = null;
    updateDisplay();
    return;
  }

  // %: 퍼센트
  if (key === '%') {
    e.preventDefault();
    if (curNum !== '0') {
      curNum = String(Number(curNum) / 100);
      updateDisplay();
    }
    return;
  }
});

// 연산자 처리 함수
function handleOperator(op) {
  if (operator && curNum === '0') {
    operator = op;
    updateDisplay();
    return;
  }

  if (operator && firstOperand !== null) {
    secondOperand = curNum;
    const result = calculate(firstOperand, operator, secondOperand);
    firstOperand = result;
    curNum = '0';
  } else {
    firstOperand = curNum;
    curNum = '0';
  }

  operator = op;
  updateDisplay();
}
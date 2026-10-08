(() => {
  // Commission 환율 표시 요소가 없는 페이지에서는 실행할 필요가 없습니다.
  const rateOutput = document.querySelector('[data-usd-krw-rate]');
  const updatedOutput = document.querySelector('[data-usd-krw-updated]');
  // 두 표시 요소가 있어야 환율과 갱신 시각을 모두 보여줄 수 있습니다.
  if (!(rateOutput instanceof HTMLOutputElement)
    || !(updatedOutput instanceof HTMLElement)) {
    return;
  }

  const refreshRate = async () => {
    try {
      // 공개 API에서 USD 기준 환율을 가져옵니다. KRW 값은 응답 검증 후 표시합니다.
      const response = await fetch('https://open.er-api.com/v6/latest/USD', {
        headers: { Accept: 'application/json' },
        cache: 'no-store',
      });
      // HTTP 오류 응답은 정상 환율 데이터로 처리하지 않습니다.
      if (!response.ok) {
        throw new Error(`Exchange rate request failed with HTTP ${response.status}`);
      }

      const data = await response.json();
      const rate = data?.rates?.KRW;
      const updatedAt = data?.time_last_update_utc;
      // API 성공 여부, 환율 숫자, 갱신 시각이 모두 유효한지 확인합니다.
      if (data?.result !== 'success'
        || typeof rate !== 'number'
        || !Number.isFinite(rate)
        || typeof updatedAt !== 'string'
        || Number.isNaN(Date.parse(updatedAt))) {
        throw new Error('Exchange rate response is missing a valid USD/KRW rate or update time.');
      }

      rateOutput.textContent = `1 USD = ${new Intl.NumberFormat('ko-KR', {
        maximumFractionDigits: 2,
      }).format(rate)} KRW`;
      // API 갱신 시각을 한국 시간으로 바꾸고 참고 환율임을 함께 안내합니다.
      updatedOutput.textContent = `마지막 갱신: ${new Intl.DateTimeFormat('ko-KR', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'Asia/Seoul',
      }).format(new Date(updatedAt))} (한국 시간) · 참고용`;
    } catch (error) {
      console.error('Unable to load the current USD/KRW exchange rate.', error);
      rateOutput.textContent = '환율 정보를 불러오지 못했습니다.';
      updatedOutput.textContent = '잠시 후 다시 확인해 주세요.';
    }
  };

  void refreshRate();
  // 환율 표시가 오래되지 않도록 30분마다 다시 요청합니다.
  window.setInterval(() => void refreshRate(), 30 * 60 * 1000);
})();

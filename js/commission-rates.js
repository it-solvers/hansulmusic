(() => {
  const rateOutput = document.querySelector('[data-usd-krw-rate]');
  const updatedOutput = document.querySelector('[data-usd-krw-updated]');
  if (!(rateOutput instanceof HTMLOutputElement)
    || !(updatedOutput instanceof HTMLElement)) {
    return;
  }

  const refreshRate = async () => {
    try {
      const response = await fetch('https://open.er-api.com/v6/latest/USD', {
        headers: { Accept: 'application/json' },
        cache: 'no-store',
      });
      if (!response.ok) {
        throw new Error(`Exchange rate request failed with HTTP ${response.status}`);
      }

      const data = await response.json();
      const rate = data?.rates?.KRW;
      const updatedAt = data?.time_last_update_utc;
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
  window.setInterval(() => void refreshRate(), 30 * 60 * 1000);
})();

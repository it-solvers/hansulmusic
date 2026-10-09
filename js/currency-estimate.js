(() => {
  const prices = [...document.querySelectorAll('[data-krw-price]:not([data-paypal-fee-estimate])')];
  const paypalFeeEstimates = [...document.querySelectorAll('[data-paypal-fee-estimate]')];
  const note = document.querySelector('[data-currency-note]');
  const status = note?.querySelector('[data-currency-status]');
  if (prices.length === 0) {
    if (status instanceof HTMLElement) status.textContent = 'No product prices are currently available.';
    return;
  }
  const cacheKey = 'hansul-krw-usd-reference';
  const cacheMaxAge = 6 * 60 * 60 * 1000;

  const readCache = () => {
    try {
      const cached = JSON.parse(sessionStorage.getItem(cacheKey) || 'null');
      if (typeof cached?.rate === 'number'
        && Number.isFinite(cached.rate)
        && cached.rate > 0
        && typeof cached.date === 'string'
        && typeof cached.savedAt === 'number') {
        return cached;
      }
    } catch {
      return null;
    }
    return null;
  };

  const writeCache = (rate, date) => {
    try {
      sessionStorage.setItem(cacheKey, JSON.stringify({ rate, date, savedAt: Date.now() }));
    } catch {
      // Currency display still works when browser storage is unavailable.
    }
  };

  const renderPrices = (rate) => {
    const formatter = new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
    prices.forEach((price) => {
      const krw = Number(price.dataset.krwPrice);
      if (!Number.isFinite(krw) || krw < 0) {
        throw new Error('Invalid KRW product price.');
      }
      price.textContent = formatter.format(krw * rate);
    });
    paypalFeeEstimates.forEach((estimate) => {
      const krw = Number(estimate.dataset.krwPrice);
      if (!Number.isFinite(krw) || krw < 0) {
        throw new Error('Invalid KRW product price for PayPal fee estimate.');
      }
      const feeUsd = (krw * rate * 0.044) + 0.30;
      estimate.textContent = `Estimated PayPal fee: ${formatter.format(feeUsd)} per order`;
    });
  };

  const setStatus = (date, cached = false) => {
    if (status instanceof HTMLElement) {
      const prefix = cached ? 'Using a saved reference rate' : 'Latest available reference rate';
      status.textContent = `${prefix} (${date}).`;
    }
  };

  const loadRate = async () => {
    const cached = readCache();
    if (cached && Date.now() - cached.savedAt < cacheMaxAge) {
      renderPrices(cached.rate);
      setStatus(cached.date, true);
      return;
    }

    const controller = new AbortController();
    const timeout = window.setTimeout(() => controller.abort(), 5000);
    try {
      const response = await fetch('https://api.frankfurter.dev/v1/latest?base=KRW&symbols=USD', {
        headers: { Accept: 'application/json' },
        signal: controller.signal,
      });
      if (!response.ok) throw new Error(`Exchange-rate request failed (${response.status}).`);
      const result = await response.json();
      const rate = result?.rates?.USD;
      if (typeof rate !== 'number'
        || !Number.isFinite(rate)
        || rate <= 0
        || typeof result.date !== 'string') {
        throw new Error('The exchange-rate response was invalid.');
      }
      renderPrices(rate);
      writeCache(rate, result.date);
      setStatus(result.date);
    } catch (error) {
      if (cached) {
        renderPrices(cached.rate);
        setStatus(cached.date, true);
        return;
      }
      prices.forEach((price) => {
        price.textContent = 'USD estimate unavailable';
      });
      paypalFeeEstimates.forEach((estimate) => {
        estimate.textContent = 'PayPal fee estimate unavailable';
      });
      if (status instanceof HTMLElement) {
        status.textContent = 'The exchange rate could not be loaded. Please try again later.';
      }
      console.error('Unable to load the KRW/USD reference exchange rate.', error);
    } finally {
      window.clearTimeout(timeout);
    }
  };

  void loadRate();
})();

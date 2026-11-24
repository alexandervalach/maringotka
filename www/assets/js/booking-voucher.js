(() => {
  'use strict';

  const form = document.querySelector('.booking-form form');
  const calendar = document.querySelector('.booking-calendar');
  const code = form?.elements.namedItem('voucher_code');
  const status = form?.querySelector('[data-voucher-status]');
  const details = form?.querySelector('[data-voucher-details]');
  const minimumText = document.querySelector('[data-booking-minimum]');
  const standardMinimum = minimumText?.textContent;
  const intro = form.querySelector('.booking-quote__intro');
  const standardIntro = intro?.textContent;
  if (!form || !calendar || !(code instanceof HTMLInputElement) || !status || !details) return;

  const isEnglish = calendar.dataset.locale === 'en';
  const fields = ['wellness', 'breakfast', 'romance', 'frizzante', 'canvas_bag'];
  let requestId = 0;
  let timer;

  function applyVoucher(voucher) {
    const wasVoucher = form.dataset.voucherValid === 'true';
    form.dataset.voucherNights = voucher ? String(voucher.nights) : '';
    form.dataset.voucherValid = voucher ? 'true' : 'false';
    form.dataset.voucherExpires = voucher ? voucher.expires : '';
    if (minimumText) {
      minimumText.textContent = voucher
        ? (isEnglish ? `Voucher stay: exactly ${voucher.nights} ${voucher.nights === 1 ? 'night' : 'nights'}.`
          : `Pobyt podľa poukazu: presne ${voucher.nights} ${voucher.nights === 1 ? 'noc' : voucher.nights < 5 ? 'noci' : 'nocí'}.`)
        : standardMinimum;
    }

    if (intro) {
      intro.textContent = voucher
        ? (isEnglish ? 'Services covered by your prepaid voucher:' : 'Služby zahrnuté v predplatenom poukaze:')
        : standardIntro;
    }

    for (const name of fields) {
      const field = form.elements.namedItem(name);
      if (!(field instanceof HTMLInputElement)) continue;
      field.disabled = Boolean(voucher);
      if (!voucher) {
        if (wasVoucher) {
          if (field.type === 'checkbox') field.checked = false;
          else field.value = '0';
        }
        continue;
      }

      if (field.type === 'checkbox') {
        field.checked = name === 'wellness' && voucher.wellness;
      } else {
        field.value = name === 'frizzante' && voucher.wine ? '1' : '0';
      }
    }

    if (voucher) {
      const nightLabel = isEnglish ? (voucher.nights === 1 ? 'night' : 'nights') :
        (voucher.nights === 1 ? 'noc' : voucher.nights < 5 ? 'noci' : 'nocí');
      details.textContent = isEnglish
        ? `Voucher: exactly ${voucher.nights} ${nightLabel}. Included services are locked.`
        : `Poukaz: presne ${voucher.nights} ${nightLabel}. Zahrnuté služby nie je možné meniť.`;
      details.hidden = false;
    } else {
      details.textContent = '';
      details.hidden = true;
    }

    form.dispatchEvent(new Event('voucher:change'));
  }

  function showStatus(text, error = false) {
    status.textContent = text;
    status.dataset.state = error ? 'error' : 'ok';
  }

  async function verify(id, value) {
    try {
      const url = new URL(calendar.dataset.voucherCheckUrl, window.location.href);
      url.searchParams.set('code', value);
      const response = await fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store' });
      if (!response.ok) throw new Error('Voucher check failed');
      const result = await response.json();
      if (id !== requestId || code.value.trim().toUpperCase() !== value) return;
      if (!result.valid || !Number.isInteger(result.nights) || result.nights < 1 || result.nights > 30 || !/^\d{4}-\d{2}-\d{2}$/.test(result.expires)) {
        applyVoucher(null);
        showStatus(isEnglish ? 'Voucher is invalid, expired or already used.' :
          'Poukaz je neplatný, expirovaný alebo už použitý.', true);
        return;
      }
      applyVoucher(result);
      showStatus(isEnglish ? 'Voucher verified.' : 'Poukaz bol overený.');
    } catch (_) {
      if (id !== requestId) return;
      applyVoucher(null);
      showStatus(isEnglish ? 'Voucher cannot be verified right now.' :
        'Poukaz sa momentálne nepodarilo overiť.', true);
    }
  }

  function changed() {
    const id = ++requestId;
    clearTimeout(timer);
    applyVoucher(null);
    const value = code.value.trim().toUpperCase();
    if (!value) { showStatus(''); return; }
    showStatus(isEnglish ? 'Checking voucher…' : 'Overujem poukaz…');
    timer = setTimeout(() => verify(id, value), 350);
  }

  form.addEventListener('submit', (event) => {
    if (code.value.trim() && form.dataset.voucherValid !== 'true') {
      event.preventDefault();
      showStatus(isEnglish ? 'Enter a valid voucher before submitting.' :
        'Pred odoslaním zadajte platný poukaz.', true);
      code.focus();
    }
  });

  code.addEventListener('input', changed);
  changed();
})();

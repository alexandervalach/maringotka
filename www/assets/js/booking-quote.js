(() => {
  'use strict';

  const quote = document.querySelector('[data-booking-quote]');
  const form = quote?.closest('form');
  if (!quote || !form) return;

  const arrival = form.elements.namedItem('arrival_date');
  const departure = form.elements.namedItem('departure_date');
  if (!(arrival instanceof HTMLInputElement) || !(departure instanceof HTMLInputElement)) return;

  const voucherCode = form.elements.namedItem('voucher_code');
  const options = quote.querySelector('.booking-quote__options');
  const summary = quote.querySelector('.booking-quote__summary');
  const notice = quote.querySelector('.booking-quote__notice');
  const isEnglish = quote.dataset.locale === 'en';
  const locale = isEnglish ? 'en-IE' : 'sk-SK';
  const formatter = new Intl.NumberFormat(locale, { style: 'currency', currency: 'EUR' });
  const output = (name, text) => {
    const element = quote.querySelector(`[data-quote-${name}]`);
    if (element) element.textContent = text;
  };
  const price = (name) => Number(quote.dataset[name]);
  const checkbox = (name) => form.elements.namedItem(name)?.checked === true;
  const quantity = (name) => {
    const input = form.elements.namedItem(name);
    const value = Number(input?.value ?? '0');
    return Number.isInteger(value) && value >= 0 && value <= 20 ? value : null;
  };
  const dayNumber = (iso) => {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(iso)) return null;
    const date = new Date(`${iso}T00:00:00Z`);
    return Number.isFinite(date.getTime()) && date.toISOString().slice(0, 10) === iso
      ? date.getTime() / 86400000 : null;
  };

  function render() {
    const usingVoucher = voucherCode instanceof HTMLInputElement && voucherCode.value.trim() !== '';
    if (options) { options.hidden = usingVoucher; options.style.display = usingVoucher ? 'none' : ''; }
    if (summary) { summary.hidden = usingVoucher; summary.style.display = usingVoucher ? 'none' : ''; }
    if (notice) notice.textContent = usingVoucher
      ? (isEnglish ? 'Voucher code and stay dates will be verified when you submit.' : 'Kód a termín poukazu overíme pri odoslaní.')
      : (isEnglish ? 'The amount is indicative. The request is not a confirmed reservation.' : 'Cena je orientačná. Žiadosť nie je potvrdená rezervácia.');
    if (usingVoucher) return;

    const start = dayNumber(arrival.value);
    const end = dayNumber(departure.value);
    const nights = start === null || end === null ? null : end - start;
    const min = Number(quote.dataset.minNights);
    const max = Number(quote.dataset.maxNights);
    const wine = quantity('frizzante');
    const bags = quantity('canvas_bag');

    if (nights === null || nights < min || nights > max || wine === null || bags === null) {
      for (const key of ['nights', 'accommodation', 'extras', 'total']) output(key, '–');
      return;
    }

    const accommodation = price('firstNight') + (nights - 1) * price('additionalNight');
    const extras = (checkbox('wellness') ? nights * price('wellness') : 0)
      + (checkbox('breakfast') ? nights * price('breakfast') : 0)
      + (checkbox('romance') ? price('romance') : 0)
      + wine * price('frizzante') + bags * price('canvasBag');

    output('nights', String(nights));
    output('accommodation', formatter.format(accommodation / 100));
    output('extras', formatter.format(extras / 100));
    output('total', formatter.format((accommodation + extras) / 100));
  }

  form.addEventListener('input', render);
  form.addEventListener('change', render);
  render();
})();

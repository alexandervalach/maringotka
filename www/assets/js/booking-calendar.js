(() => {
  'use strict';

  const calendar = document.querySelector('.booking-calendar');
  const form = document.querySelector('.booking-form form');
  if (!calendar || !form) return;

  const arrival = form.querySelector('[name="arrival_date"]');
  const departure = form.querySelector('[name="departure_date"]');
  if (!arrival || !departure) return;

  const controls = calendar.querySelector('.booking-calendar-controls');
  const days = calendar.querySelector('.booking-calendar-days');
  const heading = calendar.querySelector('.booking-calendar-month');
  const message = calendar.querySelector('.booking-calendar-message');
  const previous = calendar.querySelector('.booking-calendar-prev');
  const next = calendar.querySelector('.booking-calendar-next');
  const isEnglish = calendar.dataset.locale === 'en';
  const minimum = Number(calendar.dataset.minNights) || 1;
  const today = calendar.dataset.today;
  const until = calendar.dataset.until;
  const intervals = Array.from(calendar.querySelectorAll('[data-start][data-end]'))
    .map((item) => ({ start: item.dataset.start, end: item.dataset.end }));
  const firstMonth = today.slice(0, 7);
  let monthIndex = 0;
  let selectingDeparture = Boolean(arrival.value && !departure.value);

  const dayMs = 86400000;
  const dateUtc = (iso) => Date.parse(`${iso}T00:00:00Z`);
  const addDays = (iso, count) => new Date(dateUtc(iso) + count * dayMs).toISOString().slice(0, 10);
  const nightsBetween = (start, end) => Math.round((dateUtc(end) - dateUtc(start)) / dayMs);
  const occupied = (iso) => intervals.some(({ start, end }) => start <= iso && iso < end);
  const crossesOccupied = (start, end) => intervals.some((interval) => start < interval.end && end > interval.start);
  const inRange = (iso) => iso >= today && iso < until;

  function validStay(start, end) {
    return inRange(start) && end > start && end <= until &&
      nightsBetween(start, end) >= minimum && nightsBetween(start, end) <= 365 &&
      !crossesOccupied(start, end);
  }

  function notice(text) {
    message.textContent = text;
  }

  function updateDates() {
    departure.min = arrival.value ? addDays(arrival.value, minimum) : addDays(today, minimum);
    render();
  }

  function selectDate(iso) {
    if (!selectingDeparture || !arrival.value || iso <= arrival.value) {
      if (occupied(iso) || !inRange(iso)) {
        notice(isEnglish ? 'This arrival date is unavailable.' : 'Tento deň príchodu je obsadený.');
        return;
      }
      arrival.value = iso;
      departure.value = '';
      selectingDeparture = true;
      notice(isEnglish ? 'Now select your check-out date.' : 'Teraz vyberte deň odchodu.');
    } else if (validStay(arrival.value, iso)) {
      departure.value = iso;
      selectingDeparture = false;
      notice(isEnglish ? 'Dates selected.' : 'Termín bol vybraný.');
    } else {
      notice(isEnglish
        ? 'This stay crosses occupied nights or does not meet the minimum stay.'
        : 'Pobyt zasahuje do obsadených nocí alebo nespĺňa minimálnu dĺžku.');
      return;
    }
    arrival.dispatchEvent(new Event('change', { bubbles: true }));
    updateDates();
  }

  function render() {
    const [year, initialMonth] = firstMonth.split('-').map(Number);
    const date = new Date(Date.UTC(year, initialMonth - 1 + monthIndex, 1));
    heading.textContent = new Intl.DateTimeFormat(isEnglish ? 'en-GB' : 'sk-SK', {
      month: 'long', year: 'numeric', timeZone: 'UTC',
    }).format(date);
    previous.disabled = monthIndex === 0;
    next.disabled = monthIndex >= 11;
    days.replaceChildren();
    const labels = isEnglish ? ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
      : ['Po', 'Ut', 'St', 'Št', 'Pi', 'So', 'Ne'];
    for (const label of labels) {
      const cell = document.createElement('span');
      cell.className = 'booking-calendar-weekday';
      cell.textContent = label;
      days.appendChild(cell);
    }
    const offset = (date.getUTCDay() + 6) % 7;
    for (let i = 0; i < offset; i++) {
      const spacer = document.createElement('span');
      spacer.setAttribute('aria-hidden', 'true');
      days.appendChild(spacer);
    }
    const count = new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth() + 1, 0)).getUTCDate();
    for (let d = 1; d <= count; d++) {
      const iso = `${date.getUTCFullYear()}-${String(date.getUTCMonth() + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'booking-calendar-day';
      button.textContent = String(d);
      button.setAttribute('aria-label', iso);
      const blocked = occupied(iso);
      if (blocked) button.classList.add('is-occupied');
      if (arrival.value === iso) button.classList.add('is-arrival');
      if (departure.value === iso) button.classList.add('is-departure');
      if (arrival.value && departure.value && iso > arrival.value && iso < departure.value) {
        button.classList.add('is-selected');
      }
      const selectableCheckout = selectingDeparture && arrival.value && iso > arrival.value &&
        validStay(arrival.value, iso);
      button.disabled = (!inRange(iso) && !selectableCheckout) || (blocked && !selectableCheckout);
      if (blocked) button.title = isEnglish ? 'Occupied night' : 'Obsadená noc';
      if (!button.disabled) button.addEventListener('click', () => selectDate(iso));
      days.appendChild(button);
    }
  }

  arrival.addEventListener('change', () => {
    if (arrival.value && (!inRange(arrival.value) || occupied(arrival.value))) {
      arrival.setCustomValidity(isEnglish ? 'This arrival date is occupied or unavailable.' : 'Tento deň príchodu je obsadený alebo nedostupný.');
    } else {
      arrival.setCustomValidity('');
    }
    if (departure.value && !validStay(arrival.value, departure.value)) departure.value = '';
    selectingDeparture = Boolean(arrival.value && !departure.value);
    updateDates();
  });
  departure.addEventListener('change', () => {
    departure.setCustomValidity(departure.value && !validStay(arrival.value, departure.value)
      ? (isEnglish ? 'This stay is unavailable.' : 'Tento termín nie je dostupný.') : '');
    selectingDeparture = Boolean(arrival.value && !departure.value);
    render();
  });
  form.addEventListener('submit', (event) => {
    if (arrival.value && departure.value && !validStay(arrival.value, departure.value)) {
      event.preventDefault();
      departure.setCustomValidity(isEnglish ? 'This stay is unavailable.' : 'Tento termín nie je dostupný.');
      departure.reportValidity();
    }
  });
  previous.addEventListener('click', () => { monthIndex--; render(); });
  next.addEventListener('click', () => { monthIndex++; render(); });
  controls.hidden = false;
  days.hidden = false;
  updateDates();
})();

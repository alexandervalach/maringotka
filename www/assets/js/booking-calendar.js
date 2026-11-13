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
  const reset = calendar.querySelector('.booking-calendar-reset');
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
  // Arrival is inclusive, departure is exclusive. Checkout on the next guest's
  // arrival day is legal, even if that day is marked as an occupied night.
  const crossesOccupied = (start, end) => intervals.some(({ start: bookedStart, end: bookedEnd }) =>
    start < bookedEnd && end > bookedStart);

  const inRange = (iso) => iso >= today && iso < until;

  function validStay(start, end) {
    return inRange(start) && end > start && end <= until &&
      nightsBetween(start, end) >= minimum && nightsBetween(start, end) <= 30 &&
      !crossesOccupied(start, end);
  }

  function notice(text) {
    message.textContent = text;
  }

  function updateDates() {
    departure.min = arrival.value ? addDays(arrival.value, minimum) : addDays(today, minimum);
    reset.hidden = !arrival.value && !departure.value;
    render();
  }

  function emitDateChange() {
    // Keep the existing live price calculator synchronized with the calendar.
    arrival.dispatchEvent(new Event('change', { bubbles: true }));
    departure.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function clearSelection() {
    arrival.value = '';
    departure.value = '';
    selectingDeparture = false;
    notice(isEnglish ? 'Choose your arrival date.' : 'Vyberte deň príchodu.');
    emitDateChange();
    updateDates();
  }

  function selectDate(iso) {
    // Clicking the selected arrival again clears it. Clicking an earlier date
    // starts a fresh selection without requiring a separate reset action.
    if (selectingDeparture && arrival.value === iso) {
      clearSelection();
      return;
    }

    if (!selectingDeparture || !arrival.value || iso < arrival.value) {
      if (!inRange(iso) || occupied(iso)) {
        notice(isEnglish ? 'This arrival date is unavailable.' : 'Tento deň príchodu nie je dostupný.');
        return;
      }

      arrival.value = iso;
      departure.value = '';
      selectingDeparture = true;
      notice(isEnglish ? 'Now select your check-out date.' : 'Teraz vyberte deň odchodu.');
    } else if (validStay(arrival.value, iso)) {
      departure.value = iso;
      selectingDeparture = false;
      notice(isEnglish ? 'Dates selected. You can start again by choosing another day.'
        : 'Termín bol vybraný. Kliknutím na iný deň môžete začať nový výber.');
    } else {
      notice(isEnglish
        ? 'This stay crosses occupied nights or does not meet the stay limits.'
        : 'Pobyt zasahuje do obsadených nocí alebo nespĺňa povolenú dĺžku.');
      return;
    }

    emitDateChange();
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
      if (arrival.value === iso) button.classList.add('is-arrival');
      if (departure.value === iso) button.classList.add('is-departure');
      if (arrival.value && departure.value && iso > arrival.value && iso < departure.value) {
        button.classList.add('is-selected');
      }
      const selectableCheckout = selectingDeparture && arrival.value &&
        validStay(arrival.value, iso);

      // A booked day can still be clicked as checkout when the guest leaves
      // before the next guest arrives. Never enable a day inside the stay.
      button.disabled = (!inRange(iso) && !selectableCheckout) || (blocked && !selectableCheckout);
      if (selectableCheckout && blocked) {
        // In checkout-selection mode show this boundary as selectable rather
        // than as a disabled occupied night. The next guest keeps that night.
        button.title = isEnglish ? 'Available for checkout' : 'Možný deň odchodu';
        button.classList.add('is-checkout-available');
      } else if (blocked) {
        button.classList.add('is-occupied');
        button.title = isEnglish ? 'Occupied night' : 'Obsadená noc';
      }
      button.setAttribute('aria-pressed', String(arrival.value === iso || departure.value === iso));
      if (!button.disabled) button.addEventListener('click', () => selectDate(iso));
      days.appendChild(button);
    }
  }

  arrival.addEventListener('change', () => {
    if (arrival.value && (!inRange(arrival.value) || occupied(arrival.value))) {
      arrival.setCustomValidity(isEnglish ? 'This arrival date is unavailable.' : 'Tento deň príchodu nie je dostupný.');
    } else {
      arrival.setCustomValidity('');
    }

    if (departure.value && !validStay(arrival.value, departure.value)) {
      departure.value = '';
    }

    selectingDeparture = Boolean(arrival.value && !departure.value);
    updateDates();
  });

  departure.addEventListener('change', () => {
    departure.setCustomValidity(departure.value && !validStay(arrival.value, departure.value)
      ? (isEnglish ? 'This stay is unavailable.' : 'Tento termín nie je dostupný.') : '');
    selectingDeparture = Boolean(arrival.value && !departure.value);
    updateDates();
  });

  form.addEventListener('submit', (event) => {
    if (arrival.value && departure.value && validStay(arrival.value, departure.value)) {
      return;
    }

    event.preventDefault();
    notice(isEnglish ? 'Select a valid arrival and departure date.'
      : 'Vyberte platný príchod a odchod priamo v kalendári.');
    calendar.scrollIntoView({ behavior: 'smooth', block: 'center' });
  });

  previous.addEventListener('click', () => { monthIndex--; render(); });
  next.addEventListener('click', () => { monthIndex++; render(); });
  reset.addEventListener('click', clearSelection);

  // The native date inputs remain usable when JavaScript is disabled.
  // On an enhanced page the calendar is the sole date selector.
  for (const input of [arrival, departure]) {
    input.type = 'hidden';
    input.required = false;
    const field = input.closest('.booking-field');
    if (field) field.hidden = true;
  }

  controls.hidden = false;
  days.hidden = false;
  updateDates();
})();

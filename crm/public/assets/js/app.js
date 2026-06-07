document.addEventListener('submit', function (event) {
  const form = event.target;
  const submitter = event.submitter instanceof HTMLElement ? event.submitter : null;
  const confirmTarget = form.matches('[data-confirm]') ? form : submitter?.closest('[data-confirm]');
  if (confirmTarget) {
    const message = confirmTarget.getAttribute('data-confirm') || 'آیا مطمئن هستید؟';
    if (!confirm(message)) {
      event.preventDefault();
    }
  }
});

document.querySelectorAll('[data-weighted]').forEach(function (box) {
  const amount = document.querySelector('[name="estimated_amount"]');
  const probability = document.querySelector('[name="probability"]');
  const update = function () {
    const value = (Number(amount?.value || 0) * Number(probability?.value || 0)) / 100;
    const unit = box.getAttribute('data-currency') || 'ریال';
    box.textContent = new Intl.NumberFormat('fa-IR').format(value) + ' ' + unit;
  };
  amount?.addEventListener('input', update);
  probability?.addEventListener('input', update);
  update();
});

function generatePortalPassword() {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
  let password = '';
  for (let i = 0; i < 10; i++) {
    password += chars[Math.floor(Math.random() * chars.length)];
  }
  return password;
}

document.addEventListener('click', function (event) {
  const target = event.target;
  if (!(target instanceof HTMLElement)) return;
  const wrapper = target.closest('.password-tools');
  if (!wrapper) return;
  const input = wrapper.querySelector('[data-password-field]');
  if (!(input instanceof HTMLInputElement)) return;

  if (target.matches('[data-generate-password]')) {
    input.value = generatePortalPassword();
    input.type = 'text';
    input.dispatchEvent(new Event('input', { bubbles: true }));
  }
  if (target.matches('[data-toggle-password]')) {
    input.type = input.type === 'password' ? 'text' : 'password';
    target.textContent = input.type === 'password' ? 'نمایش' : 'مخفی';
  }
  if (target.matches('[data-copy-password]')) {
    input.select();
    navigator.clipboard?.writeText(input.value);
  }
});

const persianMonths = [
  'فروردین',
  'اردیبهشت',
  'خرداد',
  'تیر',
  'مرداد',
  'شهریور',
  'مهر',
  'آبان',
  'آذر',
  'دی',
  'بهمن',
  'اسفند'
];

const persianWeekdays = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];

function toEnglishDigits(value) {
  return String(value || '').replace(/[۰-۹٠-٩]/g, function (digit) {
    return '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩'.indexOf(digit) % 10;
  });
}

function pad2(value) {
  return String(value).padStart(2, '0');
}

function formatJalali(year, month, day) {
  return year + '/' + pad2(month) + '/' + pad2(day);
}

function parseJalali(value) {
  const clean = toEnglishDigits(value).trim().replace(/[.\-\\]/g, '/');
  const match = clean.match(/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/);
  if (!match) return null;
  const year = Number(match[1]);
  const month = Number(match[2]);
  const day = Number(match[3]);
  if (month < 1 || month > 12 || day < 1 || day > jalaliMonthLength(year, month)) return null;
  return { year, month, day };
}

function div(a, b) {
  return Math.floor(a / b);
}

function gregorianToJalali(gy, gm, gd) {
  const gdm = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
  const gy2 = gm > 2 ? gy + 1 : gy;
  let days = 355666 + (365 * gy) + div(gy2 + 3, 4) - div(gy2 + 99, 100) + div(gy2 + 399, 400) + gd + gdm[gm - 1];
  let jy = -1595 + (33 * div(days, 12053));
  days %= 12053;
  jy += 4 * div(days, 1461);
  days %= 1461;
  if (days > 365) {
    jy += div(days - 1, 365);
    days = (days - 1) % 365;
  }
  const jm = days < 186 ? 1 + div(days, 31) : 7 + div(days - 186, 30);
  const jd = days < 186 ? 1 + (days % 31) : 1 + ((days - 186) % 30);
  return { year: jy, month: jm, day: jd };
}

function jalaliToGregorian(jy, jm, jd) {
  jy += 1595;
  let days = -355668 + (365 * jy) + (div(jy, 33) * 8) + div((jy % 33) + 3, 4) + jd;
  days += jm < 7 ? (jm - 1) * 31 : ((jm - 7) * 30) + 186;
  let gy = 400 * div(days, 146097);
  days %= 146097;
  if (days > 36524) {
    gy += 100 * div(--days, 36524);
    days %= 36524;
    if (days >= 365) days++;
  }
  gy += 4 * div(days, 1461);
  days %= 1461;
  if (days > 365) {
    gy += div(days - 1, 365);
    days = (days - 1) % 365;
  }
  let gd = days + 1;
  const months = [0, 31, ((gy % 4 === 0 && gy % 100 !== 0) || (gy % 400 === 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
  let gm = 1;
  while (gm <= 12 && gd > months[gm]) {
    gd -= months[gm];
    gm++;
  }
  return { year: gy, month: gm, day: gd };
}

function isJalaliLeap(year) {
  const breaks = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];
  let jp = breaks[0];
  let jump = 0;
  let leapJ = -14;
  for (let i = 1; i < breaks.length; i++) {
    const jm = breaks[i];
    jump = jm - jp;
    if (year < jm) break;
    leapJ += div(jump, 33) * 8 + div((jump % 33), 4);
    jp = jm;
  }
  let n = year - jp;
  if (jump - n < 6) n = n - jump + div(jump + 4, 33) * 33;
  let leap = (((n + 1) % 33) - 1) % 4;
  if (leap === -1) leap = 4;
  return leap === 0;
}

function jalaliMonthLength(year, month) {
  if (month <= 6) return 31;
  if (month <= 11) return 30;
  return isJalaliLeap(year) ? 30 : 29;
}

function todayJalali() {
  const now = new Date();
  return gregorianToJalali(now.getFullYear(), now.getMonth() + 1, now.getDate());
}

function firstWeekdayOfJalaliMonth(year, month) {
  const g = jalaliToGregorian(year, month, 1);
  const date = new Date(g.year, g.month - 1, g.day);
  return (date.getDay() + 1) % 7;
}

function createDatepicker() {
  const picker = document.createElement('div');
  picker.className = 'jalali-datepicker';
  picker.hidden = true;
  document.body.appendChild(picker);

  let activeInput = null;
  let view = todayJalali();

  function close() {
    picker.hidden = true;
    activeInput = null;
  }

  function position() {
    if (!activeInput) return;
    const rect = activeInput.getBoundingClientRect();
    picker.style.top = window.scrollY + rect.bottom + 8 + 'px';
    picker.style.left = window.scrollX + rect.left + 'px';
  }

  function selectDay(day) {
    if (!activeInput) return;
    activeInput.value = formatJalali(view.year, view.month, day);
    activeInput.dispatchEvent(new Event('change', { bubbles: true }));
    close();
  }

  function render() {
    const selected = parseJalali(activeInput?.value);
    const today = todayJalali();
    const days = jalaliMonthLength(view.year, view.month);
    const offset = firstWeekdayOfJalaliMonth(view.year, view.month);
    const cells = [];
    for (let i = 0; i < offset; i++) cells.push('<span class="empty-day"></span>');
    for (let day = 1; day <= days; day++) {
      const isSelected = selected && selected.year === view.year && selected.month === view.month && selected.day === day;
      const isToday = today.year === view.year && today.month === view.month && today.day === day;
      cells.push('<button type="button" class="' + (isSelected ? 'selected ' : '') + (isToday ? 'today' : '') + '" data-day="' + day + '">' + day + '</button>');
    }

    picker.innerHTML =
      '<div class="dp-head">' +
      '<button type="button" data-nav="next">‹</button>' +
      '<strong>' + persianMonths[view.month - 1] + ' ' + view.year + '</strong>' +
      '<button type="button" data-nav="prev">›</button>' +
      '</div>' +
      '<div class="dp-weekdays">' + persianWeekdays.map(function (day) { return '<span>' + day + '</span>'; }).join('') + '</div>' +
      '<div class="dp-days">' + cells.join('') + '</div>' +
      '<div class="dp-actions"><button type="button" data-today>امروز</button><button type="button" data-clear>پاک کردن</button></div>';
  }

  picker.addEventListener('click', function (event) {
    event.stopPropagation();
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;
    const nav = target.getAttribute('data-nav');
    if (nav) {
      view.month += nav === 'next' ? 1 : -1;
      if (view.month > 12) {
        view.month = 1;
        view.year++;
      }
      if (view.month < 1) {
        view.month = 12;
        view.year--;
      }
      render();
      return;
    }
    if (target.hasAttribute('data-day')) {
      selectDay(Number(target.getAttribute('data-day')));
      return;
    }
    if (target.hasAttribute('data-today')) {
      view = todayJalali();
      selectDay(view.day);
      return;
    }
    if (target.hasAttribute('data-clear') && activeInput) {
      activeInput.value = '';
      activeInput.dispatchEvent(new Event('change', { bubbles: true }));
      close();
    }
  });

  document.addEventListener('focusin', function (event) {
    const input = event.target;
    if (!(input instanceof HTMLInputElement) || !input.classList.contains('date-input')) return;
    activeInput = input;
    view = parseJalali(input.value) || todayJalali();
    render();
    position();
    picker.hidden = false;
  });

  document.addEventListener('click', function (event) {
    const target = event.target;
    if (!(target instanceof Node) || target === activeInput || picker.contains(target)) return;
    close();
  });

  window.addEventListener('resize', position);
  window.addEventListener('scroll', position, true);
}

if (document.querySelector('.date-input')) {
  createDatepicker();
}

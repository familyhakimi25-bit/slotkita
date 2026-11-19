// Wizard tempahan: servis -> staf -> tarikh/masa -> butiran -> berjaya
(function () {
  'use strict';
  var D = JSON.parse(document.getElementById('shop-data').textContent);
  var HARI = ['Ahd', 'Isn', 'Sel', 'Rab', 'Kha', 'Jum', 'Sab'];
  var HARI_P = ['Ahad', 'Isnin', 'Selasa', 'Rabu', 'Khamis', 'Jumaat', 'Sabtu'];
  var BULAN = ['Jan', 'Feb', 'Mac', 'Apr', 'Mei', 'Jun', 'Jul', 'Ogo', 'Sep', 'Okt', 'Nov', 'Dis'];
  var singleStaff = D.staff.length === 1;
  var order = singleStaff ? [1, 3, 4] : [1, 2, 3, 4];

  var st = { step: 1, services: [], staff: singleStaff ? D.staff[0].id : 0, date: null, time: null, busy: false };
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  var btnNext = $('#btn-next'), btnBack = $('#btn-back'), bar = $('#bar');

  function rm(sen) { return 'RM' + (sen % 100 === 0 ? (sen / 100).toFixed(0) : (sen / 100).toFixed(2)); }
  function mins(m) { return m >= 60 ? Math.floor(m / 60) + ' jam' + (m % 60 ? ' ' + (m % 60) + ' min' : '') : m + ' min'; }
  function picked() { return D.services.filter(function (s) { return st.services.indexOf(s.id) !== -1; }); }
  function totals() {
    return picked().reduce(function (a, s) { a.min += s.min; a.sen += s.sen; return a; }, { min: 0, sen: 0 });
  }
  function parseD(d) { var p = d.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
  function dateLabel(d) { var x = parseD(d); return HARI_P[x.getDay()] + ', ' + x.getDate() + ' ' + BULAN[x.getMonth()]; }
  function timeLabel(t) {
    var h = +t.slice(0, 2), m = t.slice(3);
    var suf = h < 12 ? 'PG' : (h < 19 ? 'PTG' : 'MLM');
    return ((h % 12) || 12) + ':' + m + ' ' + suf;
  }
  function staffName(id) {
    if (!id) return 'Sesiapa';
    var s = D.staff.filter(function (x) { return x.id === id; })[0];
    return s ? s.name : 'Sesiapa';
  }
  function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

  /* ---------- Paparan langkah ---------- */
  function show(step) {
    st.step = step;
    $$('.step').forEach(function (el) { el.classList.toggle('on', +el.dataset.step === step); });
    var idx = order.indexOf(step);
    $$('.stepper li').forEach(function (li) {
      var i = order.indexOf(+li.dataset.s);
      li.classList.toggle('on', i === idx);
      li.classList.toggle('done', step === 5 || (i !== -1 && i < idx));
    });
    btnBack.hidden = idx <= 0;
    bar.classList.toggle('hide', step === 5);
    btnNext.innerHTML = step === 4 ? 'Sahkan tempahan' : 'Seterusnya <svg class="ic-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>';
    if (step === 3) { renderDates(); }
    if (step === 4) { renderSummary(); }
    refresh();
    var book = $('#tempah');
    if (book.getBoundingClientRect().top < 0) { book.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
  }

  function refresh() {
    var t = totals();
    $('#bar-total').textContent = st.services.length ? rm(t.sen) : 'Pilih servis';
    var meta = [];
    if (st.services.length) meta.push(st.services.length + ' servis · ' + mins(t.min));
    if (st.date && st.step >= 3) meta.push(dateLabel(st.date) + (st.time ? ', ' + timeLabel(st.time) : ''));
    $('#bar-meta').textContent = meta.join(' · ');
    var ok = { 1: st.services.length > 0, 2: true, 3: !!(st.date && st.time), 4: !st.busy }[st.step];
    btnNext.disabled = !ok;
  }

  /* ---------- Langkah 1 ---------- */
  $$('.svc input').forEach(function (cb) {
    cb.addEventListener('change', function () {
      var id = +cb.value;
      if (cb.checked) { st.services.push(id); } else { st.services = st.services.filter(function (x) { return x !== id; }); }
      st.time = null; // tempoh berubah -> slot berubah
      refresh();
    });
  });

  /* ---------- Langkah 2 ---------- */
  $$('.staff').forEach(function (b) {
    b.addEventListener('click', function () {
      st.staff = +b.dataset.id;
      st.time = null;
      $$('.staff').forEach(function (x) { x.classList.toggle('on', x === b); x.setAttribute('aria-pressed', x === b); });
      refresh();
    });
  });

  /* ---------- Langkah 3 ---------- */
  function staffOff(dateStr) {
    if (!st.staff) return false;
    var s = D.staff.filter(function (x) { return x.id === st.staff; })[0];
    if (!s || !s.off) return false;
    return s.off.split(',').indexOf(String(parseD(dateStr).getDay())) !== -1;
  }

  function renderDates() {
    var strip = $('.date-strip');
    strip.innerHTML = '';
    var first = null;
    D.days.forEach(function (d) {
      var x = parseD(d.d);
      var off = !d.open || staffOff(d.d);
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'date-chip';
      b.disabled = off;
      b.setAttribute('role', 'option');
      b.dataset.d = d.d;
      b.title = off ? (d.note || 'Tidak bekerja') : dateLabel(d.d);
      b.innerHTML = '<small>' + HARI[x.getDay()] + '</small><b>' + x.getDate() + '</b><small>' + BULAN[x.getMonth()] + '</small>';
      b.addEventListener('click', function () { pickDate(d.d); });
      strip.appendChild(b);
      if (!off && !first) first = d.d;
    });
    var keep = st.date && D.days.some(function (d) { return d.d === st.date && d.open && !staffOff(d.d); });
    pickDate(keep ? st.date : first);
  }

  var slotReq = 0;
  function pickDate(d) {
    st.date = d;
    $$('.date-chip').forEach(function (b) {
      var on = b.dataset.d === d;
      b.classList.toggle('on', on);
      b.setAttribute('aria-selected', on);
      if (on) b.scrollIntoView({ block: 'nearest', inline: 'center' });
    });
    var box = $('.slots');
    if (!d) { box.innerHTML = '<p class="empty-note">Tiada hari terbuka untuk ditempah.</p>'; refresh(); return; }
    box.innerHTML = '<div class="slot-grid" style="margin-top:1rem">' + new Array(8).join('<div class="skeleton"></div>') + '</div>';
    var my = ++slotReq;
    var q = 'slot.php?k=' + encodeURIComponent(D.slug) + '&d=' + d + '&s=' + st.services.join(',') + '&st=' + st.staff;
    fetch(D.api + q, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (my !== slotReq) return;
        renderSlots(res.slots || []);
      })
      .catch(function () {
        if (my !== slotReq) return;
        box.innerHTML = '<p class="empty-note">Tidak dapat muat slot. Semak internet dan cuba lagi.</p>';
      });
    refresh();
  }

  function renderSlots(list) {
    var box = $('.slots');
    if (st.time && list.indexOf(st.time) === -1) st.time = null;
    if (!list.length) {
      box.innerHTML = '<p class="empty-note">Tiada slot kosong pada hari ini. Cuba hari lain' + (st.staff ? ' atau pilih "Sesiapa"' : '') + '.</p>';
      refresh();
      return;
    }
    var groups = [['Pagi', function (h) { return h < 12; }], ['Tengah hari', function (h) { return h >= 12 && h < 17; }], ['Petang & malam', function (h) { return h >= 17; }]];
    box.innerHTML = '';
    groups.forEach(function (g) {
      var items = list.filter(function (t) { return g[1](+t.slice(0, 2)); });
      if (!items.length) return;
      var sec = document.createElement('div');
      sec.className = 'slot-group';
      sec.innerHTML = '<h4>' + g[0] + '</h4>';
      var grid = document.createElement('div');
      grid.className = 'slot-grid';
      items.forEach(function (t) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'slot' + (t === st.time ? ' on' : '');
        b.textContent = timeLabel(t);
        b.addEventListener('click', function () {
          st.time = t;
          $$('.slot').forEach(function (x) { x.classList.toggle('on', x === b); });
          refresh();
        });
        grid.appendChild(b);
      });
      sec.appendChild(grid);
      box.appendChild(sec);
    });
    refresh();
  }

  /* ---------- Langkah 4 ---------- */
  function renderSummary() {
    var t = totals();
    var rows = picked().map(function (s) { return '<div class="row"><span>' + esc(s.name) + '</span><b>' + rm(s.sen) + '</b></div>'; }).join('');
    rows += '<div class="row"><span>Tarikh</span><b>' + dateLabel(st.date) + '</b></div>';
    rows += '<div class="row"><span>Masa</span><b>' + timeLabel(st.time) + ' (' + mins(t.min) + ')</b></div>';
    if (!singleStaff) rows += '<div class="row"><span>Staf</span><b>' + esc(staffName(st.staff)) + '</b></div>';
    rows += '<div class="row total"><span>Jumlah (bayar di kedai)</span><b>' + rm(t.sen) + '</b></div>';
    $('#summary').innerHTML = rows;
    // isi semula nama & telefon pelanggan tetap (disimpan dalam pelayar ini sahaja)
    try {
      var saved = JSON.parse(localStorage.getItem('sk_me') || 'null');
      if (saved && !$('#f-name').value) { $('#f-name').value = saved.n || ''; $('#f-phone').value = saved.p || ''; }
    } catch (e) { /* storan disekat - abaikan */ }
  }

  function submit() {
    var form = $('#book-form');
    var err = $('.form-err', form);
    var name = $('#f-name').value.trim(), phone = $('#f-phone').value.trim();
    err.hidden = true;
    if (name.length < 2) { return fail('Sila isi nama anda.', '#f-name'); }
    if (phone.replace(/\D/g, '').length < 9) { return fail('Sila isi nombor telefon yang betul.', '#f-phone'); }
    st.busy = true;
    btnNext.textContent = 'Menghantar…';
    refresh();
    fetch(D.api + 'tempah.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': D.csrf },
      body: JSON.stringify({
        k: D.slug, services: st.services, staff: st.staff, date: st.date, time: st.time,
        name: name, phone: phone, note: $('#f-note').value, website: form.website.value
      })
    })
      .then(function (r) { return r.json().then(function (j) { return { code: r.status, j: j }; }); })
      .then(function (res) {
        st.busy = false;
        if (res.j.ok) {
          try { localStorage.setItem('sk_me', JSON.stringify({ n: name, p: phone })); } catch (e) { /* abaikan */ }
          return success(res.j.booking);
        }
        if (res.code === 409) {
          // slot diambil orang lain - kembali pilih masa
          st.time = null;
          show(3);
          var box = $('.slots');
          var n = document.createElement('div');
          n.className = 'alert alert-warn';
          n.style.marginTop = '1rem';
          n.textContent = res.j.error;
          box.parentNode.insertBefore(n, box);
          setTimeout(function () { n.remove(); }, 6000);
          return;
        }
        fail(res.j.error || 'Tempahan gagal. Cuba lagi.');
        show(4);
      })
      .catch(function () { st.busy = false; fail('Tiada sambungan internet. Cuba lagi.'); show(4); });

    function fail(msg, focus) {
      err.textContent = msg;
      err.hidden = false;
      if (focus) $(focus).focus();
      refresh();
    }
  }

  function success(b) {
    $('#d-code').textContent = b.code;
    $('.done-status').textContent = b.status === 'disahkan' ? 'Slot anda sudah disahkan. Jumpa nanti!' : 'Kedai akan sahkan tempahan anda sebentar lagi.';
    var dl = [['Servis', b.services], ['Tarikh', b.date], ['Masa', b.time]];
    if (!singleStaff) dl.push(['Staf', b.staff]);
    dl.push(['Jumlah', b.total]);
    $('#d-list').innerHTML = dl.map(function (r) { return '<dt>' + r[0] + '</dt><dd>' + esc(r[1]) + '</dd>'; }).join('');
    $('#d-gcal').href = b.gcal;
    if ($('#d-wa')) $('#d-wa').href = b.wa;
    show(5);
  }

  $('#d-again').addEventListener('click', function () {
    st.services = []; st.time = null; st.date = null;
    $$('.svc input').forEach(function (c) { c.checked = false; });
    $('#f-note').value = '';
    show(1);
  });

  /* ---------- Navigasi ---------- */
  btnNext.addEventListener('click', function () {
    if (btnNext.disabled) return;
    if (st.step === 4) return submit();
    show(order[order.indexOf(st.step) + 1]);
  });
  btnBack.addEventListener('click', function () {
    var i = order.indexOf(st.step);
    if (i > 0) show(order[i - 1]);
  });

  show(1);
})();

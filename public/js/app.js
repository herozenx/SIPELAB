/* SIPELAB: interaksi ringan tanpa framework. Tidak ada script inline (CSP script-src 'self'). */
(() => {
  'use strict';
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => Array.from(el.querySelectorAll(s));
  const rupiah = (n) => 'Rp' + Math.round(n).toLocaleString('id-ID');

  // Navigasi samping di layar kecil
  const nav = $('[data-nav]');
  const backdrop = $('[data-nav-backdrop]');
  const toggle = $('[data-nav-toggle]');
  const setNav = (open) => {
    if (!nav) return;
    nav.classList.toggle('hidden', !open);
    nav.classList.toggle('flex', open);
    backdrop?.classList.toggle('hidden', !open);
    toggle?.setAttribute('aria-expanded', String(open));
  };
  toggle?.addEventListener('click', () => setNav(nav.classList.contains('hidden')));
  backdrop?.addEventListener('click', () => setNav(false));
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && toggle?.getAttribute('aria-expanded') === 'true') setNav(false); });

  // Baris tabel bisa diklik (tautan asli tetap ada untuk keyboard & pembaca layar)
  document.addEventListener('click', (e) => {
    const row = e.target.closest('tr[data-href]');
    if (!row || e.target.closest('a, button, input, select, textarea, label')) return;
    if (e.metaKey || e.ctrlKey) window.open(row.dataset.href, '_blank');
    else window.location.href = row.dataset.href;
  });

  // Filter yang langsung terkirim saat diganti
  $$('[data-autosubmit]').forEach((el) => el.addEventListener('change', () => el.form?.submit()));

  // Tampilkan/sembunyikan kata sandi
  $$('[data-toggle-password]').forEach((btn) => btn.addEventListener('click', () => {
    const input = document.getElementById(btn.dataset.togglePassword);
    if (!input) return;
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.setAttribute('aria-label', show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
  }));

  // Bagian form yang muncul sesuai pilihan radio (mis. valid / tolak)
  $$('form').forEach((form) => {
    const radios = $$('input[type=radio][data-reveal]', form);
    if (!radios.length) return;
    const sync = () => {
      const val = radios.find((r) => r.checked)?.dataset.reveal;
      $$('[data-reveal-target]', form).forEach((el) => {
        const on = el.dataset.revealTarget === val;
        el.hidden = !on;
        $$('input, textarea, select', el).forEach((f) => { f.disabled = !on; });
      });
    };
    radios.forEach((r) => r.addEventListener('change', sync));
    sync();
  });

  // Field yang muncul sesuai pilihan select (mis. lab untuk petugas)
  $$('[data-reveal-select]').forEach((sel) => {
    const form = sel.form;
    const sync = () => $$('[data-reveal-when]', form).forEach((el) => {
      const on = el.dataset.revealWhen === sel.value;
      el.hidden = !on;
      $$('input, select', el).forEach((f) => { f.disabled = !on; });
    });
    sel.addEventListener('change', sync);
    sync();
  });

  // Cek berkas sebelum diunggah: jumlah & ukuran
  $$('input[type=file][data-max-size]').forEach((input) => input.addEventListener('change', () => {
    const maxFiles = Number(input.dataset.maxFiles || 1);
    const maxSize = Number(input.dataset.maxSize);
    let msg = '';
    if (input.files.length > maxFiles) msg = `Pilih paling banyak ${maxFiles} berkas.`;
    else if (Array.from(input.files).some((f) => f.size > maxSize)) msg = 'Ada berkas yang lebih dari 5 MB.';
    else if (Array.from(input.files).some((f) => !/\.(pdf|jpe?g|png)$/i.test(f.name))) msg = 'Format berkas harus PDF, JPG, atau PNG.';
    input.setCustomValidity(msg);
    if (msg) { input.reportValidity(); input.value = ''; input.setCustomValidity(''); }
  }));

  // Perkiraan tagihan di form pengajuan
  const est = $('[data-estimasi]');
  const jumlah = $('[data-jumlah]');
  if (est && jumlah) {
    const per = Number(est.dataset.perSampel);
    const adm = Number(est.dataset.admin);
    const upd = () => {
      const n = Math.max(1, Math.min(500, parseInt(jumlah.value, 10) || 1));
      $('[data-estimasi-jumlah]', est).textContent = n;
      $('[data-estimasi-subtotal]', est).textContent = rupiah(per * n);
      $('[data-estimasi-total]', est).textContent = rupiah(per * n + adm);
    };
    jumlah.addEventListener('input', upd);
    upd();
  }

  // Konfirmasi tindakan penting + cegah kirim ganda
  let dirty = false;
  $$('form[data-guard]').forEach((form) => {
    form.addEventListener('input', () => { dirty = true; });
    form.addEventListener('submit', (e) => {
      const btn = e.submitter;
      if (btn?.dataset.confirm && !window.confirm(btn.dataset.confirm)) { e.preventDefault(); return; }
      dirty = false;
      $$('button', form).forEach((b) => { b.disabled = true; });
      if (btn) btn.setAttribute('aria-busy', 'true');
    });
  });
  $$('button[data-confirm]').forEach((btn) => {
    const form = btn.form;
    if (!form || form.hasAttribute('data-guard')) return;
    form.addEventListener('submit', (e) => {
      if (e.submitter === btn && !window.confirm(btn.dataset.confirm)) e.preventDefault();
    });
  });

  // Jalur uji di layar sempit bisa digeser: langsung tampilkan langkah yang sedang berjalan.
  $$('.jalur').forEach((j) => {
    const now = $('[aria-current="step"]', j);
    const box = j.closest('.overflow-x-auto');
    if (now && box && box.scrollWidth > box.clientWidth) {
      box.scrollLeft = Math.max(0, now.offsetLeft - box.clientWidth / 2 + now.offsetWidth / 2);
    }
  });

  // Cetak LHU
  $('[data-print]')?.addEventListener('click', () => window.print());

  // Pembaruan real-time: cek sidik versi data berkala; muat ulang bila berubah.
  // Tidak memuat ulang paksa saat pengguna sedang mengisi form.
  const body = document.body;
  const url = body.dataset.pollUrl;
  if (url) {
    const id = body.dataset.pollId;
    const every = Math.max(5, Number(body.dataset.pollSeconds || 8)) * 1000;
    const bar = $('[data-refresh-bar]');
    let base = null;
    let timer = null;
    const check = async () => {
      if (document.hidden) return;
      try {
        const res = await fetch(url + (id ? '?p=' + encodeURIComponent(id) : ''), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (res.status === 401 || res.redirected) { clearInterval(timer); return; }
        if (!res.ok) return;
        const { v } = await res.json();
        if (base === null) { base = v; return; }
        if (v !== base) {
          const typing = dirty || (document.activeElement && /INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName) && document.activeElement.closest('form'));
          if (typing) bar?.classList.remove('hidden');
          else window.location.reload();
        }
      } catch (_) { /* jaringan putus sementara: coba lagi di putaran berikutnya */ }
    };
    check();
    timer = setInterval(check, every);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) check(); });
  }
  $('[data-refresh-now]')?.addEventListener('click', () => window.location.reload());

  // Panduan: tampilkan satu bagian per halaman dengan tombol ← → (gaya buku panduan).
  // Tanpa JavaScript semua bagian tetap tampil berurutan; saat dicetak/disimpan PDF semua bagian ikut.
  const panduan = $('[data-panduan]');
  if (panduan) {
    const bab = $$('[data-bab]', panduan);
    const daftar = $$('[data-bab-link]');
    const n = bab.length;
    let aktif = 0;
    const tampilBab = (k, gulir) => {
      aktif = Math.max(0, Math.min(n - 1, k));
      bab.forEach((b, j) => b.classList.toggle('hidden', j !== aktif));
      daftar.forEach((a, j) => { if (j === aktif) a.setAttribute('aria-current', 'true'); else a.removeAttribute('aria-current'); });
      $$('[data-bab-pos]').forEach((e) => { e.textContent = (aktif + 1) + ' / ' + n; });
      $$('[data-bab-judul]').forEach((e) => { e.textContent = bab[aktif].dataset.judul; });
      $$('[data-bab-prev]').forEach((b) => { b.disabled = aktif === 0; });
      $$('[data-bab-next]').forEach((b) => { b.disabled = aktif === n - 1; });
      if (history.replaceState) history.replaceState(null, '', '#' + bab[aktif].id);
      if (gulir) panduan.scrollIntoView({ block: 'start', behavior: 'smooth' });
    };
    const dariHash = () => Math.max(0, bab.findIndex((b) => '#' + b.id === window.location.hash));
    const bar = $('[data-bab-bar]', panduan);
    bar?.classList.replace('hidden', 'flex');
    daftar.forEach((a, j) => a.addEventListener('click', (e) => { e.preventDefault(); tampilBab(j, true); }));
    $$('[data-bab-ke]', panduan).forEach((a) => a.addEventListener('click', (e) => { e.preventDefault(); tampilBab(Number(a.dataset.babKe), true); }));
    $$('[data-bab-prev]').forEach((b) => b.addEventListener('click', () => tampilBab(aktif - 1, false)));
    $$('[data-bab-next]').forEach((b) => b.addEventListener('click', () => tampilBab(aktif + 1, false)));
    document.addEventListener('keydown', (e) => {
      if (e.target.closest('input, textarea, select, [contenteditable]') || e.altKey || e.ctrlKey || e.metaKey) return;
      if (e.key === 'ArrowRight') tampilBab(aktif + 1, false);
      if (e.key === 'ArrowLeft') tampilBab(aktif - 1, false);
    });
    window.addEventListener('hashchange', () => tampilBab(dariHash(), false));
    tampilBab(dariHash(), false);
  }

  // Kotak yang bisa ditutup (mis. ajakan membaca panduan); diingat di browser ini saja.
  $$('[data-dismissable]').forEach((box) => {
    const key = 'sipelab.tutup.' + box.dataset.dismissable;
    try { if (localStorage.getItem(key)) { box.remove(); return; } } catch (_) { /* penyimpanan diblokir */ }
    $('[data-dismiss]', box)?.addEventListener('click', () => {
      try { localStorage.setItem(key, '1'); } catch (_) { /* abaikan */ }
      box.remove();
    });
  });

  // Lencana chat belum dibaca + penanda online (permintaan ini juga memperbarui "terakhir aktif").
  const statusUrl = body.dataset.chatStatusUrl;
  if (statusUrl) {
    let belumAwal = null;
    const cekStatus = async () => {
      if (document.hidden) return;
      try {
        const res = await fetch(statusUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!res.ok || res.redirected) return;
        const { belum } = await res.json();
        $$('[data-chat-badge]').forEach((b) => { b.textContent = belum; b.classList.toggle('hidden', !belum); });
        // Di kotak masuk petugas: ada pesan baru di percakapan lain => tawarkan muat ulang daftar.
        if (belumAwal !== null && belum > belumAwal && $('[data-chat-inbox]')) {
          $('[data-refresh-bar]')?.classList.remove('hidden');
        }
        belumAwal = belum;
      } catch (_) { /* coba lagi nanti */ }
    };
    cekStatus();
    setInterval(cekStatus, 15000);
  }

  // Chat langsung: tarik pesan baru tiap 3 detik, kirim tanpa memuat ulang halaman.
  const chat = $('[data-chat]');
  if (chat) {
    const list = $('[data-chat-list]', chat);
    const form = $('[data-chat-form]', chat);
    const input = $('textarea', form);
    const tombol = $('[data-chat-kirim]', form);
    const galat = $('[data-chat-error]', form);
    const token = $('meta[name="csrf-token"]')?.content || '';
    const urlPesan = chat.dataset.urlPesan;
    const urlKirim = chat.dataset.urlKirim;
    let terakhir = 0;
    let sibuk = false;

    const dekatBawah = () => list.scrollHeight - list.scrollTop - list.clientHeight < 120;
    const keBawah = () => { list.scrollTop = list.scrollHeight; };
    const el = (tag, cls, teks) => { const n = document.createElement(tag); n.className = cls; if (teks !== undefined) n.textContent = teks; return n; };

    // Isi pesan selalu lewat textContent (bukan innerHTML) => aman dari XSS.
    const tampil = (m) => {
      if (list.querySelector('[data-id="' + m.id + '"]')) return;
      $('[data-chat-kosong]', list)?.remove();
      const baris = el('div', 'flex ' + (m.saya ? 'justify-end' : 'justify-start'));
      baris.dataset.id = m.id;
      const gelembung = el('div', 'max-w-[85%] rounded-2xl px-4 py-2.5 text-sm shadow-sm sm:max-w-[70%] ' + (m.saya
        ? 'rounded-br-md bg-laut-600 text-white'
        : (m.balai ? 'rounded-bl-md bg-white text-tinta ring-1 ring-lamun-100' : 'rounded-bl-md bg-white text-tinta ring-1 ring-kabut-200')));
      const meta = el('p', 'mb-0.5 text-2xs font-semibold ' + (m.saya ? 'text-laut-100' : (m.balai ? 'text-lamun-700' : 'text-kabut-500')),
        (m.saya ? 'Anda' : m.nama + ' · ' + m.jabatan) + ' · ' + m.waktu);
      gelembung.append(meta, el('p', 'whitespace-pre-line break-words', m.isi));
      baris.append(gelembung);
      list.append(baris);
    };

    const tampilOnline = (daftar) => {
      $$('[data-chat-online]').forEach((ul) => {
        ul.textContent = '';
        if (!daftar.length) {
          ul.append(el('li', 'text-sm text-kabut-500', ul.hasAttribute('data-chat-online-chip') ? 'Tidak ada.' : 'Belum ada petugas online. Pesan Anda tetap tersimpan dan akan dibalas.'));
          return;
        }
        daftar.forEach((o) => {
          if (ul.hasAttribute('data-chat-online-chip')) {
            const li = el('li', 'inline-flex items-center gap-2 rounded-full bg-kabut-100 py-1 pl-1 pr-3 text-xs');
            const teks = el('span', '');
            teks.append(el('span', 'font-semibold', o.nama), document.createTextNode(' · ' + o.jabatan));
            li.append(el('span', 'grid h-6 w-6 place-items-center rounded-full bg-laut-600 text-2xs font-bold text-white', o.inisial), teks);
            ul.append(li);
          } else {
            const li = el('li', 'flex items-center gap-3');
            const av = el('span', 'relative grid h-8 w-8 shrink-0 place-items-center rounded-full bg-laut-100 text-xs font-bold text-laut-700', o.inisial);
            av.append(el('span', 'absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full bg-lamun-500 ring-2 ring-white'));
            const t = el('span', 'min-w-0 leading-tight');
            t.append(el('span', 'block truncate text-sm font-semibold', o.nama), el('span', 'block truncate text-xs text-kabut-500', o.jabatan));
            li.append(av, t);
            ul.append(li);
          }
        });
      });
    };

    const tarik = async () => {
      if (sibuk) return;
      sibuk = true;
      try {
        const res = await fetch(urlPesan + '?setelah=' + terakhir, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!res.ok || res.redirected) return;
        const d = await res.json();
        const awal = terakhir === 0;
        const tempel = awal || dekatBawah();
        d.pesan.forEach((m) => { tampil(m); terakhir = Math.max(terakhir, m.id); });
        if (awal && !d.pesan.length) {
          const kosong = $('[data-chat-kosong]', list);
          if (kosong) kosong.textContent = 'Belum ada pesan. Tulis pesan di bawah.';
        }
        if (tempel) keBawah();
        tampilOnline(d.online || []);
        const po = $('[data-chat-pengguna-online]');
        if (po && d.pengguna_online !== null && d.pengguna_online !== undefined) po.textContent = d.pengguna_online ? 'online' : 'offline';
      } catch (_) { /* jaringan putus sementara */ } finally { sibuk = false; }
    };

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const isi = input.value.trim();
      if (!isi) return;
      tombol.disabled = true;
      galat.classList.add('hidden');
      try {
        const res = await fetch(urlKirim, {
          method: 'POST', credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
          body: JSON.stringify({ isi }),
        });
        if (res.ok) {
          const { pesan } = await res.json();
          tampil(pesan); // "terakhir" tidak dimajukan di sini agar pesan orang lain yang lebih dulu tidak terlewat
          input.value = '';
          keBawah();
        } else {
          let msg = 'Pesan gagal dikirim. Coba lagi.';
          if (res.status === 419) msg = 'Sesi berakhir. Muat ulang halaman lalu masuk kembali.';
          else if (res.status === 429) msg = 'Terlalu banyak pesan dalam waktu singkat. Tunggu sebentar.';
          else { try { msg = (await res.json()).message || msg; } catch (_) { /* biarkan */ } }
          galat.textContent = msg;
          galat.classList.remove('hidden');
        }
      } catch (_) {
        galat.textContent = 'Tidak terhubung ke server. Periksa jaringan Anda.';
        galat.classList.remove('hidden');
      } finally {
        tombol.disabled = false;
        input.focus();
      }
    });
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); form.requestSubmit(); }
    });

    tarik();
    setInterval(() => { if (!document.hidden) tarik(); }, 3000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) tarik(); });
  }
})();

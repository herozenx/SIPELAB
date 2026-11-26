/* SILANTRO: interaksi ringan tanpa framework. Tidak ada script inline (CSP script-src 'self'). */
(() => {
    "use strict";
    const $ = (s, el = document) => el.querySelector(s);
    const $$ = (s, el = document) => Array.from(el.querySelectorAll(s));
    const rupiah = (n) => "Rp" + Math.round(n).toLocaleString("id-ID");

    // Navigasi samping di layar kecil
    const nav = $("[data-nav]");
    const backdrop = $("[data-nav-backdrop]");
    const toggle = $("[data-nav-toggle]");
    const setNav = (open) => {
        if (!nav) return;
        nav.classList.toggle("hidden", !open);
        nav.classList.toggle("flex", open);
        backdrop?.classList.toggle("hidden", !open);
        toggle?.setAttribute("aria-expanded", String(open));
    };
    toggle?.addEventListener("click", () =>
        setNav(nav.classList.contains("hidden")),
    );
    backdrop?.addEventListener("click", () => setNav(false));
    document.addEventListener("keydown", (e) => {
        if (
            e.key === "Escape" &&
            toggle?.getAttribute("aria-expanded") === "true"
        )
            setNav(false);
    });

    // Menu atas halaman publik di HP
    const menu = $("[data-menu]");
    const menuBtn = $("[data-menu-toggle]");
    const setMenu = (open) => {
        if (!menu || !menuBtn) return;
        menu.classList.toggle("is-open", open);
        menu.setAttribute("aria-hidden", String(!open));
        menuBtn.setAttribute("aria-expanded", String(open));
        menuBtn.setAttribute("aria-label", open ? "Tutup menu" : "Buka menu");
        $("[data-menu-open]", menuBtn)?.classList.toggle("hidden", open);
        $("[data-menu-close]", menuBtn)?.classList.toggle("hidden", !open);
        if (open) menu.querySelector("a")?.focus();
        else menuBtn.focus();
    };
    menuBtn?.addEventListener("click", () =>
        setMenu(!menu.classList.contains("is-open")),
    );
    if (menu)
        $$("a", menu).forEach((a) =>
            a.addEventListener("click", () => setMenu(false)),
        );
    document.addEventListener("keydown", (e) => {
        if (
            e.key === "Escape" &&
            menuBtn?.getAttribute("aria-expanded") === "true"
        )
            setMenu(false);
    });

    // Baris tabel bisa diklik (tautan asli tetap ada untuk keyboard & pembaca layar)
    document.addEventListener("click", (e) => {
        const row = e.target.closest("tr[data-href]");
        if (
            !row ||
            e.target.closest("a, button, input, select, textarea, label")
        )
            return;
        if (e.metaKey || e.ctrlKey) window.open(row.dataset.href, "_blank");
        else window.location.href = row.dataset.href;
    });

    // Filter yang langsung terkirim saat diganti
    $$("[data-autosubmit]").forEach((el) =>
        el.addEventListener("change", () => el.form?.submit()),
    );

  // Tampilkan/sembunyikan kata sandi (delegasi agar berlaku untuk kartu operator dinamis)
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-toggle-password]');
    if (!btn) return;
    const input = document.getElementById(btn.dataset.togglePassword);
    if (!input) return;
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.setAttribute('aria-label', show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
    btn.querySelector('[data-eye-open]')?.classList.toggle('hidden', show);
    btn.querySelector('[data-eye-closed]')?.classList.toggle('hidden', !show);
  });

    // Validasi autentikasi (daftar & masuk) dimulai setelah pengguna mencoba mengirim formulir.
    $$("[data-register-form], [data-login-form]").forEach((authForm) => {
        const isLogin = authForm.hasAttribute("data-login-form");
        let submitted = false;
        const clearError = (field) => {
            field.classList.remove("is-invalid");
            field.removeAttribute("aria-invalid");
            field
                .closest("[data-field]")
                ?.querySelector(`[data-error-icon="${field.id}"]`)
                ?.classList.add("hidden");
            field
                .closest("[data-field]")
                ?.querySelector(`[data-field-error="${field.id}"]`)
                ?.classList.add("hidden");
            field
                .closest("[data-field]")
                ?.querySelector(`[data-field-error="${field.id}"]`)
                ?.replaceChildren();
            field
                .closest("[data-phone-box]")
                ?.classList.remove("phone-invalid");
        };

        const setError = (field, message) => {
            field.classList.add("is-invalid");
            field.setAttribute("aria-invalid", "true");
            field
                .closest("[data-field]")
                ?.querySelector(`[data-error-icon="${field.id}"]`)
                ?.classList.remove("hidden");
            field.closest("[data-phone-box]")?.classList.add("phone-invalid");
            const error = field
                .closest("[data-field]")
                ?.querySelector(`[data-field-error="${field.id}"]`);
            if (error) {
                error.textContent = message;
                error.classList.remove("hidden");
                error.setAttribute("role", "alert");
                field.setAttribute(
                    "aria-describedby",
                    error.id || `${field.id}-error`,
                );
                if (!error.id) error.id = `${field.id}-error`;
            }
        };

    const openOperatorCard = (field) => {
      const blok = field.closest('[data-operator-item]');
      if (!blok) return;
      const btn = $('[data-operator-toggle]', blok);
      const detail = $('[data-operator-detail]', blok);
      if (!btn || !detail) return;
      detail.classList.remove('is-closed');
      btn.setAttribute('aria-expanded', 'true');
      $('[data-operator-chevron]', blok)?.classList.add('rotate-180');
      const sub = $('[data-operator-subtitle]', blok);
      if (sub) {
        sub.textContent = 'Isi data akses masuk operator';
        sub.classList.remove('text-karang-700', 'font-medium');
        sub.classList.add('text-kabut-600');
      }
      // Paksa hitung ulang style agar elemen langsung bisa difokuskan setelah dibuka.
      void detail.offsetHeight;
    };

        const validateField = (field) => {
            clearError(field);
            const value = field.value.trim();
            let message = "";

      if (field.required && value === '') {
        message = 'Wajib diisi.';
      } else if (field.id === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
        message = 'Masukkan format email yang benar.';
      } else if (!isLogin) {
        if ((field.id === 'nama' || field.name.endsWith('[nama]')) && value.length < 3) {
          message = 'Minimal 3 karakter.';
        } else if (field.id === 'instansi' && value.length < 2) {
          message = 'Minimal 2 karakter.';
        } else if (field.id === 'telepon') {
          const digits = value.replace(/\D/g, '');
          if (digits.length < 8) message = 'Masukkan nomor telepon yang lengkap.';
        } else if (field.name.endsWith('[telepon]') && value !== '' && !/^[0-9+\-\s]{8,20}$/.test(value)) {
          message = 'Nomor HP 8 sampai 20 digit.';
        } else if (field.id === 'password') {
          if (value.length < 8) message = 'Minimal 8 karakter.';
          else if (!/[A-Z]/.test(value)) message = 'Gunakan sedikitnya satu huruf besar.';
          else if (!/[a-z]/.test(value)) message = 'Gunakan sedikitnya satu huruf kecil.';
          else if (!/[0-9]/.test(value)) message = 'Gunakan sedikitnya satu angka.';
        } else if (field.id === 'password_confirmation') {
          const password = authForm.querySelector('#password')?.value || '';
          if (value !== password) message = 'Ulangi kata sandi dengan tepat.';
        } else if (field.id === 'alamat' && value.length < 5) {
          message = 'Tulis alamat lengkap.';
        } else if (field.name.endsWith('[nik]') && !/^\d{16}$/.test(value)) {
          message = 'NIK terdiri dari 16 angka.';
        } else if (field.name.endsWith('[email]')) {
          if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
            message = 'Masukkan format email yang benar.';
          } else {
            const lower = value.toLowerCase();
            const adminEmail = (authForm.querySelector('#email')?.value || '').trim().toLowerCase();
            const otherOps = $$('[data-operator-email]', authForm).filter((el) => el !== field && el.value.trim().toLowerCase() === lower);
            if ((adminEmail && lower === adminEmail) || otherOps.length) {
              message = 'Email harus berbeda dari akun admin dan operator lain.';
            }
          }
        } else if (field.name.endsWith('[password]') && (value.length < 8 || !/[A-Z]/.test(value) || !/[a-z]/.test(value) || !/[0-9]/.test(value))) {
          message = 'Minimal 8 karakter dengan huruf besar, huruf kecil, dan angka.';
        }
      }

            if (message) setError(field, message);
            return message === "";
        };

        // Dibaca ulang setiap kali: blok operator bisa ditambah/dihapus.
        const fields = () =>
            Array.from(
                authForm.querySelectorAll('input[name]:not([type="hidden"])'),
            );
        authForm.addEventListener("focusout", (e) => {
            if (submitted && e.target.matches("input[name]"))
                validateField(e.target);
        });
        authForm.addEventListener("input", (e) => {
            const field = e.target;
            if (!field.matches("input[name]")) return;
            if (submitted && field.getAttribute("aria-invalid") === "true")
                validateField(field);
            if (
                !isLogin &&
                submitted &&
                field.id === "password" &&
                authForm
                    .querySelector("#password_confirmation")
                    ?.getAttribute("aria-invalid") === "true"
            ) {
                validateField(authForm.querySelector("#password_confirmation"));
            }
        });

        authForm.addEventListener("submit", (event) => {
            submitted = true;
            let firstInvalid = null;
            fields().forEach((field) => {
                if (!validateField(field)) {
                    openOperatorCard(field);
                    if (!firstInvalid) firstInvalid = field;
                }
            });

            if (firstInvalid) {
                event.preventDefault();
                firstInvalid.focus({ preventScroll: true });
                firstInvalid.scrollIntoView({
                    block: "center",
                    behavior: window.matchMedia(
                        "(prefers-reduced-motion: reduce)",
                    ).matches
                        ? "auto"
                        : "smooth",
                });
                return;
            }

            const button = authForm.querySelector("[data-submit-button]");
            const label = authForm.querySelector("[data-submit-label]");
            if (button) {
                button.disabled = true;
                button.setAttribute("aria-busy", "true");
                button.classList.add("cursor-not-allowed", "opacity-70");
            }
            if (label) {
                label.textContent =
                    label.dataset.loadingLabel || "Membuat akun...";
            }
        });

        requestAnimationFrame(() => {
            const serverFields = Array.from(
                authForm.querySelectorAll("[data-server-error]"),
            );
            serverFields.forEach((serverField) => {
                openOperatorCard(serverField);
                setError(
                    serverField,
                    serverField.dataset.serverError || "Periksa isian ini.",
                );
            });
            if (serverFields.length) {
                serverFields[0].focus({ preventScroll: true });
                serverFields[0].scrollIntoView({
                    block: "center",
                    behavior: window.matchMedia(
                        "(prefers-reduced-motion: reduce)",
                    ).matches
                        ? "auto"
                        : "smooth",
                });
            }
        });
    });

    // Snackbar sementara (mis. notifikasi setelah keluar) dengan jeda saat disentuh/difokus.
    $$("[data-snackbar]").forEach((bar) => {
        const duration = Math.max(
            2000,
            Number(bar.dataset.snackbarDuration || 4500),
        );
        const reduced = window.matchMedia(
            "(prefers-reduced-motion: reduce)",
        ).matches;
        let timer = null;

        const close = () => {
            clearTimeout(timer);
            bar.classList.add("opacity-0", "-translate-y-2");
            window.setTimeout(() => bar.remove(), reduced ? 0 : 300);
        };
        const startTimer = () => {
            clearTimeout(timer);
            timer = window.setTimeout(close, duration);
        };

        requestAnimationFrame(() =>
            bar.classList.remove("opacity-0", "-translate-y-2"),
        );
        startTimer();

        bar.addEventListener("mouseenter", () => clearTimeout(timer));
        bar.addEventListener("mouseleave", startTimer);
        bar.addEventListener("focusin", () => clearTimeout(timer));
        bar.addEventListener("focusout", startTimer);
        $("[data-snackbar-close]", bar)?.addEventListener("click", close);
        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape" && bar.isConnected) close();
        });
    });

  // Kartu operator perusahaan: tambah, buka/tutup accordion, dan hapus dengan konfirmasi bila sudah terisi.
  $$("[data-ulang-daftar]").forEach((daftar) => {
    const nama = daftar.dataset.ulangDaftar;
    const tpl = $(`[data-ulang-template="${nama}"]`);
    const tambahBtns = $$(`[data-ulang-tambah="${nama}"]`);
    const section = daftar.closest("[data-operator-section]");
    const emptyBox = section ? $("[data-operator-empty]", section) : null;
    const footerBox = section ? $("[data-operator-footer]", section) : null;
    const counterEl = section ? $("[data-operator-counter]", section) : null;
    const limitEl = section ? $("[data-operator-limit]", section) : null;
    const modal = $("[data-operator-modal]");
    const modalName = modal ? $("[data-operator-modal-name]", modal) : null;
    const modalCancels = modal ? $$("[data-operator-modal-cancel]", modal) : [];
    const modalConfirm = modal ? $("[data-operator-modal-confirm]", modal) : null;
    const maks = Number(daftar.dataset.maks || 10);
    const reduced = () => window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    let berikut = Number(daftar.dataset.berikut) || 0;
    let pendingDelete = null;

    const syncSummary = (blok, index) => {
      const titleEl = $("[data-operator-title]", blok);
      const subEl = $("[data-operator-subtitle]", blok);
      const toggleBtn = $("[data-operator-toggle]", blok);
      const emailVal = ($("[data-operator-email]", blok)?.value || "").trim();
      const isOpen = toggleBtn?.getAttribute("aria-expanded") === "true";
      const hasErr = Boolean(blok.querySelector('[aria-invalid="true"]'));

      if (titleEl) titleEl.textContent = `Operator ${index + 1}`;
      if (subEl) {
        subEl.classList.toggle("text-karang-700", !isOpen && hasErr);
        subEl.classList.toggle("font-medium", !isOpen && hasErr);
        subEl.classList.toggle("text-kabut-600", isOpen || !hasErr);
        if (isOpen) subEl.textContent = "Isi data akses masuk operator";
        else if (hasErr) subEl.textContent = "Periksa isian yang ditandai";
        else subEl.textContent = emailVal || "Ketuk untuk melengkapi data";
      }
    };

    const setOpen = (blok, open) => {
      const toggleBtn = $("[data-operator-toggle]", blok);
      const detail = $("[data-operator-detail]", blok);
      const chevron = $("[data-operator-chevron]", blok);
      if (!toggleBtn || !detail) return;
      detail.classList.toggle("is-closed", !open);
      toggleBtn.setAttribute("aria-expanded", String(open));
      chevron?.classList.toggle("rotate-180", open);
      const idx = $$("[data-ulang]", daftar).indexOf(blok);
      syncSummary(blok, Math.max(0, idx));
    };

    const upd = () => {
      const blok = $$("[data-ulang]", daftar);
      const count = blok.length;
      blok.forEach((b, i) => {
        const no = $("[data-ulang-no]", b);
        if (no) no.textContent = i + 1;
        $("[data-ulang-hapus]", b)?.setAttribute("aria-label", `Hapus operator ${i + 1}`);
        syncSummary(b, i);
      });
      tambahBtns.forEach((btn) => { btn.disabled = count >= maks; });
      if (emptyBox) emptyBox.hidden = count > 0;
      daftar.hidden = count === 0;
      if (footerBox) footerBox.hidden = count === 0;
      if (counterEl) {
        counterEl.textContent = `${count}/${maks}`;
        counterEl.classList.toggle("hidden", count === 0);
      }
      if (limitEl) limitEl.classList.toggle("hidden", count < maks);
    };

    const removeCard = (blok, index) => {
      const finalize = () => {
        blok.remove();
        upd();
        const remaining = $$("[data-ulang]", daftar);
        if (remaining.length) $("[data-operator-toggle]", remaining[Math.min(index, remaining.length - 1)])?.focus();
        else $("[data-operator-empty] [data-ulang-tambah]")?.focus();
      };
      if (reduced()) { finalize(); return; }
      blok.classList.add("is-leaving");
      window.setTimeout(finalize, 180);
    };

    const closeModal = (restoreFocus = true) => {
      if (!modal) return;
      modal.classList.remove("is-open");
      modal.setAttribute("aria-hidden", "true");
      if (restoreFocus && pendingDelete?.trigger) pendingDelete.trigger.focus();
      pendingDelete = null;
    };

    const openModal = (blok, index, label, trigger) => {
      if (!modal) { removeCard(blok, index); return; }
      pendingDelete = { blok, index, trigger };
      if (modalName) modalName.textContent = label;
      modal.classList.add("is-open");
      modal.setAttribute("aria-hidden", "false");
      modalCancels[modalCancels.length - 1]?.focus();
    };

    tambahBtns.forEach((btn) => btn.addEventListener("click", () => {
      const current = $$("[data-ulang]", daftar);
      if (!tpl || current.length >= maks) return;
      // Tutup kartu sebelumnya yang tidak bermasalah agar fokus tetap di satu kartu aktif.
      current.forEach((b) => {
        if (!b.querySelector('[aria-invalid="true"]')) setOpen(b, false);
      });
      const wrap = document.createElement("div");
      wrap.innerHTML = tpl.innerHTML.replaceAll("__i__", String(berikut++));
      const blok = wrap.firstElementChild;
      if (!reduced()) blok.classList.add("is-entering");
      daftar.appendChild(blok);
      upd();
      setOpen(blok, true);
      requestAnimationFrame(() => blok.classList.remove("is-entering"));
      $("[data-operator-nama]", blok)?.focus();
    }));

    daftar.addEventListener("click", (e) => {
      const hapus = e.target.closest("[data-ulang-hapus]");
      if (hapus) {
        const blok = hapus.closest("[data-ulang]");
        const items = $$("[data-ulang]", daftar);
        const index = items.indexOf(blok);
        const hasValue = $$("input", blok).some((inp) => inp.value.trim() !== "");
        const label = `Operator ${index + 1}`;
        if (hasValue) openModal(blok, index, label, hapus);
        else removeCard(blok, index);
        return;
      }

      const toggleBtn = e.target.closest("[data-operator-toggle]");
      if (toggleBtn) {
        const blok = toggleBtn.closest("[data-ulang]");
        setOpen(blok, toggleBtn.getAttribute("aria-expanded") !== "true");
      }
    });

    daftar.addEventListener("input", (e) => {
      if (!e.target.matches("[data-operator-nama], [data-operator-email]")) return;
      const blok = e.target.closest("[data-ulang]");
      if (!blok) return;
      const idx = $$("[data-ulang]", daftar).indexOf(blok);
      syncSummary(blok, Math.max(0, idx));
    });

    modalCancels.forEach((btn) => btn.addEventListener("click", () => closeModal(true)));
    modal?.addEventListener("click", (e) => {
      if (e.target === modal) closeModal(true);
    });
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && modal?.classList.contains("is-open")) closeModal(true);
    });
    modalConfirm?.addEventListener("click", () => {
      if (!pendingDelete) return;
      const { blok, index } = pendingDelete;
      closeModal(false);
      removeCard(blok, index);
    });

    upd();
  });

    // Bagian form yang muncul sesuai pilihan radio (mis. valid / tolak)
    $$("form").forEach((form) => {
        const radios = $$("input[type=radio][data-reveal]", form);
        if (!radios.length) return;
        const sync = () => {
            const val = radios.find((r) => r.checked)?.dataset.reveal;
            $$("[data-reveal-target]", form).forEach((el) => {
                const on = el.dataset.revealTarget === val;
                el.hidden = !on;
                $$("input, textarea, select", el).forEach((f) => {
                    f.disabled = !on;
                });
            });
        };
        radios.forEach((r) => r.addEventListener("change", sync));
        sync();
    });


    // Field yang muncul sesuai pilihan select (mis. lab untuk petugas)
    $$("[data-reveal-select]").forEach((sel) => {
        const form = sel.form;
        const sync = () =>
            $$("[data-reveal-when]", form).forEach((el) => {
                const on = el.dataset.revealWhen.split(" ").includes(sel.value);
                el.hidden = !on;
                $$("input, select", el).forEach((f) => {
                    f.disabled = !on;
                });
            });
        sel.addEventListener("change", sync);
        sync();
    });

    // Cek berkas sebelum diunggah: jumlah & ukuran
    $$("input[type=file][data-max-size]").forEach((input) =>
        input.addEventListener("change", () => {
            const maxFiles = Number(input.dataset.maxFiles || 1);
            const maxSize = Number(input.dataset.maxSize);
            let msg = "";
            if (input.files.length > maxFiles)
                msg = `Pilih paling banyak ${maxFiles} berkas.`;
            else if (Array.from(input.files).some((f) => f.size > maxSize))
                msg = "Ada berkas yang lebih dari 5 MB.";
            else if (
                Array.from(input.files).some(
                    (f) => !/\.(pdf|jpe?g|png)$/i.test(f.name),
                )
            )
                msg = "Format berkas harus PDF, JPG, atau PNG.";
            input.setCustomValidity(msg);
            if (msg) {
                input.reportValidity();
                input.value = "";
                input.setCustomValidity("");
            }
        }),
    );

    // Perkiraan tagihan di form pengajuan
    // Perkiraan tagihan: tarif tiap parameter terpilih × jumlah pengujian + biaya administrasi.
    // Beberapa produk dalam satu permohonan: tiap blok [data-produk] punya jumlah sampel & parameter sendiri.
    const est = $("[data-estimasi]");
    if (est) {
        const adm = Number(est.dataset.admin);
        const daftar = $("[data-produk-daftar]");
        const tpl = $("[data-produk-template]");
        const tombolTambah = $("[data-produk-tambah]");
        const maks = Number(daftar?.dataset.maks || 10);
        const upd = () => {
            let total = 0;
            let n = 0;
            $$("input[data-param]").forEach((cb) => {
                const qty = $("[data-param-jumlah]", cb.closest("li"));
                if (qty) qty.disabled = !cb.checked;
                if (!cb.checked) return;
                n += 1;
                const j = Math.max(
                    1,
                    Math.min(500, parseInt(qty?.value, 10) || 1),
                );
                total += Number(cb.dataset.tarif) * j;
            });
            const blok = daftar ? $$("[data-produk]", daftar) : [];
            blok.forEach((b, i) => {
                const no = $("[data-produk-no]", b);
                if (no) no.textContent = i + 1;
                $("[data-produk-hapus]", b)?.classList.toggle(
                    "hidden",
                    blok.length < 2,
                );
            });
            if (tombolTambah) tombolTambah.disabled = blok.length >= maks;
            const np = $("[data-estimasi-produk]", est);
            if (np) np.textContent = blok.length || 1;
            $("[data-estimasi-n]", est).textContent = n;
            $("[data-estimasi-subtotal]", est).textContent = rupiah(total);
            $("[data-estimasi-total]", est).textContent = rupiah(total + adm);
        };
        const form = est.closest("form") || document;
        form.addEventListener("input", (e) => {
            // Jumlah sampel tidak mengisi jumlah pengujian: tiap parameter diisi sendiri (bawaan 1).
            upd();
        });
        form.addEventListener("change", (e) => {
            if (e.target.matches("input[data-param]")) upd();
        });
        // Indeks baru selalu lebih besar dari yang ada, supaya nama field tidak bentrok setelah ada yang dihapus.
        let berikut =
            Number(daftar?.dataset.berikut) ||
            (daftar ? $$("[data-produk]", daftar).length : 0);
        tombolTambah?.addEventListener("click", () => {
            if (!daftar || !tpl || $$("[data-produk]", daftar).length >= maks)
                return;
            const i = berikut++;
            const wrap = document.createElement("div");
            wrap.innerHTML = tpl.innerHTML.replaceAll("__i__", String(i));
            const blok = wrap.firstElementChild;
            daftar.appendChild(blok);
            upd();
            $('input[name$="[nama]"]', blok)?.focus();
        });
        daftar?.addEventListener("click", (e) => {
            const hapus = e.target.closest("[data-produk-hapus]");
            if (!hapus || $$("[data-produk]", daftar).length < 2) return;
            hapus.closest("[data-produk]").remove();
            upd();
        });
        upd();
    }

    // Konfirmasi tindakan penting + cegah kirim ganda
    let dirty = false;
    $$("form[data-guard]").forEach((form) => {
        form.addEventListener("input", () => {
            dirty = true;
        });
        form.addEventListener("submit", (e) => {
            const btn = e.submitter;
            if (btn?.dataset.confirm && !window.confirm(btn.dataset.confirm)) {
                e.preventDefault();
                return;
            }
            dirty = false;
            // Tombol yang dinonaktifkan tidak ikut terkirim: salin nama/nilainya ke input tersembunyi.
            if (btn?.name) {
                const h = document.createElement("input");
                h.type = "hidden";
                h.name = btn.name;
                h.value = btn.value;
                form.appendChild(h);
            }
            $$("button", form).forEach((b) => {
                b.disabled = true;
            });
            if (btn) btn.setAttribute("aria-busy", "true");
        });
    });
    $$("button[data-confirm]").forEach((btn) => {
        const form = btn.form;
        if (!form || form.hasAttribute("data-guard")) return;
        form.addEventListener("submit", (e) => {
            if (e.submitter === btn && !window.confirm(btn.dataset.confirm))
                e.preventDefault();
        });
    });

    // Jalur uji di layar sempit bisa digeser: langsung tampilkan langkah yang sedang berjalan.
    $$(".jalur").forEach((j) => {
        const now = $('[aria-current="step"]', j);
        const box = j.closest(".overflow-x-auto");
        if (now && box && box.scrollWidth > box.clientWidth) {
            box.scrollLeft = Math.max(
                0,
                now.offsetLeft - box.clientWidth / 2 + now.offsetWidth / 2,
            );
        }
    });

    // Cetak LHU
    $("[data-print]")?.addEventListener("click", () => window.print());

    // Pembaruan real-time: cek sidik versi data berkala; muat ulang bila berubah.
    // Tidak memuat ulang paksa saat pengguna sedang mengisi form.
    const body = document.body;
    const url = body.dataset.pollUrl;
    if (url) {
        const id = body.dataset.pollId;
        const every = Math.max(5, Number(body.dataset.pollSeconds || 8)) * 1000;
        const bar = $("[data-refresh-bar]");
        let base = null;
        let timer = null;
        const check = async () => {
            if (document.hidden) return;
            try {
                const res = await fetch(
                    url + (id ? "?p=" + encodeURIComponent(id) : ""),
                    {
                        headers: { Accept: "application/json" },
                        credentials: "same-origin",
                    },
                );
                if (res.status === 401 || res.redirected) {
                    clearInterval(timer);
                    return;
                }
                if (!res.ok) return;
                const { v } = await res.json();
                if (base === null) {
                    base = v;
                    return;
                }
                if (v !== base) {
                    const typing =
                        dirty ||
                        (document.activeElement &&
                            /INPUT|TEXTAREA|SELECT/.test(
                                document.activeElement.tagName,
                            ) &&
                            document.activeElement.closest("form"));
                    if (typing) bar?.classList.remove("hidden");
                    else window.location.reload();
                }
            } catch (_) {
                /* jaringan putus sementara: coba lagi di putaran berikutnya */
            }
        };
        check();
        timer = setInterval(check, every);
        document.addEventListener("visibilitychange", () => {
            if (!document.hidden) check();
        });
    }
    $("[data-refresh-now]")?.addEventListener("click", () =>
        window.location.reload(),
    );

    // Panduan: tampilkan satu bagian per halaman dengan tombol ← → (gaya buku panduan).
    // Tanpa JavaScript semua bagian tetap tampil berurutan; saat dicetak/disimpan PDF semua bagian ikut.
    const panduan = $("[data-panduan]");
    if (panduan) {
        const bab = $$("[data-bab]", panduan);
        const daftar = $$("[data-bab-link]");
        const n = bab.length;
        let aktif = 0;
        const tampilBab = (k, gulir) => {
            aktif = Math.max(0, Math.min(n - 1, k));
            bab.forEach((b, j) => b.classList.toggle("hidden", j !== aktif));
            daftar.forEach((a, j) => {
                if (j === aktif) a.setAttribute("aria-current", "true");
                else a.removeAttribute("aria-current");
            });
            $$("[data-bab-pos]").forEach((e) => {
                e.textContent = aktif + 1 + " / " + n;
            });
            $$("[data-bab-judul]").forEach((e) => {
                e.textContent = bab[aktif].dataset.judul;
            });
            $$("[data-bab-prev]").forEach((b) => {
                b.disabled = aktif === 0;
            });
            $$("[data-bab-next]").forEach((b) => {
                b.disabled = aktif === n - 1;
            });
            if (history.replaceState)
                history.replaceState(null, "", "#" + bab[aktif].id);
            if (gulir)
                panduan.scrollIntoView({ block: "start", behavior: "smooth" });
        };
        const dariHash = () =>
            Math.max(
                0,
                bab.findIndex((b) => "#" + b.id === window.location.hash),
            );
        const bar = $("[data-bab-bar]", panduan);
        bar?.classList.replace("hidden", "flex");
        daftar.forEach((a, j) =>
            a.addEventListener("click", (e) => {
                e.preventDefault();
                tampilBab(j, true);
            }),
        );
        $$("[data-bab-ke]", panduan).forEach((a) =>
            a.addEventListener("click", (e) => {
                e.preventDefault();
                tampilBab(Number(a.dataset.babKe), true);
            }),
        );
        $$("[data-bab-prev]").forEach((b) =>
            b.addEventListener("click", () => tampilBab(aktif - 1, false)),
        );
        $$("[data-bab-next]").forEach((b) =>
            b.addEventListener("click", () => tampilBab(aktif + 1, false)),
        );
        document.addEventListener("keydown", (e) => {
            if (
                e.target.closest(
                    "input, textarea, select, [contenteditable]",
                ) ||
                e.altKey ||
                e.ctrlKey ||
                e.metaKey
            )
                return;
            if (e.key === "ArrowRight") tampilBab(aktif + 1, false);
            if (e.key === "ArrowLeft") tampilBab(aktif - 1, false);
        });
        window.addEventListener("hashchange", () =>
            tampilBab(dariHash(), false),
        );
        tampilBab(dariHash(), false);
    }

    // Kotak yang bisa ditutup (mis. ajakan membaca panduan); diingat di browser ini saja.
    $$("[data-dismissable]").forEach((box) => {
        const key = "sipelab.tutup." + box.dataset.dismissable;
        try {
            if (localStorage.getItem(key)) {
                box.remove();
                return;
            }
        } catch (_) {
            /* penyimpanan diblokir */
        }
        $("[data-dismiss]", box)?.addEventListener("click", () => {
            try {
                localStorage.setItem(key, "1");
            } catch (_) {
                /* abaikan */
            }
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
                const res = await fetch(statusUrl, {
                    headers: { Accept: "application/json" },
                    credentials: "same-origin",
                });
                if (!res.ok || res.redirected) return;
                const { belum } = await res.json();
                $$("[data-chat-badge]").forEach((b) => {
                    b.textContent = belum;
                    b.classList.toggle("hidden", !belum);
                });
                // Di kotak masuk petugas: ada pesan baru di percakapan lain => tawarkan muat ulang daftar.
                if (
                    belumAwal !== null &&
                    belum > belumAwal &&
                    $("[data-chat-inbox]")
                ) {
                    $("[data-refresh-bar]")?.classList.remove("hidden");
                }
                belumAwal = belum;
            } catch (_) {
                /* coba lagi nanti */
            }
        };
        cekStatus();
        setInterval(cekStatus, 15000);
    }

    // Chat langsung: tarik pesan baru tiap 3 detik, kirim tanpa memuat ulang halaman.
    const chat = $("[data-chat]");
    if (chat) {
        const list = $("[data-chat-list]", chat);
        const form = $("[data-chat-form]", chat);
        const input = $("textarea", form);
        const tombol = $("[data-chat-kirim]", form);
        const galat = $("[data-chat-error]", form);
        const token = $('meta[name="csrf-token"]')?.content || "";
        const urlPesan = chat.dataset.urlPesan;
        const urlKirim = chat.dataset.urlKirim;
        let terakhir = 0;
        let sibuk = false;

        const dekatBawah = () =>
            list.scrollHeight - list.scrollTop - list.clientHeight < 120;
        const keBawah = () => {
            list.scrollTop = list.scrollHeight;
        };
        const el = (tag, cls, teks) => {
            const n = document.createElement(tag);
            n.className = cls;
            if (teks !== undefined) n.textContent = teks;
            return n;
        };

        // Isi pesan selalu lewat textContent (bukan innerHTML) => aman dari XSS.
        const tampil = (m) => {
            if (list.querySelector('[data-id="' + m.id + '"]')) return;
            $("[data-chat-kosong]", list)?.remove();
            const baris = el(
                "div",
                "flex " + (m.saya ? "justify-end" : "justify-start"),
            );
            baris.dataset.id = m.id;
            const gelembung = el(
                "div",
                "max-w-[85%] rounded-2xl px-4 py-2.5 text-sm shadow-sm sm:max-w-[70%] " +
                    (m.saya
                        ? "rounded-br-md bg-laut-600 text-white"
                        : m.balai
                          ? "rounded-bl-md bg-white text-tinta ring-1 ring-lamun-100"
                          : "rounded-bl-md bg-white text-tinta ring-1 ring-kabut-200"),
            );
            const meta = el(
                "p",
                "mb-0.5 text-2xs font-semibold " +
                    (m.saya
                        ? "text-laut-100"
                        : m.balai
                          ? "text-lamun-700"
                          : "text-kabut-500"),
                (m.saya ? "Anda" : m.nama + " · " + m.jabatan) +
                    " · " +
                    m.waktu,
            );
            gelembung.append(
                meta,
                el("p", "whitespace-pre-line break-words", m.isi),
            );
            baris.append(gelembung);
            list.append(baris);
        };

        const tampilOnline = (daftar) => {
            $$("[data-chat-online]").forEach((ul) => {
                ul.textContent = "";
                if (!daftar.length) {
                    ul.append(
                        el(
                            "li",
                            "text-sm text-kabut-500",
                            ul.hasAttribute("data-chat-online-chip")
                                ? "Tidak ada."
                                : "Belum ada petugas online. Pesan Anda tetap tersimpan dan akan dibalas.",
                        ),
                    );
                    return;
                }
                daftar.forEach((o) => {
                    if (ul.hasAttribute("data-chat-online-chip")) {
                        const li = el(
                            "li",
                            "inline-flex items-center gap-2 rounded-full bg-kabut-100 py-1 pl-1 pr-3 text-xs",
                        );
                        const teks = el("span", "");
                        teks.append(
                            el("span", "font-semibold", o.nama),
                            document.createTextNode(" · " + o.jabatan),
                        );
                        li.append(
                            el(
                                "span",
                                "grid h-6 w-6 place-items-center rounded-full bg-laut-600 text-2xs font-bold text-white",
                                o.inisial,
                            ),
                            teks,
                        );
                        ul.append(li);
                    } else {
                        const li = el("li", "flex items-center gap-3");
                        const av = el(
                            "span",
                            "relative grid h-8 w-8 shrink-0 place-items-center rounded-full bg-laut-100 text-xs font-bold text-laut-700",
                            o.inisial,
                        );
                        av.append(
                            el(
                                "span",
                                "absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full bg-lamun-500 ring-2 ring-white",
                            ),
                        );
                        const t = el("span", "min-w-0 leading-tight");
                        t.append(
                            el(
                                "span",
                                "block truncate text-sm font-semibold",
                                o.nama,
                            ),
                            el(
                                "span",
                                "block truncate text-xs text-kabut-500",
                                o.jabatan,
                            ),
                        );
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
                const res = await fetch(urlPesan + "?setelah=" + terakhir, {
                    headers: { Accept: "application/json" },
                    credentials: "same-origin",
                });
                if (!res.ok || res.redirected) return;
                const d = await res.json();
                const awal = terakhir === 0;
                const tempel = awal || dekatBawah();
                d.pesan.forEach((m) => {
                    tampil(m);
                    terakhir = Math.max(terakhir, m.id);
                });
                if (awal && !d.pesan.length) {
                    const kosong = $("[data-chat-kosong]", list);
                    if (kosong)
                        kosong.textContent =
                            "Belum ada pesan. Tulis pesan di bawah.";
                }
                if (tempel) keBawah();
                tampilOnline(d.online || []);
                const po = $("[data-chat-pengguna-online]");
                if (
                    po &&
                    d.pengguna_online !== null &&
                    d.pengguna_online !== undefined
                )
                    po.textContent = d.pengguna_online ? "online" : "offline";
            } catch (_) {
                /* jaringan putus sementara */
            } finally {
                sibuk = false;
            }
        };

        form.addEventListener("submit", async (e) => {
            e.preventDefault();
            const isi = input.value.trim();
            if (!isi) return;
            tombol.disabled = true;
            galat.classList.add("hidden");
            try {
                const res = await fetch(urlKirim, {
                    method: "POST",
                    credentials: "same-origin",
                    headers: {
                        "Content-Type": "application/json",
                        Accept: "application/json",
                        "X-CSRF-TOKEN": token,
                    },
                    body: JSON.stringify({ isi }),
                });
                if (res.ok) {
                    const { pesan } = await res.json();
                    tampil(pesan); // "terakhir" tidak dimajukan di sini agar pesan orang lain yang lebih dulu tidak terlewat
                    input.value = "";
                    keBawah();
                } else {
                    let msg = "Pesan gagal dikirim. Coba lagi.";
                    if (res.status === 419)
                        msg =
                            "Sesi berakhir. Muat ulang halaman lalu masuk kembali.";
                    else if (res.status === 429)
                        msg =
                            "Terlalu banyak pesan dalam waktu singkat. Tunggu sebentar.";
                    else {
                        try {
                            msg = (await res.json()).message || msg;
                        } catch (_) {
                            /* biarkan */
                        }
                    }
                    galat.textContent = msg;
                    galat.classList.remove("hidden");
                }
            } catch (_) {
                galat.textContent =
                    "Tidak terhubung ke server. Periksa jaringan Anda.";
                galat.classList.remove("hidden");
            } finally {
                tombol.disabled = false;
                input.focus();
            }
        });
        input.addEventListener("keydown", (e) => {
            if (e.key === "Enter" && !e.shiftKey && !e.isComposing) {
                e.preventDefault();
                form.requestSubmit();
            }
        });

        tarik();
        setInterval(() => {
            if (!document.hidden) tarik();
        }, 3000);
        document.addEventListener("visibilitychange", () => {
            if (!document.hidden) tarik();
        });
    }

  // Paraf penyelia: digambar di kanvas (mouse/jari/pena), disimpan sebagai PNG ke input tersembunyi.
  $$('[data-paraf]').forEach((box) => {
    const kanvas = $('canvas', box);
    const input = $('input[name="paraf"]', box);
    const ctx = kanvas.getContext('2d');
    let gambar = false;
    let ada = false;
    const titik = (e) => {
      const r = kanvas.getBoundingClientRect();
      return [(e.clientX - r.left) * (kanvas.width / r.width), (e.clientY - r.top) * (kanvas.height / r.height)];
    };
    const kosongkan = () => {
      ctx.clearRect(0, 0, kanvas.width, kanvas.height);
      input.value = '';
      ada = false;
    };
    ctx.lineWidth = 3;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = '#0b2a6b';
    kanvas.addEventListener('pointerdown', (e) => {
      e.preventDefault();
      kanvas.setPointerCapture(e.pointerId);
      gambar = true;
      const [x, y] = titik(e);
      ctx.beginPath();
      ctx.moveTo(x, y);
      ctx.lineTo(x + 0.1, y + 0.1);
      ctx.stroke();
    });
    kanvas.addEventListener('pointermove', (e) => {
      if (!gambar) return;
      const [x, y] = titik(e);
      ctx.lineTo(x, y);
      ctx.stroke();
      ada = true;
    });
    const selesai = () => {
      if (!gambar) return;
      gambar = false;
      if (ada) input.value = kanvas.toDataURL('image/png');
    };
    kanvas.addEventListener('pointerup', selesai);
    kanvas.addEventListener('pointercancel', selesai);
    $('[data-paraf-hapus]', box)?.addEventListener('click', kosongkan);
  });

  // Pangkat hasil uji (mis. 2,5 x 10³): tombol x² menyalakan mode pangkat; angka & tanda yang diketik menjadi superskrip.
  const SUP = { 0: '⁰', 1: '¹', 2: '²', 3: '³', 4: '⁴', 5: '⁵', 6: '⁶', 7: '⁷', 8: '⁸', 9: '⁹', '-': '⁻', '+': '⁺' };
  $$('[data-pangkat]').forEach((btn) => {
    const input = document.getElementById(btn.dataset.pangkat);
    if (!input) return;
    const atur = (aktif) => {
      btn.setAttribute('aria-pressed', aktif ? 'true' : 'false');
      btn.classList.toggle('bg-laut-600', aktif);
      btn.classList.toggle('text-white', aktif);
    };
    btn.addEventListener('click', () => {
      atur(btn.getAttribute('aria-pressed') !== 'true');
      input.focus();
    });
    input.addEventListener('beforeinput', (e) => {
      if (btn.getAttribute('aria-pressed') !== 'true' || e.inputType !== 'insertText' || !e.data) return;
      const ubah = [...e.data].map((c) => SUP[c] ?? c).join('');
      if (ubah === e.data) return;
      e.preventDefault();
      input.setRangeText(ubah, input.selectionStart, input.selectionEnd, 'end');
      input.dispatchEvent(new Event('input', { bubbles: true }));
    });
    // Keluar dari kolom mengakhiri mode pangkat.
    input.addEventListener('blur', (e) => { if (e.relatedTarget !== btn) atur(false); });
  });
})();

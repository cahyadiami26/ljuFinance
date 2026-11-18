/* =====================================================================
   LJU Finance — interaksi UI (vanilla JS, tanpa dependency)
   ===================================================================== */
(function () {
    'use strict';

    /* ---------------------------------------------------------- sidebar */
    function toggleSidebar(force) {
        var open = typeof force === 'boolean' ? force : !document.body.classList.contains('sidebar-open');
        document.body.classList.toggle('sidebar-open', open);
    }

    document.addEventListener('click', function (event) {
        var toggle = event.target.closest('[data-sidebar-toggle]');
        if (toggle) {
            event.preventDefault();
            toggleSidebar();
            return;
        }
        if (event.target.closest('[data-sidebar-close]')) {
            toggleSidebar(false);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            toggleSidebar(false);
        }
        // Ctrl/Cmd + K -> fokus pencarian
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
            var input = document.querySelector('.topbar-search input');
            if (input) {
                event.preventDefault();
                input.focus();
                input.select();
            }
        }
    });

    /* ------------------------------------------------- konfirmasi aksi */
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }
        var message = form.getAttribute('data-confirm');
        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });

    /* --------------------------------------------- tutup pesan flash */
    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-dismiss="flash"]');
        if (button) {
            var flash = button.closest('.flash');
            if (flash) {
                flash.style.transition = 'opacity .18s ease';
                flash.style.opacity = '0';
                setTimeout(function () { flash.remove(); }, 180);
            }
        }
    });

    /* ----------------------------------------- format input uang (Rp) */
    function formatMoney(value) {
        var digits = String(value).replace(/[^\d]/g, '');
        if (digits === '') {
            return '';
        }
        return Number(digits).toLocaleString('id-ID');
    }

    document.querySelectorAll('input[data-money]').forEach(function (input) {
        input.value = formatMoney(input.value);

        input.addEventListener('input', function () {
            var caretAtEnd = input.selectionStart === input.value.length;
            input.value = formatMoney(input.value);
            if (caretAtEnd) {
                input.setSelectionRange(input.value.length, input.value.length);
            }
        });

        var form = input.closest('form');
        if (form) {
            form.addEventListener('submit', function () {
                input.value = String(input.value).replace(/\./g, '');
            });
        }
    });

    /* ---------------------------------- auto submit filter (select) -- */
    document.querySelectorAll('[data-autosubmit]').forEach(function (select) {
        select.addEventListener('change', function () {
            if (select.form) {
                select.form.submit();
            }
        });
    });

    /* ------------------------- aset: umur & metode ikut kategori ---- */
    function angka(value) {
        var digits = String(value || '').replace(/[^\d]/g, '');
        return digits === '' ? 0 : parseInt(digits, 10);
    }

    var kategoriAset = document.querySelector('[data-asset-kategori]');
    if (kategoriAset) {
        var inputUmur = document.querySelector('[data-asset-umur]');
        var labelMetode = document.querySelector('[data-asset-metode]');

        var syncKategori = function () {
            var opsi = kategoriAset.options[kategoriAset.selectedIndex];
            if (!opsi) {
                return;
            }
            var umur = parseInt(opsi.getAttribute('data-umur') || '0', 10);
            var metode = opsi.getAttribute('data-metode') || '';
            if (inputUmur && !inputUmur.dataset.touched && umur > 0) {
                inputUmur.value = umur;
            }
            if (labelMetode) {
                labelMetode.textContent = metode !== '' ? 'Metode penyusutan: ' + metode : 'Metode mengikuti kategori.';
            }
        };

        if (inputUmur) {
            inputUmur.addEventListener('input', function () {
                inputUmur.dataset.touched = '1';
            });
        }
        kategoriAset.addEventListener('change', function () {
            if (inputUmur) {
                inputUmur.dataset.touched = '';
            }
            syncKategori();
        });
        syncKategori();
    }

    /* ------------------- pajak: tarif & nilai pajak otomatis -------- */
    var jenisPajak = document.querySelector('[data-tax-type]');
    var inputDpp = document.querySelector('[data-tax-dpp]');
    var inputTarif = document.querySelector('[data-tax-tarif]');
    var inputNilai = document.querySelector('[data-tax-nilai]');

    if (jenisPajak && inputDpp && inputTarif) {
        var hitungNilai = function () {
            if (!inputNilai) {
                return;
            }
            var nilai = Math.round(angka(inputDpp.value) * (parseFloat(inputTarif.value || '0') / 100));
            inputNilai.value = nilai > 0 ? nilai.toLocaleString('id-ID') : '';
        };

        jenisPajak.addEventListener('change', function () {
            var opsi = jenisPajak.options[jenisPajak.selectedIndex];
            var tarif = opsi ? parseFloat(opsi.getAttribute('data-tarif') || '0') : 0;
            if (tarif > 0) {
                inputTarif.value = tarif;
            }
            hitungNilai();
        });

        [inputDpp, inputTarif].forEach(function (input) {
            input.addEventListener('input', hitungNilai);
        });
    }

    /* ------------------------------------------ overlay loading submit */
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement) || form.dataset.noSpinner) {
            return;
        }
        var button = form.querySelector('button[type="submit"]');
        if (button && !button.disabled) {
            button.style.opacity = '.7';
            setTimeout(function () { button.style.opacity = ''; }, 2500);
        }
    });
})();

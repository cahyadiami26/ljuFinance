/* =====================================================================
   LJU Finance — interaksi form transaksi (kas, uang muka, biaya, rekonsiliasi)
   ===================================================================== */
(function () {
    'use strict';

    /* ---------------------------------------------------- format rupiah */
    function digits(value) {
        return String(value == null ? '' : value).replace(/[^\d]/g, '');
    }

    function formatMoney(value) {
        var d = digits(value);
        return d === '' ? '' : Number(d).toLocaleString('id-ID');
    }

    function toNumber(value) {
        var d = digits(value);
        return d === '' ? 0 : Number(d);
    }

    function formatRp(value) {
        var rounded = Math.round(Number(value) || 0);
        return 'Rp ' + rounded.toLocaleString('id-ID');
    }

    function bindMoney(input) {
        if (!input || input.dataset.moneyBound === '1') {
            return;
        }
        input.dataset.moneyBound = '1';
        input.value = formatMoney(input.value);
        input.addEventListener('input', function () {
            var atEnd = input.selectionStart === input.value.length;
            input.value = formatMoney(input.value);
            if (atEnd) {
                input.setSelectionRange(input.value.length, input.value.length);
            }
        });
    }

    function stripMoneyOnSubmit(form) {
        if (!form) {
            return;
        }
        form.addEventListener('submit', function () {
            form.querySelectorAll('input[data-money]').forEach(function (input) {
                input.value = digits(input.value);
            });
        });
    }

    /* ---------------------------------------------- baris dinamis (rowset) */
    var indexCounter = {};

    function maxIndex(tbody) {
        var max = -1;
        tbody.querySelectorAll('[data-row]').forEach(function (row) {
            row.querySelectorAll('[name]').forEach(function (field) {
                var match = /\[(\d+)\]/.exec(field.getAttribute('name') || '');
                if (match) {
                    max = Math.max(max, parseInt(match[1], 10));
                }
            });
        });
        return max;
    }

    document.querySelectorAll('[data-rowset]').forEach(function (tbody) {
        var name = tbody.getAttribute('data-rowset');
        indexCounter[name] = maxIndex(tbody);

        var row = tbody.querySelector('[data-row]');
        if (row) {
            stripMoneyOnSubmit(row.closest('form'));
        }
    });

    document.addEventListener('click', function (event) {
        var add = event.target.closest('[data-rowset-add]');
        if (add) {
            event.preventDefault();
            var name = add.getAttribute('data-rowset-add');
            var tbody = document.querySelector('[data-rowset="' + name + '"]');
            var template = document.getElementById(name + '-template');
            if (!tbody || !template) {
                return;
            }
            indexCounter[name] = (indexCounter[name] == null ? maxIndex(tbody) : indexCounter[name]) + 1;
            var html = template.innerHTML.replace(/__INDEX__/g, String(indexCounter[name]));
            var wrapper = document.createElement('tbody');
            wrapper.innerHTML = html.trim();
            var newRow = wrapper.firstElementChild;
            if (!newRow) {
                return;
            }
            tbody.appendChild(newRow);

            newRow.querySelectorAll('input[data-money]').forEach(function (input) {
                bindMoney(input);
            });
            newRow.querySelectorAll('input, select').forEach(function (field) {
                field.addEventListener('input', recalc);
                field.addEventListener('change', recalc);
            });

            var first = newRow.querySelector('input:not([type="hidden"]), select');
            if (first) {
                first.focus();
            }
            recalc();
            return;
        }

        var remove = event.target.closest('[data-row-remove]');
        if (remove) {
            event.preventDefault();
            var row = remove.closest('[data-row]');
            var body = row ? row.parentElement : null;
            if (!row || !body) {
                return;
            }
            if (body.querySelectorAll('[data-row]').length <= 1) {
                row.querySelectorAll('input').forEach(function (input) {
                    if (input.tagName === 'INPUT') {
                        input.value = '';
                    }
                });
                row.querySelectorAll('select').forEach(function (select) {
                    select.selectedIndex = 0;
                });
            } else {
                row.remove();
            }
            recalc();
        }
    });

    /* ---------------------------------------------- total pengajuan biaya */
    function recalc() {
        var form = document.getElementById('expense-form');
        if (!form) {
            return;
        }

        var subtotal = 0;
        var ppn = 0;
        var pph = 0;

        form.querySelectorAll('[data-rowset="expense-items"] [data-row]').forEach(function (row) {
            var jumlah = row.querySelector('[data-item-jumlah]');
            var rowPpn = row.querySelector('[data-item-ppn]');
            var rowPph = row.querySelector('[data-item-pph]');
            subtotal += toNumber(jumlah ? jumlah.value : 0);
            ppn += toNumber(rowPpn ? rowPpn.value : 0);
            pph += toNumber(rowPph ? rowPph.value : 0);
        });

        var total = subtotal + ppn - pph;

        var targets = {
            subtotal: formatRp(subtotal),
            ppn: formatRp(ppn),
            pph: formatRp(pph),
            total: formatRp(total)
        };
        form.querySelectorAll('[data-expense-total]').forEach(function (el) {
            var key = el.getAttribute('data-expense-total');
            if (targets[key] !== undefined) {
                el.textContent = targets[key];
            }
        });
    }

    /* --------------------------------------------- isi pajak otomatis */
    document.addEventListener('change', function (event) {
        var select = event.target.closest('[data-item-pajak]');
        if (!select) {
            return;
        }
        var option = select.options[select.selectedIndex];
        var row = select.closest('[data-row]');
        if (!option || !row) {
            return;
        }
        var jenis = (option.getAttribute('data-jenis') || '').toLowerCase();
        var tarif = parseFloat(option.getAttribute('data-tarif') || '0') || 0;
        if (tarif <= 0) {
            return;
        }
        var jumlahInput = row.querySelector('[data-item-jumlah]');
        var base = toNumber(jumlahInput ? jumlahInput.value : 0);
        if (base <= 0) {
            return;
        }
        if (jenis === 'ppn') {
            var ppnInput = row.querySelector('[data-item-ppn]');
            if (ppnInput) {
                ppnInput.value = formatMoney(Math.round(base * tarif / 100));
            }
        } else {
            var pphInput = row.querySelector('[data-item-pph]');
            if (pphInput) {
                pphInput.value = formatMoney(Math.round(base * tarif / 100));
            }
        }
        recalc();
    });

    /* ------------------------------------------- tipe transaksi kas */
    function syncCashTipe() {
        var select = document.querySelector('[data-cash-tipe]');
        if (!select) {
            return;
        }
        var value = select.value;
        document.querySelectorAll('[data-cash-tipe-show]').forEach(function (el) {
            el.hidden = el.getAttribute('data-cash-tipe-show') !== value;
        });
        document.querySelectorAll('[data-cash-tipe-hide]').forEach(function (el) {
            var hideFor = el.getAttribute('data-cash-tipe-hide');
            el.hidden = hideFor === value;
        });
    }

    /* ------------------------------------------------------- inisialisasi */
    document.querySelectorAll('input[data-money]').forEach(bindMoney);
    document.querySelectorAll('[data-rowset] [data-row] input, [data-rowset] [data-row] select').forEach(function (field) {
        field.addEventListener('input', recalc);
        field.addEventListener('change', recalc);
    });

    var cashTipe = document.querySelector('[data-cash-tipe]');
    if (cashTipe) {
        cashTipe.addEventListener('change', syncCashTipe);
        syncCashTipe();
    }

    recalc();
})();
